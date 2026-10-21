<?php

namespace App\Http\Controllers\Site;

use App\Enums\MentorshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Site\MentorshipRequestResource;
use App\Models\MentorshipRequest;
use App\Models\Student;
use App\Notifications\Alerts\MentorshipRequested;
use App\Notifications\MentorshipUpdate;
use App\Support\MentorshipSettings;
use App\Support\TeamAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MyMentorshipController extends Controller
{
    /**
     * The student's latest request (null before they apply).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $latest = $student->mentorshipRequests()->latest('id')->first();

        return response()->json(['data' => $latest === null ? null : (new MentorshipRequestResource($latest))->resolve($request)]);
    }

    /**
     * Apply: one open request per student, while requests are open and a seat is free. The package's name and
     * price are copied in, so a later price change doesn't change what this student was offered.
     *
     * @throws ValidationException
     */
    public function store(Request $request, MentorshipSettings $settings): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $validated = $request->validate([
            'package_id' => ['required', 'string', Rule::in(array_column($settings->all()['packages'], 'id'))],
            'goal' => ['required', 'string', 'min:10', 'max:1000'],
            'background' => ['required', 'string', 'min:10', 'max:1000'],
            'links' => ['nullable', 'string', 'max:500'],
            'availability' => ['required', 'string', 'max:300'],
            'accept_policy' => ['accepted'],
        ], [], [
            'package_id' => 'الباقة',
            'goal' => 'هدفك',
            'background' => 'مستواك الحالي',
            'links' => 'الروابط',
            'availability' => 'الأوقات المناسبة',
            'accept_policy' => 'سياسة الإلغاء والاسترجاع',
        ]);

        $mentorship = DB::transaction(function () use ($student, $settings, $validated): MentorshipRequest {
            // the student row is locked so a double submit can't open two requests
            Student::query()->lockForUpdate()->find($student->id);

            $error = match (true) {
                $student->mentorshipRequests()->whereIn('status', MentorshipStatus::open())->exists() => 'لديك طلب إرشاد قيد المتابعة.',
                ! $settings->all()['enabled'] || $settings->wallets() === [] => 'استقبال طلبات الإرشاد مغلق حالياً.',
                $settings->seatsLeft() === 0 => 'المقاعد مكتملة حالياً، جرّب لاحقاً.',
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(['mentorship' => $error]);
            }

            $package = $settings->package($validated['package_id']);

            return $student->mentorshipRequests()->create([
                ...collect($validated)->only(['goal', 'background', 'links', 'availability'])->all(),
                'package_id' => $package['id'],
                'package_name' => $package['name'],
                'price' => $package['price'],
            ]);
        });

        $student->notify(new MentorshipUpdate($mentorship, 'received'));
        TeamAlerts::send(new MentorshipRequested($mentorship));

        return response()->json([
            'message' => 'وصل طلبك، وسأردّ عليك خلال يومين.',
            'data' => (new MentorshipRequestResource($mentorship->refresh()))->resolve($request),
        ], 201);
    }

    /**
     * Send the payment receipt (an image or a PDF) for an accepted request, before the payment window closes.
     * Sending another replaces the first.
     *
     * @throws ValidationException
     */
    public function proof(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $validated = $request->validate([
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
        ], [], ['proof' => 'إشعار التحويل', 'transaction_ref' => 'رقم العملية']);

        $mentorship = $student->mentorshipRequests()->latest('id')->first();

        if ($mentorship === null || ! $mentorship->awaitsPayment()) {
            throw ValidationException::withMessages(['proof' => 'لا يوجد طلب بانتظار الدفع، أو انتهت مهلة رفع الإشعار.']);
        }

        $file = $validated['proof'];
        $mentorship->deleteProof();
        $mentorship->forceFill([
            'status' => MentorshipStatus::ProofSubmitted,
            'proof_path' => $file->store("mentorship/{$mentorship->id}", MentorshipRequest::proofDisk()),
            'proof_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'transaction_ref' => $validated['transaction_ref'] ?? null,
            'proof_uploaded_at' => now(),
            'decision_note' => null,
        ])->save();

        TeamAlerts::send(new MentorshipRequested($mentorship, receipt: true));

        return response()->json([
            'message' => 'وصل إشعار التحويل، وسأراجعه وأؤكّد لك الدفع قريباً.',
            'data' => (new MentorshipRequestResource($mentorship))->resolve($request),
        ]);
    }
}

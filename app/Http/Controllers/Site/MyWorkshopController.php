<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\WorkshopResource;
use App\Models\Student;
use App\Models\Workshop;
use App\Notifications\Alerts\RegistrationReceived;
use App\Support\TeamAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MyWorkshopController extends Controller
{
    /**
     * The workshops the student registered in, soonest first (past ones too).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $workshops = Workshop::query()
            ->whereIn('id', $student->workshopRegistrations()->select('workshop_id'))
            ->withCount('registrations')
            ->orderBy('date')->orderBy('start_time')
            ->get();

        return response()->json(['data' => WorkshopResource::collection($workshops)->resolve($request)]);
    }

    /**
     * Take a seat in a workshop that hasn't ended and still has one free (registering twice changes nothing).
     *
     * The seat count and the insert run in one transaction with the workshop row locked, so two students racing
     * for the last seat can't both get it.
     *
     * @throws ValidationException
     */
    public function store(Request $request, Workshop $workshop): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $registration = DB::transaction(function () use ($student, $workshop) {
            $workshop = Workshop::query()->lockForUpdate()->findOrFail($workshop->id);
            $existing = $workshop->registrations()->where('student_id', $student->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $error = match (true) {
                $workshop->hasEnded() => 'انتهت هذه الورشة.',
                $workshop->seatsTaken() >= $workshop->seats => 'لا توجد مقاعد متاحة في هذه الورشة.',
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(['workshop' => $error]);
            }

            return $workshop->registrations()->create(['student_id' => $student->id]);
        });

        if ($registration->wasRecentlyCreated) {
            TeamAlerts::send(new RegistrationReceived($student, $workshop));
        }

        return response()->json([
            'message' => 'تم حجز مقعدك في الورشة.',
            'data' => (new WorkshopResource($workshop->loadCount('registrations')))->resolve($request),
        ], $registration->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Give the seat back, while the workshop hasn't ended.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request, Workshop $workshop): Response
    {
        /** @var Student $student */
        $student = $request->user();

        if ($workshop->hasEnded()) {
            throw ValidationException::withMessages(['workshop' => 'انتهت هذه الورشة.']);
        }

        $workshop->registrations()->where('student_id', $student->id)->delete();

        return response()->noContent();
    }
}

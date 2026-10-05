<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\UserAgent;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in member's devices, read from the database session store.
 *
 * Session ids are never sent to the browser (they would let a script take over the other device);
 * each session is identified by a hash of its id instead.
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isTracked()) {
            return response()->json(['data' => [], 'meta' => ['tracked' => false]]);
        }

        $currentId = $request->session()->getId();

        $sessions = $this->sessionsOf($request)
            // sessions idle past the lifetime are already expired, even before garbage collection deletes them
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session) use ($currentId): array {
                $agent = new UserAgent($session->user_agent ?? '');
                $lastActive = Carbon::createFromTimestamp($session->last_activity);

                return [
                    'id' => $this->publicId($session->id),
                    'device' => $agent->label(),
                    'is_mobile' => $agent->isMobile(),
                    'ip_address' => $session->ip_address,
                    'last_active_at' => $lastActive->toIso8601String(),
                    'last_active' => $lastActive->diffInMinutes() < 1 ? 'الآن' : $lastActive->locale(app()->getLocale())->diffForHumans(),
                    'is_current' => hash_equals($session->id, $currentId),
                ];
            });

        return response()->json(['data' => $sessions, 'meta' => ['tracked' => true]]);
    }

    /**
     * End one of the member's other sessions.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request, string $session): Response
    {
        abort_unless($this->isTracked(), 404);

        $id = $this->sessionsOf($request)->pluck('id')
            ->first(fn (string $id): bool => hash_equals($this->publicId($id), $session));

        abort_if($id === null, 404);

        if (hash_equals($id, $request->session()->getId())) {
            throw ValidationException::withMessages([
                'session' => 'لا يمكنك إنهاء جلستك الحالية من هنا، استخدم تسجيل الخروج.',
            ]);
        }

        $this->sessionsOf($request)->where('id', $id)->delete();

        // a "remember me" cookie on that device would otherwise sign it straight back in
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();

        return response()->noContent();
    }

    private function isTracked(): bool
    {
        return config('session.driver') === 'database';
    }

    private function sessionsOf(Request $request): Builder
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $request->user()->getKey());
    }

    private function publicId(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, config('app.key'));
    }
}

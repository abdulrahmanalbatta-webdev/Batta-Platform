<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriberResource;
use App\Models\Student;
use App\Models\Subscriber;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SubscriberController extends Controller
{
    /**
     * The newsletter list, newest first, marking the addresses that are also students.
     */
    public function index(): AnonymousResourceCollection
    {
        return SubscriberResource::collection(
            Subscriber::query()
                ->addSelect(['is_student' => Student::query()->selectRaw('1')->whereColumn('students.email', 'subscribers.email')->limit(1)])
                ->latest()->latest('id')
                ->get(),
        );
    }

    /**
     * Removes an address from the list (a student who is removed still gets the emails as a student).
     */
    public function destroy(Subscriber $subscriber): Response
    {
        $subscriber->delete();

        return response()->noContent();
    }
}

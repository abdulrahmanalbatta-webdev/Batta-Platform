<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Tell the login page who signed in and where to go next: the page the guest was sent away from, or the dashboard.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $redirect = redirect()->intended(config('fortify.home'));

        if (! $request->wantsJson()) {
            return $redirect;
        }

        return (new UserResource($request->user()))
            ->additional(['redirect' => $redirect->getTargetUrl()])
            ->response();
    }
}

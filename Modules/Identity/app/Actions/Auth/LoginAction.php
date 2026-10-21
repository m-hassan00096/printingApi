<?php

namespace Modules\Identity\Actions\Auth;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Exceptions\Auth\InactiveAccountException;
use Modules\Identity\Exceptions\Auth\InvalidCredentialsException;
use Modules\Identity\Models\User;

class LoginAction
{
    public function execute(
        array $credentials,
        Session $session
    ): User {
        $inactive = false;

        $guard = Auth::guard('web');

        $authenticated = $guard->attemptWhen(
            $credentials,
            function (User $user) use (&$inactive): bool {
                if (! $user->isActive()) {
                    $inactive = true;

                    return false;
                }

                return true;
            }
        );

        if (! $authenticated) {
            if ($inactive) {
                throw new InactiveAccountException();
            }

            throw new InvalidCredentialsException();
        }

        $session->regenerate();

        return $guard->user();
    }
}

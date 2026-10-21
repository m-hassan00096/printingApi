<?php

namespace Modules\Identity\Http\Middleware;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssignedRoleIsActive
{
    /**
     * Deny API requests for staff assigned to an inactive role immediately.
     * Query the database instead of using a possibly cached roles relation.
     * No role remains handled by normal route permission middleware.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A disabled user must still be able to log out or start a new login.
        if ($request->is('api/v1/auth/login', 'api/v1/auth/logout')) {
            return $next($request);
        }

        $user = $request->user('sanctum');

        if ($user instanceof User && $user->roles()
            ->where('roles.guard_name', 'web')
            ->where('roles.status', RoleStatus::INACTIVE->value)
            ->exists()) {
            return ApiResponse::error(
                message: __('role_management.inactive_role'),
                status: HttpStatusCode::FORBIDDEN,
            );
        }

        return $next($request);
    }
}

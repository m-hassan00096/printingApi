<?php

namespace Modules\Identity\Actions\Users;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use App\Services\Files\FileUploadService;
use Modules\Identity\Services\UserRoleService;
use Throwable;
use Modules\Identity\Enums\UserStatus;
class CreateUserAction
{
    public function __construct(
        private readonly UserRoleService $roles,
        private readonly FileUploadService $files,
    ) {}

    public function execute(array $data): User
    {
        $avatarPath = null;

        try {
            if (($data['avatar'] ?? null) instanceof UploadedFile) {
                $avatarPath = $this->files->store($data['avatar'], 'avatars/users');
            }

            return DB::transaction(function () use ($data, $avatarPath): User {
                // The User model hashes the password via its 'hashed' cast.
                $attributes = Arr::only($data, [
                    'name',
                    'username',
                    'phone',
                    'address',
                    'status',
                    'password',
                ]);

                // Apply default status before creating the model.
                $attributes['status'] = $data['status']
                    ?? UserStatus::ACTIVE->value;

                $attributes['avatar'] = $avatarPath;

                $user = User::create($attributes);

                $this->roles->assign($user, (int) $data['roleId']);

                return $user->load('roles.translations');
            });
        } catch (Throwable $exception) {
            // Filesystem changes do not roll back with the database transaction.
            $this->files->delete($avatarPath, 'avatars/users');

            throw $exception;
        }
    }
}

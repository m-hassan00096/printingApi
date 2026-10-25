<?php

namespace Modules\Identity\Actions\Users;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use App\Services\Files\FileUploadService;
use Modules\Identity\Services\UserRoleService;
use Modules\Identity\Services\LastActiveAdminGuard;
use Throwable;

class UpdateUserAction
{
    public function __construct(
        private readonly UserRoleService $roles,
        private readonly FileUploadService $files,
        private readonly LastActiveAdminGuard $adminGuard,
    ) {}

    public function execute(User $user, array $data): User
    {
        $oldAvatarPath = $user->getRawOriginal('avatar'); // Accessor returns a URL, not a storage path.
        $newAvatarPath = null;

        try {
            if (($data['avatar'] ?? null) instanceof UploadedFile) {
                $newAvatarPath = $this->files->store($data['avatar'], 'avatars/users');
            }

            $updatedUser = DB::transaction(function () use ($user, $data, $newAvatarPath): User {
                $this->adminGuard->assertCanUpdate($user, $data);

                $user->fill(Arr::only($data, [
                    'name', 'username', 'phone', 'address', 'status', 'password',
                ]));

                // No uploaded file means the existing avatar is preserved.
                if ($newAvatarPath !== null) {
                    $user->avatar = $newAvatarPath;
                }

                $user->save();

                if (array_key_exists('roleId', $data)) {
                    $this->roles->assign($user, (int) $data['roleId']);
                }

                return $user->refresh()->load('roles.translations');
            });
        } catch (Throwable $exception) {
            $this->files->delete($newAvatarPath, 'avatars/users');

            throw $exception;
        }

        // Remove the previous file only after the DB transaction succeeded.
        if ($newAvatarPath !== null) {
            $this->files->delete($oldAvatarPath, 'avatars/users');
        }

        return $updatedUser;
    }
}

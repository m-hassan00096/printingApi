<?php

namespace Modules\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Identity\Enums\UserStatus;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = ['name', 'username', 'phone', 'address', 'avatar', 'password', 'status'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }


    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (?string $path): ?string => $path
                ? Storage::disk('public')->url($path)
                : null,

            set: fn (?string $path): ?string => $path === null
                ? null
                : ltrim($path, '/'),
        );
    }


    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}

<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Passport;

class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const TYPE_ADMIN = 'admin';

    public const TYPE_USER = 'user';

    public const TYPES = [self::TYPE_ADMIN, self::TYPE_USER];

    /**
     * The attributes that are mass assignable.
     *
     * The privilege-bearing "type" attribute is intentionally absent: it is
     * only ever set explicitly (see UserController, RegisterController and
     * the app:create-admin command), never from a request payload.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Plain-text values are hashed on assignment; values that are
            // already hashes (e.g. from laravel/ui's reset flow) are kept.
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('password')) {
                // Cover profile changes, administrator resets, and recovery
                // resets alike. Old remember-me cookies, access tokens and
                // refresh tokens cannot survive a password rotation.
                $user->setRememberToken(Str::random(60));
                $user->revokeAllOAuthTokens();
            }
        });

        static::deleting(function (User $user): void {
            $user->revokeAllOAuthTokens();
        });
    }

    /**
     * Revoke every Passport access token (and its refresh token) issued to
     * this user, regardless of which client issued it.
     */
    public function revokeAllOAuthTokens(): void
    {
        $tokenIds = Passport::token()->newQuery()
            ->select('id')
            ->where('user_id', $this->getKey());

        Passport::refreshToken()->newQuery()
            ->whereIn('access_token_id', $tokenIds)
            ->update(['revoked' => true]);

        Passport::token()->newQuery()
            ->where('user_id', $this->getKey())
            ->update(['revoked' => true]);
    }

    public function isAdmin(): bool
    {
        return $this->type === self::TYPE_ADMIN;
    }

    /**
     * Get the profile photo URL attribute.
     */
    public function getPhotoAttribute(): string
    {
        return 'https://www.gravatar.com/avatar/'.md5(strtolower((string) $this->email)).'.jpg?s=200&d=mm';
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function assignRole(Role $role)
    {
        return $this->roles()->save($role);
    }
}

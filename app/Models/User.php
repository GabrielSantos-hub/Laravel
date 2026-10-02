<?php

namespace App\Models;

use App\Exceptions\CannotRemoveLastAdminException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * `role` fica de fora de propósito: o cadastro público não promove ADM.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADM';
    }

    public function isLastAdmin(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        return static::query()->where('role', 'ADM')->count() <= 1;
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if (! $user->isDirty('role')) {
                return;
            }

            $eraAdmin = $user->getOriginal('role') === 'ADM';
            $continuaAdmin = $user->role === 'ADM';

            if ($eraAdmin && ! $continuaAdmin && static::query()->where('role', 'ADM')->count() <= 1) {
                throw CannotRemoveLastAdminException::becauseLastAdmin();
            }
        });

        static::deleting(function (User $user): void {
            if ($user->isLastAdmin()) {
                throw CannotRemoveLastAdminException::becauseLastAdmin();
            }
        });
    }

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar)) {
            return null;
        }

        return url('storage/'.$this->avatar);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }
}
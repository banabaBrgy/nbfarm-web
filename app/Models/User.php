<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'role',
        'password',
        'security_question',
        'security_answer',

        // Added for User Management
        'status',
        'last_login_at',
        'push_alerts_enabled',
    ];

    /**
     * Hidden attributes.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            // Added for User Management
            'last_login_at' => 'datetime',
            'push_alerts_enabled' => 'boolean',
        ];
    }

    public function isFarmLead(): bool
    {
        return in_array(self::routeRole($this->role), ['owner', 'manager'], true);
    }

    public function isOwner(): bool
    {
        return self::routeRole($this->role) === 'owner';
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function farmLeads()
    {
        return static::query()
            ->get()
            ->filter(fn (self $user) => $user->isFarmLead())
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, self> */
    public static function owners()
    {
        return static::query()
            ->get()
            ->filter(fn (self $user) => $user->isOwner())
            ->values();
    }

    /**
     * Normalize stored role values to route prefixes (staff, manager, owner).
     */
    public static function routeRole(?string $role = null): string
    {
        $role = strtolower(trim($role ?? auth()->user()?->role ?? ''));

        return match ($role) {
            'poultry staff', 'staff' => 'staff',
            'farm manager', 'manager' => 'manager',
            'farm owner', 'owner' => 'owner',
            default => $role,
        };
    }
}
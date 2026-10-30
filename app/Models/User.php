<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        'status',
        'last_login_at',
        'push_alerts_enabled',
    ];

    /**
     * Hidden attributes.
     */
    protected $hidden = [
        'password',
        'security_answer',
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

    public function rotateAccess(?string $keepSessionId = null): void
    {
        $this->session_version = (int) $this->session_version + 1;
        $this->remember_token = Str::random(60);
        $this->save();
        $this->deleteStoredSessions($keepSessionId);

        if ($keepSessionId !== null && session()->getId() === $keepSessionId) {
            session(['session_version' => $this->session_version]);
        }
    }

    public function deleteStoredSessions(?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->id);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    public function hasSecurityAnswer(): bool
    {
        return trim((string) $this->security_answer) !== '';
    }

    public static function hashSecurityAnswer(string $answer): string
    {
        return Hash::make(mb_strtolower(trim($answer)));
    }

    public function securityAnswerMatches(string $answer): bool
    {
        $stored = (string) $this->security_answer;

        if ($stored === '') {
            return false;
        }

        if (! self::isHashedSecret($stored)) {
            return false;
        }

        return Hash::check(mb_strtolower(trim($answer)), $stored);
    }

    public static function isHashedSecret(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$2b$')
            || str_starts_with($value, '$argon2');
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
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    // Roles disponibles
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN       = 'admin';
    const ROLE_COLLECTOR   = 'collector';

    protected $fillable = [
        'tenant_id',
        'role',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ─── Filament panel access ─────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    // ─── Helpers de rol ────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCollector(): bool
    {
        return $this->role === self::ROLE_COLLECTOR;
    }

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Wallet del cobrador (uno a uno) */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    /** Calles asignadas a este cobrador */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_user')
            ->withPivot('assigned_at');
    }

    /** Liquidaciones creadas por este cobrador */
    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class, 'collector_id');
    }
}

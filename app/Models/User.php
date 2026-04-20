<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, \App\Traits\BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];

    public function canAccessPanel(Panel $panel): bool
    {
        // For development, we allow all roles. 
        // You can restrict it later to 'admin' or 'super_admin'
        return true; 
    }

    public function assignedSectors(): BelongsToMany { return $this->belongsToMany(Sector::class, 'sector_user')->withPivot('assigned_at'); }
    public function collectionRounds(): HasMany { return $this->hasMany(CollectionRound::class, 'collector_id'); }
    
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
}

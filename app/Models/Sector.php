<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = ['tenant_id', 'name', 'description'];

    public function properties(): HasMany { return $this->hasMany(Property::class); }
}

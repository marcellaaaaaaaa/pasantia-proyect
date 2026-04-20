<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Person extends Model
{
    use HasFactory, \App\Traits\BelongsToTenant;
    protected $fillable = [
        'tenant_id', 'family_id', 'full_name', 'id_number', 
        'birth_date', 'phone', 'email', 'is_primary_contact'
    ];
    protected $casts = ['birth_date' => 'date', 'is_primary_contact' => 'boolean'];

    public function family(): BelongsTo { return $this->belongsTo(Family::class); }
}

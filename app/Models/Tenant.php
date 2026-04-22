<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'status', 'notes'];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = self::uniqueSlugFrom($tenant->name);
            }
        });
    }

    protected static function uniqueSlugFrom(string $name): string
    {
        $base = Str::slug($name) ?: 'comunidad';
        $slug = $base;
        $i = 2;
        while (self::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    public function users(): HasMany { return $this->hasMany(User::class); }
}

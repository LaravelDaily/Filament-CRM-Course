<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price'];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: static fn($value) => $value / 100,
            set: static fn($value) => $value * 100,
        );
    }

    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class);
    }
}
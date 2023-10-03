<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    protected $fillable = [
        'name',
        'position',
        'is_default',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}

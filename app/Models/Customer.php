<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    use HasFactory;


    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'description',
        'lead_source_id',
        'pipeline_stage_id'
    ];

    public static function booted(): void
    {
        self::created(function (Customer $customer) {
            $customer->pipelineStageLogs()->create([
                'pipeline_stage_id' => $customer->pipeline_stage_id,
                'user_id' => auth()->check() ? auth()->id() : null
            ]);
        });
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class);
    }

    public function pipelineStageLogs(): HasMany
    {
        return $this->hasMany(CustomerPipelineStage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomFieldCustomer::class);
    }
}
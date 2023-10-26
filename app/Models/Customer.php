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
        'pipeline_stage_id',
        'employee_id',
    ];

    public static function booted(): void
    {
        self::created(function (Customer $customer) {
            $customer->pipelineStageLogs()->create([
                'pipeline_stage_id' => $customer->pipeline_stage_id,
                'employee_id' => $customer->employee_id,
                'user_id' => auth()->check() ? auth()->id() : null
            ]);
        });

        self::updated(function (Customer $customer) {
            $lastLog = $customer->pipelineStageLogs()->whereNotNull('employee_id')->latest()->first();

            if ($customer->employee_id !== $lastLog) {
                $customer->pipelineStageLogs()->create([
                    'employee_id' => $customer->employee_id,
                    'notes' => is_null($customer->employee_id) ? 'Employee removed' : '',
                    'user_id' => auth()->id()
                ]);
            }
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function completedTasks(): HasMany
    {
        return $this->hasMany(Task::class)->where('is_completed', true);
    }

    public function incompleteTasks(): HasMany
    {
        return $this->hasMany(Task::class)->where('is_completed', false);
    }
}
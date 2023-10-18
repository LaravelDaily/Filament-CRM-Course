<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\PipelineStage;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ManageCustomerStages extends Page
{
    protected static string $view = 'filament.pages.manage-customer-stages';

    protected ?string $heading = 'Customer Board';
    protected static ?string $navigationLabel = 'Customer Board';
    protected static ?string $navigationIcon = 'heroicon-s-queue-list';

    #[On('statusChangeEvent')]
    public function changeRecordStatus($id, $pipeline_stage_id): void
    {
        $customer = Customer::find($id);
        $customer->pipeline_stage_id = $pipeline_stage_id;
        $customer->save();

        $customer->pipelineStageLogs()->create([
            'pipeline_stage_id' => $pipeline_stage_id,
            'notes' => null,
            'user_id' => auth()->id()
        ]);

        $customerName = $customer->first_name . ' ' . $customer->last_name;

        Notification::make()
            ->title($customerName . ' Pipeline Stage Updated')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $statuses = $this->statuses();

        $records = $this->records();

        $statuses = $statuses
            ->map(function ($status) use ($records) {
                $status['group'] = $this->getId();
                $status['kanbanRecordsId'] = "{$this->getId()}-{$status['id']}";
                $status['records'] = $records
                    ->filter(function ($record) use ($status) {
                        return $this->isRecordInStatus($record, $status);
                    });

                return $status;
            });

        return [
            'records' => $records,
            'statuses' => $statuses,
        ];
    }

    protected function statuses(): Collection
    {
        return PipelineStage::query()
            ->orderBy('position')
            ->get()
            ->map(function (PipelineStage $stage) {
                return [
                    'id' => $stage->id,
                    'title' => $stage->name,
                ];
            });
    }

    protected function records(): Collection
    {
        return Customer::all()
            ->map(function (Customer $item) {
                return [
                    'id' => $item->id,
                    'title' => $item->first_name . ' ' . $item->last_name,
                    'status' => $item->pipeline_stage_id,
                ];
            });
    }

    protected function isRecordInStatus($record, $status): bool
    {
        return $record['status'] === $status['id'];
    }
}

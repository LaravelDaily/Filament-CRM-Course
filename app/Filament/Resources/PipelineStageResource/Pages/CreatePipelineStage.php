<?php

namespace App\Filament\Resources\PipelineStageResource\Pages;

use App\Filament\Resources\PipelineStageResource;
use App\Models\PipelineStage;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePipelineStage extends CreateRecord
{
    protected static string $resource = PipelineStageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = PipelineStage::max('position') + 1;

        return $data;
    }
}

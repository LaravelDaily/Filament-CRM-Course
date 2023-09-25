<?php

namespace App\Filament\Resources\LeadSourceResource\Pages;

use App\Filament\Resources\LeadSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeadSources extends ListRecords
{
    protected static string $resource = LeadSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

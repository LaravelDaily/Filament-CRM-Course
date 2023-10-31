<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use URL;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Edit Quote')
                ->icon('heroicon-m-pencil-square')
                ->url(EditQuote::getUrl([$this->record])),
            Action::make('Download Quote')
                ->icon('heroicon-s-document-check')
                ->url(URL::signedRoute('quotes.pdf', [$this->record->id]), true),
        ];
    }
}

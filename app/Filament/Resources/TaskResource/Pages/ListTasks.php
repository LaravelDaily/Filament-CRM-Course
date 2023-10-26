<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        if (!auth()->user()->isAdmin()) {
            $tabs[] = Tab::make('My Tasks')
                ->badge(Task::where('user_id', auth()->id())->count())
                ->modifyQueryUsing(function ($query) {
                    return $query->where('user_id', auth()->id());
                });
        }

        $tabs[] = Tab::make('All Tasks')
            ->badge(Task::count());

        $tabs[] = Tab::make('Completed Tasks')
            ->badge(Task::where('is_completed', true)->count())
            ->modifyQueryUsing(function ($query) {
                return $query->where('is_completed', true);
            });

        $tabs[] = Tab::make('Incomplete Tasks')
            ->badge(Task::where('is_completed', false)->count())
            ->modifyQueryUsing(function ($query) {
                return $query->where('is_completed', false);
            });

        return $tabs;
    }
}

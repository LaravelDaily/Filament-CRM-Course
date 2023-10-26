<?php

namespace App\Livewire;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class TaskCalendarWidget extends FullCalendarWidget
{
    public function fetchEvents(array $fetchInfo): array
    {
        return Task::query()
            ->where('due_date', '>=', $fetchInfo['start'])
            ->where('due_date', '<=', $fetchInfo['end'])
            ->when(!auth()->user()->isAdmin(), function ($query) {
                return $query->where('user_id', auth()->id());
            })
            ->get()
            ->map(
                fn(Task $task) => EventData::make()
                    ->id($task->id)
                    ->title(strip_tags($task->description))
                    ->start($task->due_date)
                    ->end($task->due_date)
                    ->url(TaskResource::getUrl('edit', [$task->id]))
                    ->toArray()
            )
            ->toArray();
    }
}

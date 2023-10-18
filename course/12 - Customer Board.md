It is common to manage Customers in bulk or have an overview of where the Customer is progressing. For that, we can create a board like this:

![](https://laraveldaily.com/uploads/2023/10/customerBoard.png)

In this lesson, we will do the following:

- Create a Custom Page
- Add a "kanban" style board to it
- Allow the user to move customers between Pipeline Stages

---

## Creating Custom Page - Our Customer Board

We will create a Custom Page using the Filament command:

```bash
php artisan make:filament-page ManageCustomerStages
```

This should create two files:

- `app/Filament/Pages/ManageCustomerStages.php` - the page class
- `resources/views/filament/pages/manage-customer-stages.blade.php` - the page view

We will begin with modifications to our page class:

**app/Filament/Pages/ManageCustomerStages.php**
```php

use App\Models\Customer;
use App\Models\PipelineStage;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ManageCustomerStages extends Page
{
    protected static string $view = 'filament.pages.manage-customer-stages';

    // Our Custom heading to be displayed on the page
    protected ?string $heading = 'Customer Board';
    // Custom Navigation Link name
    protected static ?string $navigationLabel = 'Customer Board';
    // Adding a Heroicon to the Navigation Link
    protected static ?string $navigationIcon = 'heroicon-s-queue-list';

    // We will be listening for the `statusChangeEvent` event to update the record status
    #[On('statusChangeEvent')]
    public function changeRecordStatus($id, $pipeline_stage_id): void
    {
        // Find the customer and update the pipeline_stage_id
        $customer = Customer::find($id);
        $customer->pipeline_stage_id = $pipeline_stage_id;
        $customer->save();

        // Don't forget to write the log
        $customer->pipelineStageLogs()->create([
            'pipeline_stage_id' => $pipeline_stage_id,
            'notes' => null,
            'user_id' => auth()->id()
        ]);

        // Inform the user that the status has been updated
        $customerName = $customer->first_name . ' ' . $customer->last_name;

        Notification::make()
            ->title($customerName . ' Pipeline Stage Updated')
            ->success()
            ->send();
    }

    // Data that we will pass to our View
    protected function getViewData(): array
    {
        $statuses = $this->statuses();

        $records = $this->records();
        
        // We are mapping through the statuses and adding the records to each status
        // This will form multiple lists dynamically based on the records
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

    // Loading the statuses from the database and mapping them
    // to have id and title. ID will be checked against Customers
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

    // We are loading all the customers and mapping them to have ID, title, and status
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

    // We are checking if the record is in the status
    protected function isRecordInStatus($record, $status): bool
    {
        return $record['status'] === $status['id'];
    }
}
```

Loading our page in the browser, we should see the following:

![](https://laraveldaily.com/uploads/2023/10/emptyCustomerBoard.png)

This is because we have yet to modify the view. Let's do that now:

**resources/views/filament/pages/manage-customer-stages.blade.php**
```blade
<x-filament-panels::page>
    <x-filament::card wire:ignore.self>
        <div>
            <div class="w-full h-full flex space-x-4 rtl:space-x-reverse overflow-x-auto">
                @foreach($statuses as $status)
                    <div class="h-full flex-1">
                        <div class="bg-primary-200 rounded px-2 flex flex-col h-full" id="{{ $status['id'] }}">
                            <div class="p-2 text-sm text-gray-900">
                                {{ $status['title'] }}
                            </div>
                            <div
                                    id="{{ $status['kanbanRecordsId'] }}"
                                    data-status-id="{{ $status['id'] }}"
                                    class="space-y-2 p-2 flex-1 overflow-y-auto">

                                @foreach($status['records'] as $record)
                                    <div
                                            id="{{ $record['id'] }}"
                                            class="shadow bg-white dark:bg-gray-800 p-2 rounded border">

                                        <p>
                                            {{ $record['title'] }}
                                        </p>

                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>


            <div wire:ignore>
                <script>
                    window.onload = () => {
                        @foreach($statuses as $status)
                        {{-- Space here is needed to fix the Livewire issue where it adds comment block breaking JS scripts--}}
                        Sortable.create(document.getElementById('{{ $status['kanbanRecordsId'] }}'), {
                            group: '{{ $status['group'] }}',
                            animation: 0,
                            ghostClass: 'bg-warning-600',

                            setData: function (dataTransfer, dragEl) {
                                dataTransfer.setData('id', dragEl.id);
                            },

                            onEnd: function (evt) {
                                const sameContainer = evt.from === evt.to;
                                const orderChanged = evt.oldIndex !== evt.newIndex;

                                if (sameContainer && !orderChanged) {
                                    return;
                                }

                                const recordId = evt.item.id;
                                const toStatusId = evt.to.dataset.statusId;

                                @this.
                                dispatch('statusChangeEvent', {
                                    id: recordId,
                                    pipeline_stage_id: toStatusId
                                });
                            },
                        });
                        @endforeach
                    }
                </script>

            </div>
        </div>
    </x-filament::card>
</x-filament-panels::page>
```

Let's look at what we did here and why:

**Note:** We will refer to Pipeline Stages as Status here (to match the codebase)

- We are using the `x-filament-panels::page` component to have the page layout
- We are using the `x-filament::card` component to have the card layout
- We are using `wire:ignore.self` to ignore the card itself from Livewire
- We are looping through the statuses and creating a column for each status
- We then fill each status with the records that belong to it - Customers
- We are using the `Sortable` library to make the records draggable
- We are creating multiple sortable lists, one for each status
- We are checking if the record was moved from one status to another by listening to the `onEnd` event
- If the record was moved, we are dispatching the `statusChangeEvent` event to Livewire
- This event is processed by our page class

That's it! Opening our page in the browser, we should see the following:

![](https://laraveldaily.com/uploads/2023/10/customerBoard.png)

And, of course, moving the customer between the stages should work as well:

![](https://laraveldaily.com/uploads/2023/10/customerBoardMove.png)

(It's less obvious, but a notification pops up when it's moved.)

---

## Why There Was no Package Used?

We have tried to use a package for this [Kanban's Board page](https://v2.filamentphp.com/plugins/kanbans-board-page) by [David Vincent](https://github.com/invaders-xx), but we found some issues with it. It did not work as expected, and while it was possible to override some parts - it was not easy to explain why these modifications were needed, nor was it easy to do so in some cases. This became a reason why we took the approach of creating this ourselves without the package. In any case, credits for this page goes to [David Vincent](https://github.com/invaders-xx)

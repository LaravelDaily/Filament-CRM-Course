Now that we have Employees - they usually have to perform specific Tasks with our Customers. For example, they might need to make a phone call to them or send over some documents. For that, we can build a Task system with a calendar view like this:

![](https://laraveldaily.com/uploads/2023/10/taskCalendarWidget.png)

In this lesson, we will do the following:

- Create Task Model and Database
- Add the Create Task button to the Customer list
- Add Task list to the Customer page (view page)
- Add Task Resource with Tabs
- Add a Calendar page for Tasks

---

## Create Task Model and Database

Let's start with our Models and Database structure:

**Migration**
```php
use App\Models\Customer;
use App\Models\User;

// ...

Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(Customer::class)->constrained();
    $table->foreignIdFor(User::class)->nullable()->constrained();
    $table->text('description');
    $table->date('due_date')->nullable();
    $table->boolean('is_completed')->default(false);
    $table->timestamps();
});
```

Then, we can fill our Model:

**app/Models/Task.php**
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'description',
        'due_date',
        'is_completed',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

That's it. We have our base structure for the Task Model and Database.

---

## Add Create Task button to the Customer list

Next, we want to add a button to create a new task for each of our customers:

**app/Filament/Resources/CustomerResource.php**
```php
// ...

return $table
    // ...
    ->actions([
        // ...
        Tables\Actions\Action::make('Add Task')// [tl! add:start]
            ->icon('heroicon-s-clipboard-document')
            ->form([
                Forms\Components\RichEditor::make('description')
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->preload()
                    ->searchable()
                    ->relationship('employee', 'name'),
                Forms\Components\DatePicker::make('due_date')
                    ->native(false),

            ])
            ->action(function (Customer $customer, array $data) {
                $customer->tasks()->create($data);

                Notification::make()
                    ->title('Task created successfully')
                    ->success()
                    ->send();
            })// [tl! add:end]
    ])

// ...
```

Before we load the page, we need to add a relationship to our Customer Model:

**app/Models/Customer.php**
```php
// ...

public function tasks(): HasMany
{
    return $this->hasMany(Task::class);
}

// ...
```

Now we can load the page and see these buttons:

![](https://laraveldaily.com/uploads/2023/10/customerTableTaskCreateButton.png)

Clicking on them will open a modal with a form to create a new Task:

![](https://laraveldaily.com/uploads/2023/10/customerTableTaskCreateModal.png)

---

## Add Task List to the Customer Page

Now that we can create tasks, we cannot know what tasks are assigned to the Customer. Let's solve that by adding it to our Customer View page:

**app/Filament/Resources/CustomerResource.php**
```php
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Actions\Action;

// ...

return $infolist
    ->schema([
        // ...
        Section::make('Pipeline Stage History and Notes')
            ->schema([
                ViewEntry::make('pipelineStageLogs')
                    ->label('')
                    ->view('infolists.components.pipeline-stage-history-list')
            ])
            ->collapsible()// [tl! --]
            ->collapsible(),// [tl! add:start]
        Tabs::make('Tasks')
            ->tabs([
                Tabs\Tab::make('Completed')
                    ->badge(fn($record) => $record->completedTasks->count())
                    ->schema([
                        RepeatableEntry::make('completedTasks')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('description')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('employee.name')
                                    ->hidden(fn($state) => is_null($state)),
                                TextEntry::make('due_date')
                                    ->hidden(fn($state) => is_null($state))
                                    ->date(),
                            ])
                            ->columns()
                    ]),
                Tabs\Tab::make('Incomplete')
                    ->badge(fn($record) => $record->incompleteTasks->count())
                    ->schema([
                        RepeatableEntry::make('incompleteTasks')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('description')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('employee.name')
                                    ->hidden(fn($state) => is_null($state)),
                                TextEntry::make('due_date')
                                    ->hidden(fn($state) => is_null($state))
                                    ->date(),
                                TextEntry::make('is_completed')
                                    ->formatStateUsing(function ($state) {
                                        return $state ? 'Yes' : 'No';
                                    })
                                    ->suffixAction(
                                        Action::make('complete')
                                            ->button()
                                            ->requiresConfirmation()
                                            ->modalHeading('Mark task as completed?')
                                            ->modalDescription('Are you sure you want to mark this task as completed?')
                                            ->action(function (Task $record) {
                                                $record->is_completed = true;
                                                $record->save();

                                                Notification::make()
                                                    ->title('Task marked as completed')
                                                    ->success()
                                                    ->send();
                                            })
                                    ),
                            ])
                            ->columns(3)
                    ])
            ])
            ->columnSpanFull(),// [tl! add:end]
    ]);

// ...
```

With this, we expect two new relationships in our Customer Model:

**app/Models/Customer.php**
```php
// ...

public function completedTasks(): HasMany
{
    return $this->hasMany(Task::class)->where('is_completed', true);
}

public function incompleteTasks(): HasMany
{
    return $this->hasMany(Task::class)->where('is_completed', false);
}

// ...
```

These relationships will load specific information for displaying our `RepeatableEntry` fields. Treat them as a way to filter the data.

Now, loading our Customer view - we can see the Tasks section:

![](https://laraveldaily.com/uploads/2023/10/customerViewTasks.png)

In our incomplete tab - we can see the button to mark the task as completed:

![](https://laraveldaily.com/uploads/2023/10/customerViewTasksCompleteButton.png)

---

## Add Task Resource with Tabs

Creating tasks from the Customer page is nice - we should have a separate page for that. Let's create a new Resource for that:

```bash
php artisan make:filament-resource Task --generate
```

By default, Filament guessed the fields, but it's not entirely accurate:

![](https://laraveldaily.com/uploads/2023/10/taskResourceDefaultFields.png)

Even our Create form is not quite right:

![](https://laraveldaily.com/uploads/2023/10/taskResourceDefaultCreateForm.png)

Let's fix both of these to be up to our standards. First, we will fix the table to show the correct information:

**Note:** We have replaced the whole table

**app/Filament/Resources/TaskResource.php**
```php
use Filament\Notifications\Notification;

// ...

return $table
    ->columns([
        Tables\Columns\TextColumn::make('customer.first_name')
            ->formatStateUsing(function ($record) {
                return $record->customer->first_name . ' ' . $record->customer->last_name;
            })
            ->searchable(['first_name', 'last_name'])
            ->sortable(),
        Tables\Columns\TextColumn::make('employee.name')
            ->label('Employee')
            ->searchable()
            ->sortable(),
        Tables\Columns\TextColumn::make('description')
            ->html(),
        Tables\Columns\TextColumn::make('due_date')
            ->date()
            ->sortable(),
        Tables\Columns\IconColumn::make('is_completed')
            ->boolean(),
        Tables\Columns\TextColumn::make('created_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true),
        Tables\Columns\TextColumn::make('updated_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true),
    ])
    ->filters([
        //
    ])
    ->actions([
        Tables\Actions\EditAction::make(),
        Tables\Actions\Action::make('Complete')
            ->hidden(fn(Task $record) => $record->is_completed)
            ->icon('heroicon-m-check-badge')
            ->modalHeading('Mark task as completed?')
            ->modalDescription('Are you sure you want to mark this task as completed?')
            ->action(function (Task $record) {
                $record->is_completed = true;
                $record->save();

                Notification::make()
                    ->title('Task marked as completed')
                    ->success()
                    ->send();
            })
    ])
    ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
            Tables\Actions\DeleteBulkAction::make(),
        ]),
    ])
    ->defaultSort(function ($query) {
        return $query->orderBy('due_date', 'asc')
            ->orderBy('id', 'desc');
    });

// ...
```

This fixed a couple of things:

- Employee name display
- Customer name display
- Added default sort by due date and id
- Added `Complete` action to the table

Here's what this looks like now:

![](https://laraveldaily.com/uploads/2023/10/taskResourceTable.png)

Next, we need to fix our form:

**Note:** Once again, we have replaced the whole form

**app/Filament/Resources/TaskResource.php**
```php
use App\Models\Customer;

// ...

return $form
    ->schema([
        Forms\Components\Select::make('customer_id')
            ->searchable()
            ->relationship('customer')
            ->getOptionLabelFromRecordUsing(fn(Customer $record) => $record->first_name . ' ' . $record->last_name)
            ->searchable(['first_name', 'last_name'])
            ->required(),
        Forms\Components\Select::make('user_id')
            ->preload()
            ->searchable()
            ->relationship('employee', 'name'),
        Forms\Components\RichEditor::make('description')
            ->required()
            ->maxLength(65535)
            ->columnSpanFull(),
        Forms\Components\DatePicker::make('due_date'),
        Forms\Components\Toggle::make('is_completed')
            ->required(),
    ]);

// ...
```

This fixes the following:

- Customer select field is now searchable by first/last names and displays the full name
- Employee select field is now searchable by name
- Description is now a RichEditor

Here's what this looks like now:

![](https://laraveldaily.com/uploads/2023/10/taskResourceCreateForm.png)

---

## Adding Tabs to the Task Resource

Now that our table is fixed, we can add a couple of tabs to our Task Resource:

- My Tasks - filter for employees to only see their tasks
- All Tasks - displays all tasks in the system
- Completed Tasks - displays only completed tasks
- Incomplete Tasks - displays only incomplete tasks

**app/Filament/Resources/TaskResource/Pages/ListTasks.php**
```php
use App\Models\Task;
use Filament\Resources\Components\Tab;

// ...

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
```

Here's what this looks like for admins:

![](https://laraveldaily.com/uploads/2023/10/taskResourceTabsAdmin.png)

And for employees:

![](https://laraveldaily.com/uploads/2023/10/taskResourceTabsEmployee.png)

---

## Add a Calendar Page for Tasks

Last on our list is a custom page using [Filament FullCalendar plugin](https://github.com/saade/filament-fullcalendar). Let's install the plugin via composer:

```bash
composer require saade/filament-fullcalendar:^3.0
```

Then we need to register it in our `AdminPanelProvider`:

**app/Filament/Providers/AdminPanelProvider.php**
```php
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

// ...

return $panel
    // ...
    ->authMiddleware([
        Authenticate::class,
    ])
     ->plugins([
        FilamentFullCalendarPlugin::make()
    ]);

// ...
```

Once that is done, we can create a livewire page:

```bash
php artisan make:filament-page TaskCalendar
```

This should create the file `app/Filament/Pages/TaskCalendar.php`. Next, we need to create a widget for it:

```bash
php artisan make:filament-widget TaskCalendar
```

Make sure that your settings are correct:

![](https://laraveldaily.com/uploads/2023/10/taskCalendarWidgetSettings.png)

**Note:** We are aiming to create a livewire widget here!

Once that is done, you should have a new file `app/Livewire/TaskCalendarWidget.php`, which means we can add the widget to our page:

**resources/views/filament/pages/task-calendar.blade.php**
```blade
<x-filament-panels::page>
    @livewire(App\Livewire\TaskCalendarWidget::class){{-- [tl! ++] --}}
</x-filament-panels::page>
```

Now, all we have to do - is modify our widget itself:

**app/Livewire/TaskCalendarWidget.php**
```php
use Filament\Widgets\Widget;// [tl! --]
use App\Filament\Resources\TaskResource;// [tl! add:start]
use App\Models\Task;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;// [tl! add:end]

class TaskCalendarWidget extends Widget// [tl! --]
class TaskCalendarWidget extends FullCalendarWidget// [tl! ++]
{
    protected static string $view = 'livewire.task-calendar-widget';// [tl! --]
    
    public function fetchEvents(array $fetchInfo): array// [tl! add:start]
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
    }// [tl! add:end]
}
```

Since we removed the `$view` from our widget, we can delete this file `resources/views/livewire/task-calendar-widget.blade.php`

Now we can go to our page and see the calendar:

![](https://laraveldaily.com/uploads/2023/10/taskCalendarWidget.png)

That's it. We have a working Task system now with a calendar view. If you wish to modify what the calendar does, you can read the [plugin documentation](https://github.com/saade/filament-fullcalendar?tab=readme-ov-file#table-of-contents).

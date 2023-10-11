Currently, we have Customers and Pipeline Stages in our system. Still, there is no easy way to move our Customers within the Pipeline while saving its history. Let's fix that by adding a table Action:

![](https://laraveldaily.com/uploads/2023/10/customerMoveToStageButton.png)

In this lesson, we will do the following:

- Create a `CustomerPipelineStage` Model to save the history of the Customer's Pipeline Stage changes and any comments added.
- Add creating and updating action Observers to our Customer Model to save the history.

---

## Creating Logs Model and Table

Our `CustomerPipelineStage` Model will be a simple table with the following fields:

- `customer_id` - the Customer ID
- `pipeline_stage_id` (nullable) - the Pipeline Stage ID. It is nullable to allow notes without status change.
- `user_id` (nullable) - the User who made the change. It is nullable to allow system-triggered changes to be logged.
- `notes` (nullable, text) - any notes added to the change

Let's create the Migration:

**Migration**
```php
use App\Models\Customer;
use App\Models\PipelineStage;
use App\Models\User;

// ...

Schema::create('customer_pipeline_stages', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(Customer::class)->constrained();
    $table->foreignIdFor(PipelineStage::class)->nullable()->constrained();
    $table->foreignIdFor(User::class)->nullable()->constrained();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

Next, we will create the Model:

**app/Models/CustomerPipelineStage.php**
```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPipelineStage extends Model
{
    protected $fillable = [
        'customer_id',
        'pipeline_stage_id',
        'user_id',
        'notes'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class);
    }
}
```

And finally, we will add the relationship to our `Customer` Model:

**app/Models/Customer.php**
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// ...

public function pipelineStageLogs(): HasMany
{
    return $this->hasMany(CustomerPipelineStage::class);
}
```

That's it. We have the Database table ready for our customer logs.

---

## Creating Table Action

Now that we have our Database and Models ready, we can implement the table Action:

****
```php
use Filament\Notifications\Notification;

// ...

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ...
        ])
        ->filters([
            // ...
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('Move to Stage')// [tl! add:start]
                ->icon('heroicon-m-pencil-square')
                ->form([
                    Forms\Components\Select::make('pipeline_stage_id')
                        ->label('Status')
                        ->options(PipelineStage::pluck('name', 'id')->toArray())
                        ->default(function (Customer $record) {
                            $currentPosition = $record->pipelineStage->position;
                            return PipelineStage::where('position', '>', $currentPosition)->first()?->id;
                        }),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                ])
                ->action(function (Customer $customer, array $data): void {
                    $customer->pipeline_stage_id = $data['pipeline_stage_id'];
                    $customer->temporary_notes_field = $data['notes'];
                    $customer->save();

                    Notification::make()
                        ->title('Customer Pipeline Updated')
                        ->success()
                        ->send();
                }),// [tl! add:end]
        ])
        ->bulkActions([
            // ...
        ]);
}

// ...
```

Few things to note here:

- Our select will, by default, retrieve the next Pipeline Stage in the list so that we don't have to select it manually.
- Our `notes` are being written into a temporary field that will be removed from the Model at the update event.
- We are sending a notification to the User after the update.

Here's what this looks like in the UI:

![](https://laraveldaily.com/uploads/2023/10/customerMoveToStageButton.png)

---

## Creating Pipeline Stage Logger

The last thing to do is to create the Observers to log all the Pipeline Stage changes:

**app/Models/Customer.php**
```php
// ...

public static function booted(): void
{
    self::created(function (Customer $customer) {
        $customer->pipelineStageLogs()->create([
            'pipeline_stage_id' => $customer->pipeline_stage_id,
            'user_id' => auth()->check() ? auth()->id() : null
        ]);
    });

    self::updating(function (Customer $customer) {
        if ($customer->isDirty(['status', 'temporary_notes_field'])) {
            $customer->pipelineStageLogs()->create([
                'pipeline_stage_id' => $customer->pipeline_stage_id,
                'notes' => $customer->temporary_notes_field,
                'user_id' => auth()->check() ? auth()->id() : null
            ]);
            unset($customer->attributes['temporary_notes_field']);
        }
    });
}

// ...
```

This is going to listen for create and update events. Here's what it will do for each event:

Create:
- Create a new `CustomerPipelineStage` record with the `pipeline_stage_id` and `user_id` (if logged in) from the Customer.

Update:
- If the `status` or `temporary_notes_field` fields are dirty, create a new `CustomerPipelineStage` record with the `pipeline_stage_id`, `notes` and `user_id` (if logged in) from the Customer.

Remember that the `temporary_notes_field` does not exist on the Model, so we need to unset it from the attributes array. Otherwise, it will throw an error.

That's it. In the next lesson, we will build the table filters.

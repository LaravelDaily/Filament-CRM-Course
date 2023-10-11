Quite often, CRMs do not fit in the pre-defined fields. This is where Custom Fields come in handy, as they allow users to set up their own fields for the CRM and fill Customer profiles with them:

![](https://laraveldaily.com/uploads/2023/10/customFieldsCRUD2.png)

In this lesson, we will do the following:

- Create a Custom Field database table and Model
- Create a pivot table for the Custom Field and Customer relationship
- Create a pivot Model type for Filament to better handle the relationship
- Create simple Custom Field seeders
- Create a Custom Field CRUD (Filament Resource)
- Add Custom Field to the Customer Resource via Repeater Component
- Display Custom Fields on the Customer View page - we will generate them dynamically

---

## Preparing Database, Models and Seeders

Let's start by creating our Custom Fields database. It will have just one field - `name`:

**Migration**
```php
Schema::create('custom_fields', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

Next, we know that this is going to be a many-to-many relationship, so we need a pivot table:

**Migration**
```php
use App\Models\Customer;
use App\Models\CustomField;

// ...

Schema::create('custom_field_customer', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(Customer::class)->constrained();
    $table->foreignIdFor(CustomField::class)->constrained();
    $table->string('value');
    $table->timestamps();
});
```

Then, we can create our Models:

**app/Models/CustomField.php**
```php
class CustomField extends Model
{
    protected $fillable = [
        'name'
    ];
}
```

And a pivot Model (Filament uses it to better handle the relationship):

**app/Models/CustomFieldCustomer.php**
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomFieldCustomer extends Pivot
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
```

The last Model to update is the Customer Model, as we need to define the relationship:

> **Note:** This is not a many-to-many relationship, as we use a pivot Model. So we need to use `HasMany` instead of `BelongsToMany`. It works the same as many-to-many, but now with an intermediate model!

**app/Models/Customer.php**
```php
public function customFields(): HasMany
{
    return $this->hasMany(CustomFieldCustomer::class);
}
```

Now, we can create our seeders:

**database/seeders/DatabaseSeeder.php**
```php
use App\Models\CustomField;

// ...

public function run(): void
{
    // ...
    
    $customFields = [
        'Birth Date',
        'Company',
        'Job Title',
        'Family Members',
    ];

    foreach ($customFields as $customField) {
        CustomField::create(['name' => $customField]);
    }
}
```

Running migrations and seeds:

```bash
php artisan migrate:fresh --seed
```

Should now give us a few Custom Fields in the database:

![](https://laraveldaily.com/uploads/2023/10/customFieldsDatabaseExample.png)

---

## Creating Custom Field CRUD

We created the Resource CRUD with this command:

```bash
php artisan make:filament-resource CustomField --generate
```

Then, all we had to do - was move the navigation item to the Settings group:

**app/Filament/Resources/CustomFieldResource.php**
```php
class CustomFieldResource extends Resource
{
    // ...
    
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';// [tl! --]
    protected static ?string $navigationGroup = 'Settings';// [tl! ++]


    // ...
}
```

That's it. We have our Custom Field CRUD in the Settings group:

![](https://laraveldaily.com/uploads/2023/10/customFieldsCRUD.png)

---

## Adding Custom Field to the Customer Resource

To add our Custom Fields to Customer, we have to modify the Customer Resource form:

**app/Filament/Resources/CustomerResource.php**
```php
use App\Models\CustomField;
use Filament\Forms\Get;

// ...

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Customer Details')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('last_name')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone_number')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ])
                ->columns(),
            Forms\Components\Section::make('Lead Details')
                ->schema([
                    Forms\Components\Select::make('lead_source_id')
                        ->relationship('leadSource', 'name'),
                    Forms\Components\Select::make('tags')
                        ->relationship('tags', 'name')
                        ->multiple(),
                    Forms\Components\Select::make('pipeline_stage_id')
                        ->relationship('pipelineStage', 'name', function ($query) {
                            $query->orderBy('position', 'asc');
                        })
                        ->default(PipelineStage::where('is_default', true)->first()?->id)
                ])
                ->columns(3),
            Forms\Components\Section::make('Documents')
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Repeater::make('customerDocuments')
                        ->relationship('customerDocuments')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->addActionLabel('Add Document')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->required(),
                            Forms\Components\Textarea::make('comments'),
                        ])
                        ->columns()
                ]),
            Forms\Components\Section::make('Additional fields') // [tl! add:start]
                ->schema([
                    Forms\Components\Repeater::make('fields')
                        ->hiddenLabel()
                        ->relationship('customFields')
                        ->schema([
                            Forms\Components\Select::make('custom_field_id')
                                ->label('Field Type')
                                ->options(CustomField::pluck('name', 'id')->toArray())
                                // We will disable already selected fields
                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                    return collect($get('../*.custom_field_id'))
                                        ->reject(fn($id) => $id === $state)
                                        ->filter()
                                        ->contains($value);
                                })
                                ->required()
                                // Adds search bar to select
                                ->searchable()
                                // Live is required to make sure that the options are updated
                                ->live(),
                            Forms\Components\TextInput::make('value')
                                ->required()
                        ])
                        ->addActionLabel('Add another Field')
                        ->columns(),
                ]),// [tl! add:end]
        ]);
}

// ...
```

That's it! We will now have a new section in the Customer Resource form where we can add Custom Fields:

![](https://laraveldaily.com/uploads/2023/10/customFieldsCRUD2.png)

---

## Displaying Custom Fields on the Customer View page

Last on our list is the display of Custom Fields when viewing Customer. This will use a dynamic approach, as we don't know how many Custom Fields there will be:

**app/Filament/Resources/CustomerResource.php**
```php
// ...

public static function infoList(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            Section::make('Personal Information')
                ->schema([
                    TextEntry::make('first_name'),
                    TextEntry::make('last_name'),
                ])
                ->columns(),
            Section::make('Contact Information')
                ->schema([
                    TextEntry::make('email'),
                    TextEntry::make('phone_number'),
                ])
                ->columns(),
            Section::make('Additional Details')
                ->schema([
                    TextEntry::make('description'),
                ]),
            Section::make('Lead and Stage Information')
                ->schema([
                    TextEntry::make('leadSource.name'),
                    TextEntry::make('pipelineStage.name'),
                ])
                ->columns(),
            Section::make('Additional fields')// [tl! add:start]
                ->hidden(fn($record) => $record->customFields->isEmpty())
                ->schema(
                    // We are looping within our relationship, then creating a TextEntry for each Custom Field
                    fn($record) => $record->customFields->map(function ($customField) {
                        return TextEntry::make($customField->customField->name)
                            ->label($customField->customField->name)
                            ->default($customField->value);
                    })->toArray()
                )
                ->columns(),// [tl! add:end]
            Section::make('Documents')
                ->hidden(fn($record) => $record->customerDocuments->isEmpty())
                ->schema([
                    RepeatableEntry::make('customerDocuments')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('file_path')
                                ->label('Document')
                                ->formatStateUsing(fn() => "Download Document")
                                ->url(fn($record) => Storage::url($record->file_path), true)
                                ->badge()
                                ->color(Color::Blue),
                            TextEntry::make('comments'),
                        ])
                        ->columns()
                ]),
            Section::make('Pipeline Stage History and Notes')
                ->schema([
                    ViewEntry::make('pipelineStageLogs')
                        ->label('')
                        ->view('infolists.components.pipeline-stage-history-list')
                ])
                ->collapsible()
        ]);
}

// ...
```

With this addition, we used the Collections map method to create a new array from our Custom Fields list. This allows us to display any number of Custom Fields without hardcoding them. We will also hide the section if our Customer has no custom fields. Here's what the View looks like:

![](https://laraveldaily.com/uploads/2023/10/customFieldsView.png)

That's it for this lesson! We now have a fully working Custom Fields system that allows us to add any number of fields to our Customers.

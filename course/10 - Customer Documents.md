Our Customer needs some documents to be uploaded. These can range from simple information sheets to signed PDF documents. We need to be able to upload them and add notes to them, just like this:

![](https://laraveldaily.com/uploads/2023/10/customerDocumentsFormExample2.png)

In this lesson, we will do the following:

- Create Documents database table, model
- Add Documents to the Customer form - only for **edit page**
    - As a bonus, we will clean up the form a bit
- Add Documents to the Customer view page as downloadable links

---

## Creating Database Table and Model

Our Documents will have the following fields:

- `id`
- `customer_id`
- `file_path`
- `comments` (nullable text)

Let's start with the migration:

**Migration**
```php
use App\Models\Customer;

// ...

Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(Customer::class)->constrained();
    $table->string('file_path');
    $table->text('comments')->nullable();
    $table->timestamps();
});
```

Then, we will create the model:

**app/Models/Document.php**
```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Storage;

class Document extends Model
{
    protected $fillable = [
        'customer_id',
        'file_path',
        'comments'
    ];

    protected static function booted(): void
    {
        self::deleting(function (Document $customerDocument) {
            Storage::disk('public')->delete($customerDocument->file_path);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

**Quick note:** Look at our `deleting` observer - we delete the file from the storage when the document is deleted. This is an excellent practice to follow.

And lastly, we need to tie our Customer model to the Document model:

```php
// ...

public function customerDocuments(): HasMany
{
    return $this->hasMany(Document::class);
}
```

---

## Adding Documents to the Customer Form

Adding Documents to the Customer form is quite easy:

**app/Filament/Resources/CustomerResource.php**
```php
// ...
public static function form(Form $form): Form
{
    return $form
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
            Forms\Components\Select::make('lead_source_id')
                ->relationship('leadSource', 'name'),
            Forms\Components\Select::make('tags')
                ->relationship('tags', 'name')
                ->multiple(),
            Forms\Components\Select::make('pipeline_stage_id')
                ->relationship('pipelineStage', 'name', function ($query) {
                    $query->orderBy('position', 'asc');
                })
                ->default(PipelineStage::where('is_default', true)->first()?->id),
            Forms\Components\Section::make('Documents')// [tl! add:start]
                // This will make the section visible only on the edit page
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Repeater::make('documents')
                        ->relationship('documents')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->addActionLabel('Add Document')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->required(),
                            Forms\Components\Textarea::make('comments'),
                        ])
                        ->columns()
                ])// [tl! add:end]
        ]);
}

// ...
```

Adding this Section with Repeater quickly gives us the following:

![](https://laraveldaily.com/uploads/2023/10/customerDocumentsFormExample.png)

But we can all agree that this form needs a bit of a face-lift, so let's do that:
**app/Filament/Resources/CustomerResource.php**
```php
// ...
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('first_name')// [tl! remove:start]
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
            Forms\Components\Select::make('lead_source_id')
                ->relationship('leadSource', 'name'),
            Forms\Components\Select::make('tags')
                ->relationship('tags', 'name')
                ->multiple(),
            Forms\Components\Select::make('pipeline_stage_id')
                ->relationship('pipelineStage', 'name', function ($query) {
                    $query->orderBy('position', 'asc');
                })
                ->default(PipelineStage::where('is_default', true)->first()?->id),// [tl! remove:end]
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
                // This will make the section visible only on the edit page
                ->visibleOn('edit')
                ->schema([
                    Forms\Components\Repeater::make('documents')
                        ->relationship('documents')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->addActionLabel('Add Document')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->required(),
                            Forms\Components\Textarea::make('comments'),
                        ])
                        ->columns()
                ])
        ]);
}

// ...
```

This face-lift moved the fields around and added cleaner sections for better separation. The result is the following:

![](https://laraveldaily.com/uploads/2023/10/customerDocumentsFormExample2.png)

Which is much cleaner and easier to use.

---

## Adding Documents to the Customer View Page

Last, we need a place to view the Documents. Users could go into editing, but that is not very convenient. Let's add a new tab to the Customer view page:

**app/Filament/Resources/CustomerResource.php**
```php
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

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
            Section::make('Documents')// [tl! add:start]
                // This will hide the section if there are no documents
                ->hidden(fn($record) => $record->documents->isEmpty())
                ->schema([
                    RepeatableEntry::make('documents')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('file_path')
                                ->label('Document')
                                // This will rename the column to "Download Document" (otherwise, it's just the file name)
                                ->formatStateUsing(fn() => "Download Document")
                                // URL to be used for the download (link), and the second parameter is for the new tab
                                ->url(fn($record) => Storage::url($record->file_path), true)
                                // This will make the link look like a "badge" (blue)
                                ->badge()
                                ->color(Color::Blue),
                            TextEntry::make('comments'),
                        ])
                        ->columns()
                ]),// [tl! add:end]
            Section::make('Pipeline Stage History and Notes')
                ->schema([
                    ViewEntry::make('pipelineStageLogs')
                        ->label('')
                        ->view('infolists.components.pipeline-stage-history-list')
                ])
                ->collapsible()
        ]);
}
```

Opening the Customer view page will now show the Documents section (as long as you have a Document uploaded):

![](https://laraveldaily.com/uploads/2023/10/customerDocumentsViewExample.png)

---

In the next lesson, we will add custom field support for our Customers.

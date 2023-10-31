Next, we will dive deeper into a specific CRM use case: Products and Quotes. In this lesson, we will start creating our simple Products and allow users to create Quotes that later we will turn into a PDF. Here's what our Products and Quotes will look like:

![](https://laraveldaily.com/uploads/2023/10/productsList.png)
![](https://laraveldaily.com/uploads/2023/10/quotesListFixed.png)

In this lesson, we will do the following:

- Create a Product Model, Database Table, and CRUD
- Create a Quote Model, Database Table, and CRUD
- Create a complex Quote create/edit form with real-time calculations
- Create an action button on the Customer list to create a Quote
- Modify how our Customer actions look like

---

## Creating the Product Model

Our first task is to create a Product table in the database so that we would have something to sell:

**Migration**
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->integer('price');
    $table->timestamps();
});
```

Next, let's work on the Model:

**app/Models/Product.php**
```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price'];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: static fn($value) => $value / 100,
            set: static fn($value) => $value * 100,
        );
    }
}
```

Lastly, for our Database setup, we need a seeder:

**database/seeders/DatabaseSeeder.php**
```php
use App\Models\Product;

// ...

public function run(): void
{
    // ...
    
    $products = [
        ['name' => 'Product 1', 'price' => 12.99],
        ['name' => 'Product 2', 'price' => 2.99],
        ['name' => 'Product 3', 'price' => 55.99],
        ['name' => 'Product 4', 'price' => 99.99],
        ['name' => 'Product 5', 'price' => 1.99],
        ['name' => 'Product 6', 'price' => 12.99],
        ['name' => 'Product 7', 'price' => 15.99],
        ['name' => 'Product 8', 'price' => 29.99],
        ['name' => 'Product 9', 'price' => 33.99],
        ['name' => 'Product 10', 'price' => 62.99],
        ['name' => 'Product 11', 'price' => 42.99],
        ['name' => 'Product 12', 'price' => 112.99],
        ['name' => 'Product 13', 'price' => 602.99],
        ['name' => 'Product 14', 'price' => 129.99],
        ['name' => 'Product 15', 'price' => 1200.99],
    ];
    
    foreach ($products as $product) {
        Product::create($product);
    }
}
```

Then running `php artisan migrate:fresh --seed` will give us a simple set of products to test the system.

---

## Creating Product Resource

Next, we want to manage the products using Filament, so let's create a new resource:

```bash
php artisan make:filament-resource Product --generate
```

This will generate all of our Resource files. This time, we don't have to customize anything on them:

![](https://laraveldaily.com/uploads/2023/10/productsList.png)

---

## Creating the Quote Model

Next, we will work on our Quote database table and model. First, let's create the migration:

**Migration**
```php
use App\Models\Customer;

// ...

Schema::create('quotes', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(Customer::class)->constrained();
    $table->integer('taxes');
    $table->timestamps();
});
```

Next, let's work on the Model:

**app/Models/Quote.php**
```php

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = ['customer_id', 'taxes'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
```

As you can see, we are still missing the connection to our Products, so let's add that:

**Migration**
```php
use App\Models\Product;
use App\Models\Quote;

// ...

Schema::create('product_quote', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(Quote::class)->constrained();
    $table->foreignIdFor(Product::class)->constrained();
    $table->unsignedInteger('quantity');
    $table->integer('price');
    $table->timestamps();
});
```

This way, we created a pivot table with the `quantity` and `price` columns. Next, let's add the relationship to our Quote model:

**app/Models/Quote.php**
```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// ...

public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class)->withPivot(['quantity', 'price']);
}
```

But this is not enough for Filament, as it needs a pivot model to work correctly, so let's create that too:

**app/Models/ProductQuote.php**
```php
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductQuote extends Pivot
{
    public $incrementing = true;
    public $timestamps = false;

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100,
        );
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

Then we can finish our Quote Model:

**app/Models/Quote.php**
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// ...


public function quoteProducts(): HasMany
{
    return $this->hasMany(ProductQuote::class);
}

protected function total(): Attribute
{
    return Attribute::make(
        get: function () {
            $total = 0;

            foreach ($this->quoteProducts as $product) {
                $total += $product->price * $product->quantity;
            }

            return $total * (1 + (is_numeric($this->taxes) ? $this->taxes : 0) / 100);
        }
    );
}

protected function subtotal(): Attribute
{
    return Attribute::make(
        get: function () {
            $subtotal = 0;

            foreach ($this->quoteProducts as $product) {
                $subtotal += $product->price * $product->quantity;
            }

            return $subtotal;
        }
    );
}
```

As you can see, we have added another relationship - `quoteProducts`. It will be used inside the Filament to create many-to-many records. As for our `total()` and `subtotal()` functions - we will use them to calculate the total and subtotal of the Quote in real time using Laravel's Attribute Casting.

---

## Creating Quote Resource

Next, we want to manage the Quotes using Filament, so let's create a new resource:

```bash
php artisan make:filament-resource Quote --generate
```

This generated our resource, and visiting it - we can see that once again, we will have to make significant modifications:

![](https://laraveldaily.com/uploads/2023/10/quotesDefaultForm.png)

So let's do that and create a modified form that will allow us to create a Quote with Products:

**app/Filament/Resources/QuoteResource.php**
```php
use App\Models\Customer 
use Filament\Forms\Components\Section   
use Filament\Forms\Get  
use Filament\Forms\Set  
use App\Models\Product  
use Filament\Forms\Components\Actions\Action    

// ...

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('customer_id')
                ->searchable()
                ->relationship('customer')
                ->getOptionLabelFromRecordUsing(fn(Customer $record) => $record->first_name . ' ' . $record->last_name)
                ->searchable(['first_name', 'last_name'])
                ->default(request()->has('customer_id') ? request()->get('customer_id') : null)
                ->required(),
            Section::make()
                ->columns(1)
                ->schema([
                    Forms\Components\Repeater::make('quoteProducts')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->relationship('product', 'name')
                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                    return collect($get('../*.product_id'))
                                        ->reject(fn($id) => $id == $state)
                                        ->filter()
                                        ->contains($value);
                                })
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, $livewire) {
                                    $set('price', Product::find($get('product_id'))->price);
                                    self::updateTotals($get, $livewire);
                                })
                                ->required(),
                            Forms\Components\TextInput::make('price')
                                ->required()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function (Get $get, $livewire) {
                                    self::updateTotals($get, $livewire);
                                })
                                ->prefix('$'),
                            Forms\Components\TextInput::make('quantity')
                                ->integer()
                                ->default(1)
                                ->required()
                                ->live()
                        ])
                        ->live()
                        ->afterStateUpdated(function (Get $get, $livewire) {
                            self::updateTotals($get, $livewire);
                        })
                        ->afterStateHydrated(function (Get $get, $livewire) {
                            self::updateTotals($get, $livewire);
                        })
                        ->deleteAction(
                            fn(Action $action) => $action->after(fn(Get $get, $livewire) => self::updateTotals($get, $livewire)),
                        )
                        ->reorderable(false)
                        ->columns(3)
                ]),
            Section::make()
                ->columns(1)
                ->maxWidth('1/2')
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->numeric()
                        ->readOnly()
                        ->prefix('$')
                        ->afterStateUpdated(function (Get $get, $livewire) {
                            self::updateTotals($get, $livewire);
                        }),
                    Forms\Components\TextInput::make('taxes')
                        ->suffix('%')
                        ->required()
                        ->numeric()
                        ->default(20)
                        ->live(true)
                        ->afterStateUpdated(function (Get $get, $livewire) {
                            self::updateTotals($get, $livewire);
                        }),
                    Forms\Components\TextInput::make('total')
                        ->numeric()
                        ->readOnly()
                        ->prefix('$')
                ])
        ]);
}

public static function updateTotals(Get $get, $livewire): void
{
    // Retrieve the state path of the form. Most likely, it's `data` but could be something else.
    $statePath = $livewire->getFormStatePath();

    $products = data_get($livewire, $statePath . '.quoteProducts');
    if (collect($products)->isEmpty()) {
        return;
    }
    $selectedProducts = collect($products)->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

    $prices = collect($products)->pluck('price', 'product_id');

    $subtotal = $selectedProducts->reduce(function ($subtotal, $product) use ($prices) {
        return $subtotal + ($prices[$product['product_id']] * $product['quantity']);
    }, 0);

    data_set($livewire, $statePath . '.subtotal', number_format($subtotal, 2, '.', ''));
    data_set($livewire, $statePath . '.total', number_format($subtotal + ($subtotal * (data_get($livewire, $statePath . '.taxes') / 100)), 2, '.', ''));
}

// ...
```

While this code seems really complex, it's actually just doing the following:

- Adds a Customer select field with a search
- Adds a Repeater field for our Quote Products
  - This field allows us to add multiple products to the Quote
  - Each of the Products has price (changeable) and quantity (changeable)
  - You can add/remove products as needed
- Adds a Subtotal, Taxes, and Total fields
  - Subtotal is calculated by adding all the products together (price * quantity)
  - Taxes is a percentage that is added to the subtotal
  - Total is the subtotal + Taxes
  - All of these fields are reactive and calculated in real-time with the `updateTotals` function

This is what our form looks like:

![](https://laraveldaily.com/uploads/2023/10/quotesForm.png)

Once we create a Quote - we can see that there's an ugly List page being loaded:

![](https://laraveldaily.com/uploads/2023/10/quotesList.png)

Let's fix that to display the correct information:

**app/Filament/Resources/QuoteResource.php**
```php
// ...

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('customer.first_name')
                ->formatStateUsing(function ($record) {
                    return $record->customer->first_name . ' ' . $record->customer->last_name;
                })
                ->searchable(['first_name', 'last_name'])
                ->sortable(),
            Tables\Columns\TextColumn::make('taxes')
                ->numeric()
                ->suffix('%')
                ->sortable(),
            Tables\Columns\TextColumn::make('subtotal')
                ->numeric()
                ->money()
                ->sortable(),
            Tables\Columns\TextColumn::make('total')
                ->numeric()
                ->money()
                ->sortable(),
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
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}

// ...
```

Now, loading the same page - we can see that it looks much better:

![](https://laraveldaily.com/uploads/2023/10/quotesListFixed.png)

---

## Create Quotes From Customer Table

To make life easier for our users, we want to add a button to the Customer table to allow us to create a Quote for that Customer. Let's do this:

**app/Filament/Resources/CustomerResource.php**
```php
use App\Filament\Resources\QuoteResource\Pages\CreateQuote;

// ...

public static function table(Table $table): Table
{
    return $table
        // ...
        ->actions([
            // ...
           Tables\Actions\Action::make('Create Quote')
                  ->icon('heroicon-m-book-open')
                  ->url(function ($record) {
                      return CreateQuote::getUrl(['customer_id' => $record->id]);
                  })
        ])
        // ...
}
```

This indeed added our Creation Quote action that links to our Quote creation page, but it made our Customer table look a bit ugly:

![](https://laraveldaily.com/uploads/2023/10/customerListTooManyActions.png)

Let's fix that by adding a dropdown menu for all the actions:

**app/Filament/Resources/CustomerResource.php**
```php
use App\Filament\Resources\QuoteResource\Pages\CreateQuote;

// ...

public static function table(Table $table): Table
{
    return $table
        // ...
        ->actions([
            Tables\Actions\ActionGroup::make([
                // ...
            ])
        ])
        // ...
}
```

Once we surrounded our Actions with an `ActionGroup` we can see that it looks much better:

![](https://laraveldaily.com/uploads/2023/10/customerListActionsGrouped.png)

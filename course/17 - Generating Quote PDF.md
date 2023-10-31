Now that we have our Quotes being created, we should have the ability to generate a PDF and send it to our customer:

![](https://laraveldaily.com/uploads/2023/10/pdfViewExample.png)

In this lesson, we will do the following:

- Create a view page for our Quote - this will be a page to preview PDF
- Install and modify a PDF generation package
- Create a controller to generate the PDF (both preview and download)

---

## Creating a Simple View Page for Quote

We can quickly create a new View page by running the following command:

```bash
php artisan make:filament-page ViewQuote --resource=QuoteResource --type=ViewRecord
```

This will generate a file, but we still need to register it in our Resource:

**app/Filament/Resources/QuoteResource.php**
```php
// ...

public static function table(Table $table): Table
    {
        return $table
            // ...
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);// [tl! --]
            ])// [tl! add:start]
            ->recordUrl(function ($record) {
                return Pages\ViewQuote::getUrl([$record]);
            });// [tl! add:end]
    }

public static function getPages(): array
{
    return [
        'index' => Pages\ListQuotes::route('/'),
        'create' => Pages\CreateQuote::route('/create'),
        'view' => Pages\ViewQuote::route('/{record}'),// [tl! ++]
        'edit' => Pages\EditQuote::route('/{record}/edit'),
    ];
}
```

Now we can click on the row, and we should see our new page:

![](https://laraveldaily.com/uploads/2023/10/quoteDefaultViewPage.png)

That's it for now. Next, we will generate the PDF and display it on the View page.

---

## Installing PDF Package

Next, we will install a package [Laravel Invoices](https://github.com/LaravelDaily/laravel-invoices) to deal with the invoice generation itself:

**Note:** This can be replaced with any other package or just pure DomPDF

```bash
composer require laraveldaily/laravel-invoices:^3.0
```

Then, we need to publish the package files:

```bash
php artisan invoices:install
```

This will create quite a few files, but we will only modify a few. First, let's open a config file and change our currency format:

**config/invoices.php**
```php
// ...

'currency' => [
    'code' => 'eur',//[tl! --]
    'code' => 'usd',//[tl! ++]

    /*
     * Usually cents
     * Used when spelling out the amount and if your currency has decimals.
     *
     * Example: Amount in words: Eight hundred fifty thousand sixty-eight EUR and fifteen ct.
     */
    'fraction' => 'ct.',
    'symbol'   => '€',//[tl! --]
    'symbol'   => '$',//[tl! --]

    /*
     * Example: 19.00
     */
    'decimals' => 2,

    /*
     * Example: 1.99
     */
    'decimal_point' => '.',

    /*
     * By default empty.
     * Example: 1,999.00
     */
    'thousands_separator' => '',

    /*
     * Supported tags {VALUE}, {SYMBOL}, {CODE}
     * Example: 1.99 €
     */
    'format' => '{VALUE} {SYMBOL}',//[tl! --]
    'format' => '{SYMBOL}{VALUE}',//[tl! ++]
],

'seller' => [
     // ...
    'attributes' => [
        // ...
        'custom_fields' => [
            /*
             * Custom attributes for Seller::class
             *
             * Used to display additional info on Seller section in invoice
             * attribute => value
             */
            'SWIFT' => 'BANK101',// [tl! --]
        ],
    ],
],
```

Then we can open the English translation file and change the `Invoice` to `Quote`:

**resources/lang/vendor/invoices/en/invoice.php**
```php
// ...

'invoice'                => 'Invoice',// [tl! --]    
'invoice'                => 'Quote',// [tl! ++]
'serial'                 => 'Serial No.',
'date'                   => 'Invoice date',// [tl! --]
'date'                   => 'Quote date',// [tl! ++]

// ...
```

---

## Generating PDF

Now that our settings are in place and we have the package, we can work on the invoice generation. First, we need to create a new controller:

```bash
php artisan make:controller QuotePdfController
```

Then, we can open the Controller and add the following code:

**app/Http/Controllers/QuotePdfController.php**
```php

use App\Models\Quote;
use Illuminate\Http\Request;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;

class QuotePdfController extends Controller
{
    public function __invoke(Request $request, Quote $quote)
    {
        $quote->load(['quoteProducts.product', 'customer']);

        $customer = new Buyer([
            'name' => $quote->customer->first_name . ' ' . $quote->customer->last_name,
            'custom_fields' => [
                'email' => $quote->customer->email,
            ],
        ]);

        $items = [];

        foreach ($quote->quoteProducts as $product) {
            $items[] = (new InvoiceItem())
                ->title($product->product->name)
                ->pricePerUnit($product->price)
                ->subTotalPrice($product->price * $product->quantity)
                ->quantity($product->quantity);
        }

        $invoice = Invoice::make()
            ->sequence($quote->id)
            ->buyer($customer)
            ->taxRate($quote->taxes)
            ->totalAmount($quote->total)
            ->addItems($items);

        if ($request->has('preview')) {
            return $invoice->stream();
        }

        return $invoice->download();
    }
}
```

Here's what our code does:

- It loads relationships (to prevent N+1 issue!)
- Forms a buyer with Customer details
- Forms an array of items (products) with the `InvoiceItem` class
- Creates an Invoice with all the data
- If the `preview` parameter is present, it streams the PDF
- Otherwise, it downloads the PDF

Now we can register our new route:

**routes/web.php**
```php
use App\Http\Controllers\QuotePdfController;

// ...

Route::middleware('signed')
    ->get('quotes/{quote}/pdf', QuotePdfController::class)
    ->name('quotes.pdf');
```

---

## Displaying PDF in View Page

Last on our List is the PDF display on the view page and the ability to download it. To do that, we need to add an InfoList:

**app/Filament/Resources/QuoteResource.php**
```php
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;

// ...

public static function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            ViewEntry::make('invoice')
                ->columnSpanFull()
                ->viewData([
                    'record' => $infolist->record
                ])
                ->view('infolists.components.quote-invoice-view')
        ]);
}

// ...
```

We simply added a ViewEntry with a custom view. Now we can create the view:

**resources/views/infolists/components/quote-invoice-view.blade.php**
```blade
<iframe src="{{ URL::signedRoute('quotes.pdf', [$record->id, 'preview' => true]) }}" style="min-height: 100svh;" class="w-full">

</iframe>
```

Here, we created an iframe with a signed URL to our PDF controller. Now, visiting the View page, we should see a PDF:

![](https://laraveldaily.com/uploads/2023/10/quotePdfView.png)

---

## Few Small Adjustments

There are still a couple of minor things we should do:

1. Add a button to download the PDF on our View page
2. Remove `Please pay until:` from our Quote as it's not needed

Let's start with the button. We can add it to our View page:

**app/Filament/Resources/QuoteResource/Pages/ViewQuote.php**
```php
use Filament\Actions\Action;
use URL;

// ...

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
```

Now, loading the page, we should see a new button:

![](https://laraveldaily.com/uploads/2023/10/quotePdfDownloadButton.png)

Next, we can modify our Invoice template that was published to comment out the `pay until` text:

**resources/views/vendor/invoices/templates/default.blade.php**
```blade
{{-- ... --}}

<p>{{-- [tl! remove:start] --}}
    {{ trans('invoices::invoice.pay_until') }}: {{ $invoice->getPayUntilDate() }}
</p>{{-- [tl! remove:end] --}}

{{-- ... --}}
```

Now we can reload the page, and we should see the text removed:

![](https://laraveldaily.com/uploads/2023/10/quotePdfView2.png)

That's it! Now, our users can create quotes for their Customers and send them out as PDFs.

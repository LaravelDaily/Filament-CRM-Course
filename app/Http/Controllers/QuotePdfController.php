<?php

namespace App\Http\Controllers;

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
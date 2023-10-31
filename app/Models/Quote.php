<?php

namespace App\Models;

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

    public function quoteProducts(): HasMany
    {
        return $this->hasMany(ProductQuote::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot(['quantity', 'price']);
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
}
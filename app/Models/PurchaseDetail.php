<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException(
                'Detail Purchase tidak boleh dihapus karena berhubungan dengan histori stok.'
            );
        });
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
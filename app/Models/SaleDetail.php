<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'purchase_price',
        'selling_price',
        'subtotal',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException(
                'Detail Sale tidak boleh dihapus karena berhubungan dengan histori stok.'
            );
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no',
        'user_id',
        'customer_id',
        'subtotal',
        'discount',
        'grand_total',
        'payment_method',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException(
                'Sale tidak boleh dihapus karena transaksi sudah memengaruhi stok.'
            );
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'invoice_no',
        'user_id',
        'supplier_id',
        'grand_total',
    ];

    protected $casts = [
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException(
                'Purchase tidak boleh dihapus karena transaksi sudah memengaruhi stok.'
            );
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'system_stock',
        'physical_stock',
        'adjustment',
        'reason',
    ];

    protected $casts = [
        'system_stock' => 'decimal:3',
        'physical_stock' => 'decimal:3',
        'adjustment' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (
            StockAdjustment $adjustment
        ) {
            $systemStock = round(
                (float) $adjustment->system_stock,
                3
            );

            $physicalStock = round(
                (float) $adjustment->physical_stock,
                3
            );

            $expectedAdjustment = round(
                $physicalStock - $systemStock,
                3
            );

            $actualAdjustment = round(
                (float) $adjustment->adjustment,
                3
            );

            if ($systemStock < 0) {
                throw new \LogicException(
                    'System stock tidak boleh negatif.'
                );
            }

            if ($physicalStock < 0) {
                throw new \LogicException(
                    'Physical stock tidak boleh negatif.'
                );
            }

            if (
                abs(
                    $actualAdjustment -
                    $expectedAdjustment
                ) > 0.0005
            ) {
                throw new \LogicException(
                    'Nilai adjustment tidak sesuai dengan system_stock dan physical_stock.'
                );
            }
        });

        static::updating(function () {
            throw new \LogicException(
                'Stock Adjustment tidak boleh diubah. Buat adjustment baru jika terjadi koreksi.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'Stock Adjustment tidak boleh dihapus karena merupakan histori stok.'
            );
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
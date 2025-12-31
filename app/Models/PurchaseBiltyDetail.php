<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseBiltyDetail extends Model
{
    use HasFactory;

    protected $table = 'purchase_bilty_details';

    protected $fillable = [
        'bilty_id',
        'item_id',
        'quantity',
        'unit',
        'remarks',
    ];

    // Relationship with PurchaseBilty
    public function bilty()
    {
        return $this->belongsTo(PurchaseBilty::class, 'bilty_id');
    }

    // Relationship with Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'item_id');
    }

    // Relationship with Measurement Unit
    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'unit');
    }
}

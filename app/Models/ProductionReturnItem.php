<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionReturnItem extends Model
{
    protected $fillable = ['production_return_id', 'product_id', 'variation_id', 'production_id' , 'unit_id', 'quantity', 'price'];

    public function productionReturn()
    {
        return $this->belongsTo(ProductionReturn::class, 'production_return_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function unit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'unit_id');
    }
}

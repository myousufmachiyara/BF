<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketRate extends Model
{
    protected $fillable = [
        'category_id', 'subcategory_id', 'shape_id', 'size_id', 'rate'
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    public function shape()
    {
        return $this->belongsTo(AttributeValue::class, 'shape_id');
    }

    public function size()
    {
        return $this->belongsTo(AttributeValue::class, 'size_id');
    }
}

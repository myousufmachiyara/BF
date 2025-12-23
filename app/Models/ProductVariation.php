<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'manufacturing_cost',
        'selling_price',
        'stock_quantity',
    ];

    // In ProductVariation.php
    protected static function booted()
    {
        static::creating(function ($variation) {
            if (empty($variation->barcode)) {
                $product = $variation->product;
                $prefix = strtoupper($product->item_type) . '-VAR-';

                $variation->barcode = generateGlobalBarcode($prefix);
            }
        });
    }

    /* ----------------- Relationships ----------------- */

    // Belongs to main product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Belongs to many attribute values (e.g. color, size)
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variation_attribute_values')
                    ->withTimestamps();
    }

    // Pivot model for extra handling (if needed)
    public function values()
    {
        return $this->hasMany(ProductVariationAttributeValue::class);
    }

    // Production Receivings
    public function receivings()
    {
        return $this->hasMany(ProductionReceivingDetail::class, 'variation_id');
    }

    public function usedAsPart()
    {
        return $this->hasMany(ProductPart::class, 'part_variation_id');
    }

}

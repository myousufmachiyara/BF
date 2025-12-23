<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name',
        'sku',
        'barcode',
        'description',
        'manufacturing_cost',
        'opening_stock',
        'selling_price',
        'consumption',
        'reorder_level',
        'max_stock_level',
        'minimum_order_qty',
        'measurement_unit',
        'item_type',    // fg, raw, service
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->barcode)) {
                $prefix = match($product->item_type) {
                    'fg'      => 'FG-',
                    'raw'     => 'RAW-',
                    'service' => 'SRV-',
                    default   => 'PRD-',
                };

                $product->barcode = generateGlobalBarcode($prefix);
            }
        });
    }


    /* ----------------- Relationships ----------------- */

    // Belongs to category
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    // Has many variations
    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    // Has many images
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    // Belongs to measurement unit
    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit');
    }

    public function purchaseInvoices() 
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'item_id');
    }

    public function parts()
    {
        return $this->hasMany(ProductPart::class, 'product_id');
    }

    public function usedInProducts()
    {
        return $this->hasMany(ProductPart::class, 'part_id');
    }

}

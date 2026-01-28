<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'opening_stock',
        'selling_price',
        'purchase_price',
        'bilty_charges',
        'reorder_level',
        'max_stock_level',
        'minimum_order_qty',
        'measurement_unit',
        'is_active',
    ];


    /* ----------------- Relationships ----------------- */

    // Belongs to category
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
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

    public function saleInvoices() 
    {
        return $this->hasMany(SaleInvoiceItem::class, 'product_id');
    }

    public function saleInvoiceParts() 
    {
        return $this->hasMany(SaleItemCustomization::class, 'item_id');
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

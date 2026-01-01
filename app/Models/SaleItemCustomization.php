<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItemCustomization extends Model
{
    protected $table = 'sale_item_customization';

    protected $fillable = [
        'sale_invoice_id',
        'sale_invoice_items_id',
        'item_id',
    ];

    public function saleInvoice()
    {
        return $this->belongsTo(SaleInvoice::class, 'sale_invoice_id');
    }

    public function saleInvoiceItem()
    {
        return $this->belongsTo(
            SaleInvoiceItem::class,
            'sale_invoice_items_id'
        );
    }

    public function item()
    {
        return $this->belongsTo(Product::class, 'item_id');
    }
}


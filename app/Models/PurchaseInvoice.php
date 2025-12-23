<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'invoice_date',
        'payment_terms',
        'bill_no',
        'ref_no',
        'remarks',
        'convance_charges',
        'labour_charges',
        'bill_discount',
        'created_by'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class,'purchase_invoice_id');
    }

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseInvoiceAttachment::class);
    }
}


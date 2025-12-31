<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseBilty extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_bilty';

    protected $fillable = [
        'purchase_id',
        'vendor_id',
        'bilty_date',
        'ref_no',
        'remarks',
        'bilty_amount',
        'created_by',
    ];

    protected $dates = [
        'bilty_date',
    ];

    // Relationship with Purchase Invoice
    public function purchase()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_id');
    }

    // Relationship with Vendor (Chart of Accounts)
    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    // Relationship with User (created_by)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship with Bilty Details
    public function details()
    {
        return $this->hasMany(PurchaseBiltyDetail::class, 'bilty_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionReceiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'production_id',
        'vendor_id',
        'rec_date',
        'grn_no',
        'convance_charges',
        'bill_discount',
        'received_by',
    ];

    public function details()
    {
        return $this->hasMany(ProductionReceivingDetail::class);
    }

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }
}

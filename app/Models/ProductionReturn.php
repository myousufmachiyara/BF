<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionReturn extends Model
{
    use SoftDeletes;

    protected $fillable = ['vendor_id', 'return_date', 'remarks', 'created_by'];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function items()
    {
        return $this->hasMany(ProductionReturnItem::class);
    }
}

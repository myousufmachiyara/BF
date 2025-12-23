<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code'];

    public function stockTransfersFrom()
    {
        return $this->hasMany(StockTransfer::class, 'from_location_id');
    }

    public function stockTransfersTo()
    {
        return $this->hasMany(StockTransfer::class, 'to_location_id');
    }
}

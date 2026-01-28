<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostDatedCheque extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cheque_number', 'cheque_date', 'amount', 'bank_name', 
        'coa_id', 'status', 'deposited_at', 'cleared_at', 'remarks', 'created_by'
    ];

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    protected $casts = [
        'cheque_date' => 'date',
        'deposited_at' => 'date',
        'cleared_at' => 'date',
    ];

    // Helper to check if the cheque is due for deposit
    public function scopeDue($query)
    {
        return $query->where('cheque_date', '<=', now())->where('status', 'received');
    }
}

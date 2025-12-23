<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeSequence extends Model
{
    protected $table = 'barcode_sequences';

    protected $fillable = [
        'prefix',
        'next_number',
    ];

    // Disable timestamps if your table does not have created_at / updated_at
    public $timestamps = false;
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class POSController extends Controller
{
    public function index(){
        $items = Product::orderBy('name', 'asc')->where('item_type','fg')->get();

        return view('sales.pos',compact('items'));
    }
}

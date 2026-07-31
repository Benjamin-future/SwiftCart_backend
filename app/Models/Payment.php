<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id','provider','amount','phone','status',
        'merchant_request_id','checkout_request_id',
        'mpesa_receipt','result_code','result_desc','raw_callback',
    ];

    protected $casts = ['raw_callback' => 'array'];

    public function order() { return $this->belongsTo(\App\Models\Order::class); }
}

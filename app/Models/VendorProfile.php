<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'phone',
        'address',
        'status', // Active | Inactive
    ];

    protected $attributes = [
        'status' => 'Active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
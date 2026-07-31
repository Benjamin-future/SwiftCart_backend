<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_email',
        'phone',
        'mpesa_number',
        'address',
        'id_number',
        'kra_pin',
        'description',
        'permit_path',
        'status', // pending | approved | rejected
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $appends = ['permit_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Full public URL to the uploaded permit, so the admin dashboard
     * can link/preview it without building the path itself.
     */
    public function getPermitUrlAttribute()
    {
        return $this->permit_path
            ? asset('storage/' . $this->permit_path)
            : null;
    }
}
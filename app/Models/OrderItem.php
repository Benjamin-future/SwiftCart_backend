<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'vendor_id',    // null = platform/admin-owned product
        'product_name', // snapshot of the product title at purchase time
        'quantity',
        'price',        // unit price at purchase time
        'status',       // pending | processing | shipped | delivered | cancelled
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Always append calculated total & subtotal to JSON responses
     */
    protected $appends = [
        'subtotal',
        'total',
    ];

    /**
     * Relationship: Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relationship: Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship: Vendor (User)
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Accessor for calculated subtotal: (price * quantity)
     */
    public function getSubtotalAttribute()
    {
        return (float) ($this->price * $this->quantity);
    }

    /**
     * Accessor alias for total
     */
    public function getTotalAttribute()
    {
        return $this->getSubtotalAttribute();
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'rider_id',
        'assigned_by',
        'status', // requested | approved | assigned | picked_up | in_transit | delivered | cancelled
        'address',
        'contact_name',
        'phone',
        'notes',
        'scheduled_at',
        'delivered_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /* =========================================================
     |  RELATIONSHIPS
     * ========================================================*/

    /**
     * Get the order associated with the delivery.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the rider assigned to the delivery.
     */
    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the user/admin who assigned the rider.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /* =========================================================
     |  SCOPES & HELPERS
     * ========================================================*/

    /**
     * Scope query to active deliveries (not delivered or cancelled).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled']);
    }

    /**
     * Scope query to pending/requested deliveries.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'requested');
    }

    /**
     * Check if the delivery is completed.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if the delivery is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
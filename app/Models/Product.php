<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'image',
        'category_id',
        'vendor_id', // null = platform/admin-owned product
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The vendor selling this product. Null vendor_id means the
     * platform itself is the seller (an "admin sale").
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /* ----------------- stock helpers ----------------- */

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Reduce stock after a successful order line item. Clamped at 0
     * so concurrent orders can never push it negative. Call this from
     * OrderController::store() (or wherever order items are actually
     * created) once stock needs to start moving on real purchases —
     * it isn't wired up automatically yet since I haven't seen that
     * controller's current code.
     */
    public function decrementStock(int $quantity): void
    {
        $this->stock = max(0, $this->stock - $quantity);
        $this->save();
    }
}

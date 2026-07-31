<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Wallet extends Model
{
    use HasFactory;

    public const DEFAULT_CURRENCY = 'KES';

    protected $fillable = [
        'user_id',
        'currency',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    protected $attributes = [
        'currency' => self::DEFAULT_CURRENCY,
        'balance'  => 0.00,
    ];

    /* -------------------------------- Relationships ------------------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    /* ----------------------------------- Scopes ----------------------------------- */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCurrency($query, string $code)
    {
        return $query->where('currency', strtoupper($code));
    }

    public function scopePositive($query)
    {
        return $query->where('balance', '>', 0);
    }

    /* ---------------------------- Accessors / Helpers ----------------------------- */

    /**
     * Human-readable money, e.g. "KES 1,234.56"
     */
    public function getFormattedBalanceAttribute(): string
    {
        $amount = number_format((float) $this->balance, 2, '.', ',');
        return "{$this->currency} {$amount}";
    }

    /* --------------------------- Money-safe operations ---------------------------- */

    /**
     * Deposit funds into this wallet (atomic).
     */
    public function deposit(float|int $amount): static
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($amount) {
            $this->lockForUpdate()->refresh();
            $this->balance = (float) $this->balance + $amount;
            $this->save();

            return $this->fresh();
        });
    }

    /**
     * Withdraw funds from this wallet (atomic).
     */
    public function withdraw(float|int $amount): static
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdraw amount must be greater than zero.');
        }

        return DB::transaction(function () use ($amount) {
            $this->lockForUpdate()->refresh();

            if ($amount > (float) $this->balance) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $this->balance = (float) $this->balance - $amount;
            $this->save();

            return $this->fresh();
        });
    }

    /**
     * Transfer funds to another wallet (atomic, currency must match).
     */
    public function transferTo(Wallet $destination, float|int $amount): void
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        if (strtoupper($this->currency) !== strtoupper($destination->currency)) {
            throw new InvalidArgumentException('Wallet currencies must match for transfer.');
        }

        DB::transaction(function () use ($destination, $amount) {
            // Order locks by ID to prevent deadlocks on concurrent cross-transfers
            if ($this->id < $destination->id) {
                $this->lockForUpdate()->refresh();
                $destination->lockForUpdate()->refresh();
            } else {
                $destination->lockForUpdate()->refresh();
                $this->lockForUpdate()->refresh();
            }

            if ($amount > (float) $this->balance) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $this->balance = (float) $this->balance - $amount;
            $this->save();

            $destination->balance = (float) $destination->balance + $amount;
            $destination->save();
        });
    }

    /* --------------------------------- Mutators ---------------------------------- */

    protected static function booted(): void
    {
        static::saving(function (Wallet $wallet) {
            if ($wallet->currency) {
                $wallet->currency = strtoupper($wallet->currency);
            }
        });
    }
}
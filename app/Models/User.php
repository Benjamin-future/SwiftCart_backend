<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail; // <-- for email verification
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass-assignable attributes.
     *
     * NOTE: do NOT add email_verified_at to $fillable (we set it in code).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
    ];

    /**
     * Hidden on serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super_admin'    => 'boolean',
        'password'          => 'hashed',
    ];

    /**
     * Append a simple boolean "is_verified" to API responses.
     */
    protected $appends = ['is_verified'];

    /**
     * Model boot hooks for sane defaults & normalization.
     */
    protected static function booted(): void
    {
        // Default role/is_super_admin on create if not provided
        static::creating(function (User $user) {
            if (empty($user->role)) {
                $user->role = 'user';
            }
            if ($user->is_super_admin === null) {
                $user->is_super_admin = false;
            }
            // Normalize email to lowercase
            if (!empty($user->email)) {
                $user->email = strtolower($user->email);
            }
        });

        static::updating(function (User $user) {
            // Keep emails normalized
            if ($user->isDirty('email') && !empty($user->email)) {
                $user->email = strtolower($user->email);
            }
        });
    }

    /* ===========================
     *  Accessors / Helpers
     * =========================== */

    /**
     * Computed boolean for convenience in the frontend.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark the user as verified (admin/manual flow).
     */
    public function markVerified(): void
    {
        if (is_null($this->email_verified_at)) {
            $this->email_verified_at = now();
            $this->save();
        }
    }

    /**
     * Revoke verification (rarely needed, but useful for admin tools).
     */
    public function revokeVerification(): void
    {
        if (!is_null($this->email_verified_at)) {
            $this->email_verified_at = null;
            $this->save();
        }
    }

    /**
     * True if user has admin role.
     */
    public function isAdmin(): bool
    {
        return ($this->role === 'admin');
    }

    /**
     * True if user is the super admin.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /* ===========================
     *  Query Scopes
     * =========================== */

    /**
     * Only admins (includes super admin).
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Non-admins (regular users).
     */
    public function scopeNonAdmins($query)
    {
        return $query->where('role', '!=', 'admin');
    }

    /**
     * Verified users (email_verified_at not null).
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Unverified users.
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    public function vendorRequest()
{
    return $this->hasOne(VendorRequest::class);
}

}

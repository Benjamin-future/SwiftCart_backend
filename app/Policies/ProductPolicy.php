<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * Give admins full access:
     *  - role === 'admin'  OR
     *  - is_admin === true OR
     *  - Sanctum token has 'admin' ability
     */
    public function before(?User $user, string $ability)
    {
        if (
            $user &&
            (
                ($user->role ?? null) === 'admin' ||
                ($user->is_admin ?? false) ||
                (method_exists($user, 'tokenCan') && $user->tokenCan('admin'))
            )
        ) {
            return true; // allow everything
        }

        return null; // fall through to method-specific checks
    }

    // Public viewing (guest allowed)
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    // Non-admins are denied (admins are handled in before())
    public function create(?User $user): bool  { return false; }
    public function update(?User $user, Product $product): bool { return false; }
    public function delete(?User $user, Product $product): bool { return false; }
    public function restore(?User $user, Product $product): bool { return false; }
    public function forceDelete(?User $user, Product $product): bool { return false; }
}

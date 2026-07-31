<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users (admin-only).
     * Returns a consistent, frontend-friendly payload with is_verified.
     */
    public function index()
    {
        $users = User::query()
            ->select('id','name','email','role','is_super_admin','email_verified_at','created_at')
            ->orderBy('id')
            ->get()
            ->map(fn(User $u) => $this->mapUser($u));

        return response()->json(['data' => $users]);
    }

    /**
     * Show a single user (admin or owner).
     */
    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        return response()->json($this->mapUser($user));
    }

    /**
     * Create a new user.
     * - Only SUPER ADMIN may assign role=admin.
     * - Optional "verified" boolean (only superadmin can set it).
     * - By default, new users are NOT verified (email_verified_at = null).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|unique:users,email',
            'password'              => 'required|string|min:6|confirmed',
            'role'                  => 'sometimes|in:admin,user',
            'verified'              => 'sometimes|boolean',
        ]);

        $actor = $request->user();

        // Role: default to 'user'; only superadmin can set 'admin'
        $role = 'user';
        if ($actor && method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin()) {
            if (($validated['role'] ?? null) === 'admin') {
                $role = 'admin';
            }
        }

        // Verification: default unverified; only superadmin may set verified=true on create
        $emailVerifiedAt = null;
        if ($actor && method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin()) {
            if (!empty($validated['verified'])) {
                $emailVerifiedAt = Carbon::now();
            }
        }

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => strtolower($validated['email']),
            'password'          => Hash::make($validated['password']),
            'role'              => $role,
            'email_verified_at' => $emailVerifiedAt,
        ]);

        return response()->json($this->mapUser($user), 201);
    }

    /**
     * Update user info.
     * - Only SUPER ADMIN may change roles.
     * - Normal users can update only themselves (no role changes).
     * - Admins cannot change roles.
     * - Nobody can change the Super Admin's role.
     */
    public function update(Request $request, $id)
    {
        $target = User::find($id);
        if (!$target) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6|confirmed',
            'role'     => 'sometimes|in:admin,user', // honored only by superadmin
        ]);

        $actor = $request->user();

        // Guard: if not admin/superadmin, can only update self (and not role)
        $isSelf = $actor && $actor->id === $target->id;
        $isAdmin = $actor && $actor->role === 'admin';
        $isSuper = $actor && method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin();

        if (!$isSelf && !$isAdmin && !$isSuper) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Role changes restricted to Super Admin only
        $wantsRoleChange = array_key_exists('role', $validated);
        if ($wantsRoleChange) {
            if (!$isSuper) {
                return response()->json(['message' => 'Only the Super Admin can change user roles.'], 403);
            }
            if (method_exists($target, 'isSuperAdmin') && $target->isSuperAdmin()) {
                return response()->json(['message' => 'Cannot change the Super Admin role.'], 403);
            }
        }

        if (isset($validated['name'])) {
            $target->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $target->email = strtolower($validated['email']);
        }
        if (isset($validated['password'])) {
            $target->password = Hash::make($validated['password']);
        }
        if ($wantsRoleChange) {
            $target->role = $validated['role'];
        }

        $target->save();

        return response()->json($this->mapUser($target));
    }

    /**
     * Delete a user (admin or superadmin); cannot delete Super Admin.
     */
    public function destroy(Request $request, $id)
    {
        $target = User::find($id);
        if (!$target) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $actor = $request->user();
        $isAdmin = $actor && $actor->role === 'admin';
        $isSuper = $actor && method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin();

        if (!$isAdmin && !$isSuper) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (method_exists($target, 'isSuperAdmin') && $target->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot delete the Super Admin account.'], 403);
        }

        $target->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Manually mark a user as verified (admin-only in routes).
     * Super Admin targets are protected.
     */
    public function verify(Request $request, User $user)
    {
        // Optional: extra guard beyond 'admin' middleware
        // if (!$request->user()?->role === 'admin' && !$request->user()?->isSuperAdmin()) abort(403);

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot modify Super Admin.'], 403);
        }

        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = Carbon::now();
            $user->save();
        }

        return response()->json([
            'message' => 'User verified',
            'user'    => $this->mapUser($user),
        ]);
    }

    /**
     * Manually revoke verification (admin-only in routes).
     * Super Admin targets are protected.
     */
    public function unverify(Request $request, User $user)
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot modify Super Admin.'], 403);
        }

        if (!is_null($user->email_verified_at)) {
            $user->email_verified_at = null;
            $user->save();
        }

        return response()->json([
            'message' => 'User unverified',
            'user'    => $this->mapUser($user),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Helpers
     * --------------------------------------------------------------------*/

    /**
     * Normalize user payload for frontend tables.
     */
    private function mapUser(User $u): array
    {
        return [
            'id'                => $u->id,
            'name'              => $u->name,
            'email'             => $u->email,
            'role'              => $u->role,
            'is_super_admin'    => (bool) $u->is_super_admin,
            'email_verified_at' => $u->email_verified_at,
            'is_verified'       => !is_null($u->email_verified_at),
            'created_at'        => $u->created_at,
        ];
    }
}

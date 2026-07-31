<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user (force role=user)
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email:rfc,dns|unique:users,email',
            'password'    => 'required|string|min:6|confirmed', // expects password_confirmation
            'device_name' => 'sometimes|string|max:100',
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'role'              => 'user', // safe default
            'email_verified_at' => Carbon::now(),
            'remember_token'    => Str::random(60),
        ]);

        return $this->issueToken(
            $user,
            $validated['device_name'] ?? $this->deviceName($request),
            201,
            'Registration successful'
        );
    }

    /**
     * Login user/admin with email + password
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'       => 'required|email:rfc',
            'password'    => 'required|string',
            'device_name' => 'sometimes|string|max:100',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // 🔧 Normalize legacy rows:
        // - If superadmin but role is null/non-admin, persist role='admin' (so frontend relying on role won't break)
        // - Else if role is null, persist role='user'
        if (is_null($user->role)) {
            $user->role = $this->isSuper($user) ? 'admin' : 'user';
            $user->save();
        }

        return $this->issueToken(
            $user,
            $validated['device_name'] ?? $this->deviceName($request),
            200,
            'Login successful'
        );
    }

    /**
     * Refresh current token (rotate)
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $this->deleteCurrentToken($request);
        return $this->issueToken($user, $this->deviceName($request), 200, 'Token refreshed');
    }

    /**
     * Logout (invalidate the token used for this request)
     */
    public function logout(Request $request)
    {
        $this->deleteCurrentToken($request);
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    /**
     * Logout from all devices (delete all tokens)
     */
    public function logoutAll(Request $request)
    {
        $request->user()?->tokens()?->delete();
        return response()->json(['message' => 'Logged out from all devices'], 200);
    }

    /**
     * Get authenticated user (+ abilities)
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user'        => $this->safeUser($user),     // includes role_effective
            'abilities'   => $this->abilitiesFor($user),
        ], 200);
    }

    /**
     * Alias for frontend that calls /user instead of /me
     */
    public function user(Request $request)
    {
        return $this->me($request);
    }

    /* ----------------- helpers ----------------- */

    private function deleteCurrentToken(Request $request): void
    {
        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
            return;
        }

        if ($raw = $request->bearerToken()) {
            if ($accessToken = PersonalAccessToken::findToken($raw)) {
                $accessToken->delete();
            }
        }
    }

    private function deviceName(Request $request): string
    {
        return substr((string)($request->input('device_name') ?: $request->userAgent() ?: 'web'), 0, 100);
    }

    /** True if user has super flag */
    private function isSuper(User $user): bool
    {
        // If your model casts is_super_admin to bool, this will be accurate
        return (bool)($user->is_super_admin ?? false);
    }

    /**
     * Effective role for UI/routing:
     * - superadmin => 'admin' (so admin-only routes work)
     * - else stored role or 'user' fallback
     */
    private function effectiveRole(User $user): string
    {
        if ($this->isSuper($user)) return 'admin';
        return $user->role ?: 'user';
    }

    /**
     * Map role/flags -> Sanctum abilities
     * - Super Admin: 'superadmin' + all admin abilities
     * - Admin: admin abilities
     * - User: user abilities
     */
    private function abilitiesFor(User $user): array
    {
        if ($this->isSuper($user)) {
            return [
                'superadmin', 'admin',
                'users.read', 'users.write',
                'products.read', 'products.write',
                'orders.read', 'orders.manage',
                'wallets.read', 'wallets.manage',
            ];
        }

        if (($user->role ?? null) === 'admin') {
            return [
                'admin',
                'users.read', 'users.write',
                'products.read', 'products.write',
                'orders.read', 'orders.manage',
                'wallets.read', 'wallets.manage',
            ];
        }

        return [
            'user',
            'profile.read', 'profile.update',
            'orders.create', 'orders.read.own',
            'wallet.read.own', 'wallet.update.own',
        ];
    }

    /**
     * Safe, consistent user payload for the frontend.
     * Guarantees non-null role via role_effective, and includes the raw DB role for reference.
     */
    private function safeUser(User $user): array
    {
        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'role'            => $user->role,                 // may be null historically
            'role_effective'  => $this->effectiveRole($user), // ✅ always 'admin' or 'user'
            'is_super_admin'  => $this->isSuper($user),
            'created_at'      => optional($user->created_at)->toAtomString(),
            'updated_at'      => optional($user->updated_at)->toAtomString(),
        ];
    }

    /**
     * Mint Sanctum token + return consistent payload
     */
    private function issueToken(User $user, string $deviceName, int $status, string $message)
    {
        $abilities = $this->abilitiesFor($user);

        // Uncomment for single-session behavior:
        // $user->tokens()->delete();

        $token = $user->createToken($deviceName ?: 'web', $abilities)->plainTextToken;

        return response()->json([
            'message'      => $message,
            'token'        => $token,
            'token_type'   => 'Bearer',
            'abilities'    => $abilities,
            'user'         => $this->safeUser($user),  // includes role_effective
        ], $status);
    }
}

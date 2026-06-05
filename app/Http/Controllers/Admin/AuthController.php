<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session (Sanctum SPA cookie) auth for the React admin panel.
 * Replaces the Filament login screen.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! ($user->isSuperAdmin() || $user->isAuthor())) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('auth.admin_access_denied') ?: 'You are not allowed to access the admin panel.',
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'success' => true,
            'data' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->isSuperAdmin(),
            'permissions' => [
                'blog_post' => $this->crud($user, 'blog_post'),
                'blog_category' => $this->crud($user, 'blog_category'),
                'blog_tag' => $this->crud($user, 'blog_tag'),
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function crud(User $user, string $entity): array
    {
        $super = $user->isSuperAdmin();

        return [
            'view' => $super || $user->can("view_{$entity}"),
            'create' => $super || $user->can("create_{$entity}"),
            'update' => $super || $user->can("update_{$entity}"),
            'delete' => $super || $user->can("delete_{$entity}"),
        ];
    }
}

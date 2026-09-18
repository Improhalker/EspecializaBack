<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\UpdateInitialPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('username', $credentials['username'])->first();

        if (! $user || ! $user->is_admin || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'As credenciais informadas não são válidas.',
                'errors' => ['username' => ['As credenciais informadas não são válidas.']],
            ], 422);
        }

        $user->tokens()->delete();

        return response()->json([
            'token' => $user->createToken('admin-panel')->plainTextToken,
            'user' => $this->userData($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->userData($user)]);
    }

    public function updateInitialPassword(UpdateInitialPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);

        return response()->json(['user' => $this->userData($user->fresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([], 204);
    }

    /**
     * @return array{id: int, name: string, username: string|null, must_change_password: bool}
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'must_change_password' => $user->must_change_password,
        ];
    }
}

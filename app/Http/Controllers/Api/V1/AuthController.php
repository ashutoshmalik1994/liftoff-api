<?php
namespace App\Http\Controllers\Api\V1;


use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

use App\Http\Controllers\Controller;

class AuthController extends Controller
{
     /**
     * @OA\Post(
     *     path="/api/v1/auth/token",
     *     tags={"Authentication"},
     *     summary="Get authentication token (JWT)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="loreum_user"),
     *             @OA\Property(property="password", type="string", example="loreum_user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
public function login(Request $request)
{
    $credentials = $request->only('username', 'password');

    if (!$token = auth('api')->attempt($credentials)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    $user = auth('api')->user(); // current logged in user

    return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => auth('api')->factory()->getTTL() * 60,
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
        ]
    ]);
}


		public function profile()
		{
			return response()->json(auth()->user());
		}
		/**
		 * @OA\Post(
		 *     path="/api/v1/auth/refresh",
		 *     tags={"Authentication"},
		 *     summary="Refresh an expired JWT token",
		 *     security={{"bearerAuth":{}}},
		 *     @OA\Response(response=200, description="Token refreshed successfully"),
		 *     @OA\Response(response=401, description="Invalid or expired token")
		 * )
		 */
public function refresh(Request $request)
{
    try {
        $token = JWTAuth::getToken();

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        // Check if the current token is still valid
        if (JWTAuth::parseToken()->check()) {
            return response()->json([
                'message' => 'Current token is still valid, no need to refresh yet.'
            ]);
        }

        // If the token is expired or about to expire, refresh it
        $newToken = JWTAuth::parseToken()->refresh();

        return response()->json([
            'token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    } catch (JWTException $e) {
        return response()->json([
            'error' => 'Token cannot be refreshed. Please authorize with a valid token.'
        ], 401);
    }
}


		 


}

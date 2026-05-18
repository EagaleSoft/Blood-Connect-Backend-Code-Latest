<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request, FirebaseService $firebase): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:6',
            'role' => 'required|in:patient_donor,team_volunteer',
        ]);

        $validated['email'] = strtolower(trim($validated['email']));

        try {
            $createdUser = $firebase->auth()->createUser([
                'email' => $validated['email'],
                'password' => $validated['password'],
                'displayName' => $validated['name'],
                'disabled' => false,
            ]);

            $uid = $createdUser->uid;

            try {
                $firebase->firestore()
                    ->collection('users')
                    ->document($uid)
                    ->set([
                        'uid' => $uid,
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'],
                        'role' => $validated['role'],
                        'status' => 'active',
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ]);
            } catch (Throwable $e) {
                try {
                    $firebase->auth()->deleteUser($uid);
                } catch (Throwable $deleteError) {
                    Log::error('Firebase rollback failed.', [
                        'uid' => $uid,
                        'error' => $deleteError->getMessage(),
                    ]);
                }

                Log::error('Firestore user profile save failed.', [
                    'uid' => $uid,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed while saving user profile.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully.',
                'data' => [
                    'uid' => $uid,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'role' => $validated['role'],
                    'status' => 'active',
                ],
            ], 201);

        } catch (Throwable $e) {
            Log::warning('Registration failed.', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            $errorMessage = strtolower($e->getMessage());

            if (str_contains($errorMessage, 'email_exists') || str_contains($errorMessage, 'already exists')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already exists.',
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Registration failed.',
            ], 500);
        }
    }

   public function login(Request $request, FirebaseService $firebase): JsonResponse
{
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    $validated['email'] = strtolower(trim($validated['email']));

    try {
        // Firebase Auth SDK: email/password verify
        $signInResult = $firebase->auth()->signInWithEmailAndPassword(
            $validated['email'],
            $validated['password']
        );

        $uid = $signInResult->firebaseUserId();
        $idToken = $signInResult->idToken();

        // Firestore SDK: get saved user profile and role
        $snapshot = $firebase->firestore()
            ->collection('users')
            ->document($uid)
            ->snapshot();

        if (!$snapshot->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        $userProfile = $snapshot->data();

        if (($userProfile['status'] ?? null) !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $idToken,
            'data' => [
                'uid' => $uid,
                'name' => $userProfile['name'] ?? null,
                'email' => $userProfile['email'] ?? null,
                'phone' => $userProfile['phone'] ?? null,
                'role' => $userProfile['role'] ?? null,
                'status' => $userProfile['status'] ?? null,
            ],
        ]);

    } catch (Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.',
        ], 401);
    }
}
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DonorController extends Controller
{
    public function store(Request $request, FirebaseService $firebase): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:30',
            'blood_group' => 'required|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'current_location' => 'required|string|max:255',
            'is_available_now' => 'required|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization token is required.',
                ], 401);
            }

            // Firebase Auth SDK: verify logged-in user token
            $verifiedToken = $firebase->auth()->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');

            // Firestore SDK: fetch logged-in user profile
            $userSnapshot = $firebase->firestore()
                ->collection('users')
                ->document($uid)
                ->snapshot();

            if (!$userSnapshot->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User profile not found.',
                ], 404);
            }

            $user = $userSnapshot->data();

            if (($user['status'] ?? null) !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active.',
                ], 403);
            }

            if (($user['role'] ?? null) !== 'patient_donor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only patient/donor users can submit donate blood form.',
                ], 403);
            }

            $donorRequestId = (string) Str::uuid();
            $timestamp = now()->toIso8601String();

            $donorRequest = [
                'id' => $donorRequestId,
                'user_id' => $uid,

                'name' => trim($validated['name']),
                'phone' => trim($validated['phone']),
                'blood_group' => $validated['blood_group'],
                'current_location' => trim($validated['current_location']),
                'is_available_now' => (bool) $validated['is_available_now'],
                'message' => $validated['message'] ?? null,

                'user_email' => $user['email'] ?? null,
                'user_role' => $user['role'] ?? null,

                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Firestore SDK: save donor request
            $firebase->firestore()
                ->collection('donor_requests')
                ->document($donorRequestId)
                ->set($donorRequest);

            return response()->json([
                'success' => true,
                'message' => 'Donate blood request submitted successfully.',
                'data' => $donorRequest,
            ], 201);

        } catch (Throwable $e) {
            Log::error('Donate blood request failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit donate blood request.',
            ], 500);
        }
    }
}
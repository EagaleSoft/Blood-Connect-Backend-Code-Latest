<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BloodRequestController extends Controller
{
    public function store(Request $request, FirebaseService $firebase): JsonResponse
    {
        $validated = $request->validate([
            'patient_name' => 'required|string|max:120',
            'location' => 'required|string|max:255',
            'hospital_name' => 'required|string|max:180',
            'blood_group' => 'required|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'blood_constituents' => 'required|array|min:1',
            'blood_constituents.*' => 'required|string|in:Whole Blood,FFP,PCV,PRP',
            'case_description' => 'required|string|max:500',
        ]);

        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization token is required.',
                ], 401);
            }

            // Firebase Auth SDK: verify login token
            $verifiedToken = $firebase->auth()->verifyIdToken($token);
            $uid = $verifiedToken->claims()->get('sub');

            // Firestore SDK: read logged-in user profile
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

            $userProfile = $userSnapshot->data();

            if (($userProfile['status'] ?? null) !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not active.',
                ], 403);
            }

            if (($userProfile['role'] ?? null) !== 'patient_donor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only patient/donor users can send blood request.',
                ], 403);
            }

            $requestId = (string) Str::uuid();

            $bloodRequest = [
                'id' => $requestId,
                'user_id' => $uid,

                'patient_name' => $validated['patient_name'],
                'location' => $validated['location'],
                'hospital_name' => $validated['hospital_name'],
                'blood_group' => $validated['blood_group'],
                'blood_constituents' => $validated['blood_constituents'],
                'case_description' => $validated['case_description'],

                'requested_by_name' => $userProfile['name'] ?? null,
                'requested_by_email' => $userProfile['email'] ?? null,
                'requested_by_phone' => $userProfile['phone'] ?? null,

                'status' => 'pending',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            // Firestore SDK: save request
            $firebase->firestore()
                ->collection('blood_requests')
                ->document($requestId)
                ->set($bloodRequest);

            return response()->json([
                'success' => true,
                'message' => 'Blood request submitted successfully.',
                'data' => $bloodRequest,
            ], 201);

        } catch (Throwable $e) {
            Log::error('Blood request failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit blood request.',
            ], 500);
        }
    }
}
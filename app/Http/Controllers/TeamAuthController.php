<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ClientAuthHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Helpers\TeamAuthHelper;
class TeamAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:teams,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamAuthHelper::login($request);
    }


    public function logout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamAuthHelper::logout($request);
    }



    /** Staff may only read or change their own account through teamAuth. */
    private function ownAccount(Request $request): ?JsonResponse
    {
        $own = $request->attributes->get('team')->team_id;

        if ($request->filled('team_id') && $request->input('team_id') !== $own) {
            return response()->json([
                'success' => false,
                'message' => 'You can only act on your own account.',
            ], 403);
        }

        $request->merge(['team_id' => $own]);

        return null;
    }

    public function reset_password(Request $request): JsonResponse
    {
        if ($denied = $this->ownAccount($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|different:current_password',
            'confirm_password' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamAuthHelper::reset_password($request);
    }

    public function getProfile(Request $request): JsonResponse
    {
        if ($denied = $this->ownAccount($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamAuthHelper::getProfile($request);
    }


    public function updateProfile(Request $request): JsonResponse
    {
        if ($denied = $this->ownAccount($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string',
            'email' => 'required|email',
            'full_name' => 'required|string',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamAuthHelper::updateProfile($request);
    }

}
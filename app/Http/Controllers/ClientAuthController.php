<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ClientAuthHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
class ClientAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:clients,email',
            'account_type' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
            'password' => 'required|string|min:8',
            'confirm_password' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ClientAuthHelper::register($request);
    }



    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:clients,email',
            'password' => 'required|string|min:8',
            'fcm_token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ClientAuthHelper::login($request);
    }


    public function reset_password(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:client_id|nullable|string|email',
            'client_id' => 'required_without:email|nullable|string',
            'password' => 'required|string|min:8',
            'confirm_password' => 'required|string|same:password',
            'business_name' => 'nullable|string',
            'registration_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ClientAuthHelper::reset_password($request);
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

        return ClientAuthHelper::logout($request);
    }





    public function getProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ClientAuthHelper::getProfile($request);
    }
}

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

}
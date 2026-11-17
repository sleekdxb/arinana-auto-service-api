<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\PublicHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    public function submitMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string',
            'interested' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
        return PublicHelper::submitMessage($request);
    }
}

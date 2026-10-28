<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Helpers\ServicesHelper;
class ServicesController extends Controller
{
    public function getServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ServicesHelper::getServices($request);
    }


    public function addServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
            'name' => 'required|string',
            'type' => 'sometimes|nullable|array',
            'type.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ServicesHelper::addServices($request);
    }

    public function updateServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
            'ser_id' => 'required|string',
            'name' => 'required|string',
            'type' => 'sometimes|nullable|array',
            'type.*' => 'string',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ServicesHelper::updateServices($request);
    }


    public function deleteServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
            'ser_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return ServicesHelper::deleteServices($request);
    }


}

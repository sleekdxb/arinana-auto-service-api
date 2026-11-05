<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\NotificationHelper;

class NotificationController extends Controller
{
    public function getNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,client_id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return NotificationHelper::getNotifications($request);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,client_id',
            'notif_ids' => 'nullable|array',
            'notif_ids.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return NotificationHelper::markRead($request);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,client_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return NotificationHelper::getPreferences($request);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,client_id',
            'push_enabled' => 'nullable|boolean',
            'email_enabled' => 'nullable|boolean',
            'sms_enabled' => 'nullable|boolean',
            'booking_updates' => 'nullable|boolean',
            'service_status_updates' => 'nullable|boolean',
            'service_completion' => 'nullable|boolean',
            'document_review' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return NotificationHelper::updatePreferences($request);
    }
}

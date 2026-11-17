<?php

namespace App\Http\Controllers;

use App\Helpers\SettingsHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function getAvailability(Request $request): JsonResponse
    {
        return $this->validated($request, [], fn() => SettingsHelper::getAvailability());
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'operating_days' => 'nullable|array',
            'operating_days.*' => 'integer|between:1,7',
            'opens_at' => 'nullable|date_format:H:i',
            'closes_at' => 'nullable|date_format:H:i',
            'slot_minutes' => 'nullable|integer|in:15,30,45,60,90,120',
            'slot_capacity' => 'nullable|integer|min:1|max:100',
            'lead_time_minutes' => 'nullable|integer|min:0|max:10080',
            'change_cutoff_hours' => 'nullable|integer|min:0|max:168',
        ], fn() => SettingsHelper::updateAvailability($request));
    }

    public function getDocumentRequirements(Request $request): JsonResponse
    {
        return $this->validated($request, [], fn() => SettingsHelper::getDocumentRequirements());
    }

    public function updateDocumentRequirements(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'requirements' => 'present|array',
            'requirements.*.key' => 'nullable|string',
            'requirements.*.name' => 'required|string|max:120',
            'requirements.*.description' => 'nullable|string|max:500',
            'requirements.*.required' => 'nullable|boolean',
            'requirements.*.enabled' => 'nullable|boolean',
            'requirements.*.type' => 'nullable|in:document,image',
            'requirements.*.count' => 'nullable|integer|min:1|max:10',
        ], fn() => SettingsHelper::updateDocumentRequirements($request));
    }

    public function getStatusPipelines(Request $request): JsonResponse
    {
        return $this->validated($request, [], fn() => SettingsHelper::getStatusPipelines());
    }

    public function updateStatusPipelines(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'service' => 'nullable|array',
            'service.*.name' => 'nullable|string|max:60',
            'service.*.label' => 'required|string|max:60',
            'structural_safety' => 'nullable|array',
            'structural_safety.*.name' => 'nullable|string|max:60',
            'structural_safety.*.label' => 'required|string|max:60',
        ], fn() => SettingsHelper::updateStatusPipelines($request));
    }

    public function getNotificationSettings(Request $request): JsonResponse
    {
        return $this->validated($request, [], fn() => SettingsHelper::getNotificationSettings());
    }

    public function updateNotificationSettings(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'events' => 'required|array',
            'events.*.key' => 'required|string',
            'events.*.push' => 'nullable|boolean',
            'events.*.email' => 'nullable|boolean',
            'events.*.sms' => 'nullable|boolean',
        ], fn() => SettingsHelper::updateNotificationSettings($request));
    }

    public function getClientDocumentRequirements(): JsonResponse
    {
        return SettingsHelper::getDocumentRequirements(true);
    }

    public function getClientStatusPipelines(): JsonResponse
    {
        return SettingsHelper::getStatusPipelines();
    }

    /** Every team endpoint needs a real team_id, like the rest of /team. */
    private function validated(Request $request, array $rules, callable $next): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
        ] + $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return $next();
    }
}

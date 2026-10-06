<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Helpers\FileUploadHelper;
use Carbon\Carbon;

class FileUploadController extends Controller
{

    public function uploadFiles(Request $request): JsonResponse
    {
        // Step 0: Parse uploadProtocol if it's a JSON string
        if (is_string($request->input('uploadProtocol'))) {
            try {
                $uploadProtocol = json_decode($request->input('uploadProtocol'), true);

                if (!is_array($uploadProtocol)) {
                    throw new \Exception('Invalid JSON structure.');
                }

                $request->merge([
                    'uploadProtocol' => $uploadProtocol
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid uploadProtocol JSON',
                    'errors' => [
                        'uploadProtocol' => [$e->getMessage()]
                    ],
                ], 422);
            }
        }

        // Step 1: Validate uploadProtocol structure
        $protocolValidator = Validator::make($request->all(), [
            'uploadProtocol' => 'required|array',

            'uploadProtocol.target' => [
                'required',
                'string',
                'in:account,vehicle,booking,account_update,vehicle_update,booking_update'
            ],

            'uploadProtocol.ref_id' => 'required|string',

            'uploadProtocol.upload_at' => 'required|date',

            'uploadProtocol.existing_file_ids' => [
                'required_if:uploadProtocol.target,account_update,vehicle_update,booking_update',
                'array',
            ],

            'uploadProtocol.existing_file_ids.*' => [
                'required_if:uploadProtocol.target,account_update,vehicle_update,booking_update',
                'string',
            ],
        ]);

        if ($protocolValidator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Upload protocol validation failed',
                'errors' => $protocolValidator->errors(),
            ], 422);
        }

        $target = $request->input('uploadProtocol.target');

        // Step 2: Define file validation rules
        $fileRules = [];

        // Vehicle files
        if (in_array($target, ['vehicle', 'vehicle_update'])) {
            $fileRules = [
                'vehicle_img' => 'required_without_all:vehicle_doc|array|min:1',
                'vehicle_doc' => 'required_without_all:vehicle_img|array|min:1',

                'vehicle_img.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:20480',
                'vehicle_doc.*' => 'file|mimes:pdf,doc,docx|max:20480',
            ];
        }

        // Account files
        elseif (in_array($target, ['account', 'account_update'])) {
            $fileRules = [
                'account_img' => 'required_without:account_doc|array|min:1',
                'account_doc' => 'required_without:account_img|array|min:1',

                'account_img.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:20480',
                'account_doc.*' => 'file|mimes:pdf,doc,docx|max:20480',

                'expiry_data' => ['nullable'],
            ];
        }

        // booking files
        elseif (in_array($target, ['booking', 'booking_update'])) {
            $fileRules = [
                'booking_img' => 'required_without:booking_doc|array|min:1',
                'booking_doc' => 'required_without:booking_img|array|min:1',

                'booking_img.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:20480',
                'booking_doc.*' => 'file|mimes:pdf,doc,docx|max:20480',
            ];
        }

        // Step 3: Run file validation
        $fileValidator = Validator::make($request->all(), $fileRules);

        if ($fileValidator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'File validation failed',
                'errors' => $fileValidator->errors(),
            ], 422);
        }

        // Step 4: Normalize uploaded files
        $allFiles = [];

        foreach ($fileRules as $field => $rule) {
            if (
                str_ends_with($field, '.*') ||
                $field === 'expiry_data'
            ) {
                continue;
            }

            $files = $request->file($field);

            if (!$files) {
                continue;
            }

            $files = is_array($files)
                ? $files
                : [$files];

            foreach ($files as $file) {
                $allFiles[] = [
                    'field' => $field,
                    'file' => $file,
                ];
            }
        }

        // Step 5: Process expiry_data (only relevant for account)
        $rawExpiryData = $request->input('expiry_data', []);

        if (is_string($rawExpiryData)) {
            $rawExpiryData = json_decode($rawExpiryData, true) ?: [];
        }

        $expiryData = [];

        if (is_array($rawExpiryData)) {
            foreach ($rawExpiryData as $filename => $dateString) {
                try {
                    $expiryData[$filename] = Carbon::parse($dateString)
                        ->toDateTimeString();
                } catch (\Throwable $e) {
                    $expiryData[$filename] = null;
                }
            }
        } else {
            Log::warning('Invalid expiry_data format', [
                'raw' => $rawExpiryData
            ]);
        }

        // Step 6: Upload files
        $uploadProtocol = $request->input('uploadProtocol');

        $response = FileUploadHelper::uploadFiles(
            $uploadProtocol,
            $allFiles,
            $expiryData
        );

        return $response instanceof JsonResponse
            ? $response
            : response()->json([
                'status' => true,
                'message' => 'Files uploaded successfully',
                'data' => $response,
            ]);
    }

}

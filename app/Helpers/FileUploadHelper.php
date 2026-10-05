<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\ClientFile;
use App\Models\VehicleFile;
use App\Models\ClientFileState;
use App\Models\VehicleFileState;
use App\Models\BookingFile;
use App\Models\BookingFileState;



class FileUploadHelper
{

    public static function uploadFiles(
        array $protocol,
        array $files,
        array $expiryData = []
    ) {
        $target = $protocol['target'];
        $refId = $protocol['ref_id'];
        $uploadedAt = Carbon::parse($protocol['upload_at'])->toDateTimeString();
        $proId = $protocol['pro_id'] ?? null;
        $now = now();

        $uploadEntries = [];
        $summary = [];
        $details = [];

        $disk = Storage::disk('hostinger');
        $uploadBaseUrl = rtrim(env('UPLOAD_BASE_URL', ''), '/');
        $profileImageUrl = null;

        /* --------------------------------
           Normalize expiry data
        -------------------------------- */
        $normalizedExpiryData = [];

        foreach ($expiryData as $key => $value) {
            if ($key && $value) {
                $normalizedExpiryData[strtolower($key)] = $value;
            }
        }

        /* --------------------------------
           Process Files
        -------------------------------- */
        foreach ($files as $item) {

            $field = $item['field'];

            $fileGroup = is_array($item['file'])
                ? $item['file']
                : [$item['file']];

            foreach ($fileGroup as $file) {

                $timestamp = now()->format('YmdHis');

                $folderParts = [$refId];

                /* --------------------------------
                   Folder Logic
                -------------------------------- */

                // VEHICLE
                if (str_starts_with($target, 'vehicle')) {

                    $folderParts[] = $proId ?? $refId;

                    $baseDir = match ($field) {
                        'vehicle_doc' => 'vehicle/Documents',
                        'vehicle_img' => 'vehicle/Images',
                        default => 'vehicle/Misc',
                    };

                }

                // booking
                elseif (str_starts_with($target, 'booking')) {

                    $baseDir = match ($field) {
                        'booking_doc' => 'booking/Documents',
                        'booking_img' => 'booking/Images',
                        default => 'booking/Misc',
                    };

                }

                // ACCOUNT
                else {

                    $baseDir = match ($field) {
                        'account_img' => 'account/Profiles/Img',
                        'account_doc' => 'account/Profiles/Doc',
                        default => 'account/Misc',
                    };
                }

                $folderParts[] = "{$field}_{$timestamp}";

                $numericFolder = $baseDir . '/' . implode('/', $folderParts);

                if (!$disk->exists($numericFolder)) {
                    $disk->makeDirectory(
                        $numericFolder,
                        0755,
                        true
                    );
                }

                /* --------------------------------
                   Store File
                -------------------------------- */

                $uuid = (string) Str::uuid();

                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();

                $filename = $uuid . '.' . $extension;

                $path = $numericFolder . '/' . $filename;

                $disk->put(
                    $path,
                    fopen($file->getRealPath(), 'r')
                );

                $fullUrl = $uploadBaseUrl . '/' . $path;

                /* --------------------------------
                   Profile Image - Account
                -------------------------------- */

                if (
                    in_array($target, ['account', 'account_update']) &&
                    $field === 'account_img'
                ) {
                    $profileImageUrl = $fullUrl;
                }

                /* --------------------------------
                   Expiry Logic
                -------------------------------- */

                $baseFileName = strtolower(
                    pathinfo($originalName, PATHINFO_FILENAME)
                );

                $expiryAt = $normalizedExpiryData[$baseFileName]
                    ?? now()->addYear();

                try {
                    $expiryAt = Carbon::parse($expiryAt)
                        ->toDateTimeString();
                } catch (\Throwable $e) {
                    $expiryAt = now()
                        ->addYear()
                        ->toDateTimeString();
                }

                /* --------------------------------
                   Database Entry
                -------------------------------- */

                $entry = [
                    'file_id' => $uuid,
                    'ref_id' => $refId,
                    'file_url' => $path,
                    'file_name' => $originalName,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'ref_hash' => hash('sha256', $refId),
                    'uploaded_at' => $uploadedAt,
                    'created_at' => $now,
                    'expiry_at' => $expiryAt,
                ];

                /* --------------------------------
                   Vehicle Reference
                -------------------------------- */

                if (str_starts_with($target, 'vehicle')) {
                    $entry['vehicle_id'] = $proId ?? $refId;
                }

                /* --------------------------------
                   File Category
                -------------------------------- */

                $entry['file_category'] = match (true) {

                    // Vehicle
                    str_starts_with($target, 'vehicle')
                    && $field === 'vehicle_doc'
                    => 'Vehicle Document',

                    str_starts_with($target, 'vehicle')
                    && $field === 'vehicle_img'
                    => 'Vehicle Image',

                    // booking
                    str_starts_with($target, 'booking')
                    && $field === 'booking_doc'
                    => 'booking Document',

                    str_starts_with($target, 'booking')
                    && $field === 'booking_img'
                    => 'booking Image',

                    // Account
                    str_starts_with($target, 'account')
                    && $field === 'account_img'
                    => 'Profile Image',

                    str_starts_with($target, 'account')
                    && $field === 'account_doc'
                    => 'Profile Document',

                    default => 'Miscellaneous',
                };

                $uploadEntries[] = $entry;

                $summary[$field] = ($summary[$field] ?? 0) + 1;

                $details[] = [
                    'field' => $field,
                    'file_name' => $originalName,
                    'file_url' => $fullUrl,
                    'file_id' => $uuid,
                    'file_category' => $entry['file_category'],
                    'expiry_at' => $expiryAt,
                    'state' => 'uploaded',
                ];
            }
        }

        /* --------------------------------
           Save to Database
        -------------------------------- */

        foreach ($uploadEntries as $i => $entry) {

            /*
            |--------------------------------------------------------------------------
            | Account
            |--------------------------------------------------------------------------
            */

            if (str_starts_with($target, 'account')) {

                $upload = AccountFile::create($entry);

                $state = AccountFileState::create([
                    'file_id' => $entry['file_id'],
                    'state' => 'uploaded',
                    'code' => 'UPLOADED471',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | booking
            |--------------------------------------------------------------------------
            */ elseif (str_starts_with($target, 'booking')) {

                $upload = BookingFile::create($entry);

                $state = BookingFileState::create([
                    'file_id' => $entry['file_id'],
                    'state' => 'uploaded',
                    'code' => 'UPLOADED471',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Vehicle
            |--------------------------------------------------------------------------
            */ else {

                $upload = VehicleFile::create($entry);

                $state = VehicleFileState::create([
                    'file_id' => $entry['file_id'],
                    'state' => 'uploaded',
                    'code' => 'UPLOADED471',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Attach State
            |--------------------------------------------------------------------------
            */

            $upload->update([
                'file_state_id' => $state->id
            ]);

            $details[$i]['upload_id'] = $upload->id;
        }

        /* --------------------------------
           Update Profile Image
        -------------------------------- */

        if (
            in_array($target, ['account', 'account_update']) &&
            $profileImageUrl
        ) {
            User::where('acc_id', $refId)
                ->update([
                    'profile_img' => $profileImageUrl
                ]);
        }

        /* --------------------------------
           Response
        -------------------------------- */

        return [
            'status' => true,
            'message' => 'Files uploaded successfully',
            'upload_base_url' => $uploadBaseUrl,
            'summary' => $summary,
            'uploaded' => $details,
        ];
    }



}
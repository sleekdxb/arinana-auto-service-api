<?php

namespace App\Helpers;

use App\Models\Contact;
use App\Models\ContactsState;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicHelper
{

    public static function submitMessage(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {

            // 1. Normalize email
            $email = Str::lower(trim($request->email));

            // 2. Find the latest contact for this email
            $lastContact = Contact::whereRaw('LOWER(email) = ?', [$email])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            // 3. Allow a new contact only after 24 hours
            if ($lastContact) {
                $nextAllowedAt = $lastContact->created_at
                    ->copy()
                    ->addHours(24);

                if (now()->lt($nextAllowedAt)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can submit another contact after 24 hours from your last contact.',
                        'last_contact_at' => $lastContact->created_at
                            ->toDateTimeString(),
                        'next_allowed_at' => $nextAllowedAt
                            ->toDateTimeString(),
                    ], 409);
                }
            }

            // 4. Generate the contact ID first
            $conId = hash('sha256', (string) Str::uuid());

            // 5. Create a NEW state for this contact
            $state = ContactsState::create([
                'con_id' => $conId,
                'state_id' => (string) Str::uuid(),
                'name' => 'New',
                'code' => 'NEW',
                'team_id' => 'SYSTEM',
            ]);

            // 6. Create the contact and link its state_id
            $contact = Contact::create([
                'con_id' => $conId,
                'name' => $request->name,
                'email' => $email,
                'interested' => $request->interested,
                'state_id' => $state->state_id,
                'message' => $request->message,
            ]);

            // 7. Return success response
            return response()->json([
                'success' => true,
                'message' => 'Your message has been submitted successfully.',
                'data' => [
                    'con_id' => $contact->con_id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'interested' => $contact->interested,
                    'state_id' => $contact->state_id,
                    'message' => $contact->message,
                    'created_at' => $contact->created_at,
                ],
            ], 201);
        });
    }

}
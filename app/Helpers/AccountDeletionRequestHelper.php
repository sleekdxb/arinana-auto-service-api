<?php

namespace App\Helpers;

use App\Models\AccountDeletionRequest;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccountDeletionRequestHelper
{
    public static function request(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $pending = AccountDeletionRequest::where('client_id', $client->client_id)
            ->where('status', AccountDeletionRequest::PENDING)
            ->first();

        if ($pending) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an account deletion request in review.',
                'data' => self::present($pending),
            ], 409);
        }

        $deletion = AccountDeletionRequest::create([
            'request_id' => 'DEL' . strtoupper(Str::random(12)),
            'client_id' => $client->client_id,
            'client_name' => self::clientName($client),
            'client_email' => $client->email,
            'client_phone' => $client->phone,
            'account_type' => $client->account_type,
            'reason' => $request->filled('reason') ? trim($request->reason) : null,
            'status' => AccountDeletionRequest::PENDING,
        ]);

        self::tellClient(
            $deletion,
            'Account deletion requested',
            'We have received your request to delete your account. Our team will review it and email you once it is done. You can cancel the request from the app until then.'
        );

        return response()->json([
            'success' => true,
            'message' => 'Your account deletion request has been sent.',
            'data' => self::present($deletion),
        ], 201);
    }

    public static function current(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $latest = AccountDeletionRequest::where('client_id', $client->client_id)->latest('id')->first();

        return response()->json([
            'success' => true,
            'message' => 'Account deletion request retrieved.',
            'data' => $latest ? self::present($latest) : null,
        ], 200);
    }

    public static function cancel(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $pending = AccountDeletionRequest::where('client_id', $client->client_id)
            ->where('status', AccountDeletionRequest::PENDING)
            ->first();

        if (!$pending) {
            return response()->json([
                'success' => false,
                'message' => 'There is no account deletion request to cancel.',
            ], 404);
        }

        $pending->update(['status' => AccountDeletionRequest::CANCELLED, 'handled_at' => now()]);

        self::tellClient(
            $pending,
            'Account deletion cancelled',
            'Your account deletion request has been cancelled. Your account stays active.'
        );

        return response()->json([
            'success' => true,
            'message' => 'Your account deletion request has been cancelled.',
            'data' => self::present($pending),
        ], 200);
    }

    public static function list(): JsonResponse
    {
        $requests = AccountDeletionRequest::with('handledBy')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AccountDeletionRequest::PENDING])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Account deletion requests retrieved.',
            'data' => $requests->map(fn (AccountDeletionRequest $r) => self::present($r, true))->values(),
        ], 200);
    }

    public static function approve(Request $request): JsonResponse
    {
        $deletion = self::pendingOrFail($request->request_id);
        if ($deletion instanceof JsonResponse) {
            return $deletion;
        }

        $client = Client::where('client_id', $deletion->client_id)->first();
        if ($client) {
            ClientDeletionHelper::delete($client);
        }

        $deletion->update([
            'status' => AccountDeletionRequest::COMPLETED,
            'team_id' => $request->attributes->get('team')->team_id,
            'note' => $request->filled('note') ? trim($request->note) : null,
            'handled_at' => now(),
        ]);

        AccountMailHelper::send(
            $deletion->client_email,
            $deletion->client_name,
            'Your Ariana Auto Service account has been deleted',
            'Your account has been deleted',
            'As you requested, your account and personal data have been permanently deleted. Records of completed services that we are required to keep have been anonymised. Thank you for choosing Ariana Auto Service.',
            ['status' => 'Deleted']
        );

        return response()->json([
            'success' => true,
            'message' => 'The account has been permanently deleted.',
            'data' => self::present($deletion->fresh('handledBy'), true),
        ], 200);
    }

    public static function reject(Request $request): JsonResponse
    {
        $deletion = self::pendingOrFail($request->request_id);
        if ($deletion instanceof JsonResponse) {
            return $deletion;
        }

        $deletion->update([
            'status' => AccountDeletionRequest::REJECTED,
            'team_id' => $request->attributes->get('team')->team_id,
            'note' => trim($request->note),
            'handled_at' => now(),
        ]);

        self::tellClient(
            $deletion,
            'Account deletion declined',
            'We were unable to delete your account at this time. Please see the note from our team below.',
            $deletion->note
        );

        return response()->json([
            'success' => true,
            'message' => 'The account deletion request has been declined.',
            'data' => self::present($deletion->fresh('handledBy'), true),
        ], 200);
    }

    private static function pendingOrFail(?string $requestId): AccountDeletionRequest|JsonResponse
    {
        $deletion = AccountDeletionRequest::where('request_id', $requestId)->first();

        if (!$deletion) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if ($deletion->status !== AccountDeletionRequest::PENDING) {
            return response()->json(['success' => false, 'message' => 'This request has already been handled.'], 422);
        }

        return $deletion;
    }

    /** In-app notification, push and email for a request the client still has an account for. */
    private static function tellClient(AccountDeletionRequest $deletion, string $title, string $message, ?string $note = null): void
    {
        $notification = NotificationHelper::notify(
            $deletion->client_id,
            'account',
            $title,
            $note ?: $message,
            $deletion->request_id,
            ['status' => $deletion->status]
        );

        if ($notification) {
            try {
                PushHelper::sendToClient($deletion->client_id, $title, $note ?: $message, [
                    'notif_id' => $notification->notification_id,
                    'type' => 'account',
                    'ref_id' => $deletion->request_id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Account push failed: ' . $e->getMessage());
            }
        }

        AccountMailHelper::send($deletion->client_email, $deletion->client_name, $title, $title, $message, [
            'note' => $note,
            'rows' => [['Request', $deletion->request_id], ['Requested', self::shopTime($deletion->created_at)]],
        ]);
    }

    private static function present(AccountDeletionRequest $r, bool $forTeam = false): array
    {
        $data = [
            'request_id' => $r->request_id,
            'status' => $r->status,
            'reason' => $r->reason,
            'note' => $r->note,
            'created_at' => $r->created_at?->toIso8601String(),
            'handled_at' => $r->handled_at?->toIso8601String(),
        ];

        if (!$forTeam) {
            return $data;
        }

        return $data + [
            'client_id' => $r->client_id,
            'client_name' => $r->client_name,
            'client_email' => $r->client_email,
            'client_phone' => $r->client_phone,
            'account_type' => $r->account_type,
            'account_exists' => Client::where('client_id', $r->client_id)->exists(),
            'handled_by' => $r->handledBy?->full_name,
        ];
    }

    private static function clientName(Client $client): string
    {
        return trim($client->first_name . ' ' . $client->last_name) ?: (string) $client->business_name;
    }

    private static function shopTime(?Carbon $at): string
    {
        return $at ? $at->copy()->timezone(config('booking.timezone'))->format('M j, Y, g:i A') : '';
    }
}

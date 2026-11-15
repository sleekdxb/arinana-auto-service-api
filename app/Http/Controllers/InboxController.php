<?php

namespace App\Http\Controllers;

use App\Helpers\AccountDeletionRequestHelper;
use App\Helpers\ContactMessageHelper;
use App\Models\ContactsState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InboxController extends Controller
{
    public function requestAccountDeletion(Request $request): JsonResponse
    {
        return $this->validated($request, ['reason' => 'nullable|string|max:1000'],
            fn () => AccountDeletionRequestHelper::request($request));
    }

    public function getAccountDeletionRequest(Request $request): JsonResponse
    {
        return AccountDeletionRequestHelper::current($request);
    }

    public function cancelAccountDeletionRequest(Request $request): JsonResponse
    {
        return AccountDeletionRequestHelper::cancel($request);
    }

    public function getAccountDeletionRequests(): JsonResponse
    {
        return AccountDeletionRequestHelper::list();
    }

    public function approveAccountDeletion(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'request_id' => 'required|string',
            'note' => 'nullable|string|max:1000',
        ], fn () => AccountDeletionRequestHelper::approve($request));
    }

    public function rejectAccountDeletion(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'request_id' => 'required|string',
            'note' => 'required|string|max:1000',
        ], fn () => AccountDeletionRequestHelper::reject($request));
    }

    public function getContactMessages(): JsonResponse
    {
        return ContactMessageHelper::list();
    }

    public function setContactMessageState(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'con_id' => 'required|string|exists:contacts,con_id',
            'state' => ['required', Rule::in([ContactsState::NEW, ContactsState::READ, ContactsState::CLOSED])],
            'note' => 'nullable|string|max:1000',
        ], fn () => ContactMessageHelper::setState($request));
    }

    public function replyContactMessage(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'con_id' => 'required|string|exists:contacts,con_id',
            'message' => 'required|string|max:5000',
        ], fn () => ContactMessageHelper::reply($request));
    }

    private function validated(Request $request, array $rules, callable $next): JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return $next();
    }
}

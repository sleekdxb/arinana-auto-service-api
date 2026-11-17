<?php

namespace App\Http\Controllers;

use App\Helpers\StaffHelper;
use App\Models\Team;
use App\Models\TeamStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function getStaff(): JsonResponse
    {
        return StaffHelper::getStaff();
    }

    public function addStaff(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:teams,email',
            'phone' => 'nullable|string|max:30|unique:teams,phone',
            'role' => 'required|string|max:100',
            'access_level' => ['required', Rule::in(Team::ACCESS_LEVELS)],
            'employee_id' => 'nullable|string|max:100|unique:teams,employee_id',
        ], fn () => StaffHelper::addStaff($request));
    }

    public function updateStaff(Request $request): JsonResponse
    {
        $id = $request->input('staff_id');

        return $this->validated($request, [
            'staff_id' => 'required|string|exists:teams,team_id',
            'full_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('teams', 'email')->ignore($id, 'team_id')],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('teams', 'phone')->ignore($id, 'team_id')],
            'role' => 'sometimes|required|string|max:100',
            'access_level' => ['sometimes', 'required', Rule::in(Team::ACCESS_LEVELS)],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('teams', 'employee_id')->ignore($id, 'team_id')],
        ], fn () => StaffHelper::updateStaff($request));
    }

    public function suspendStaff(Request $request): JsonResponse
    {
        return $this->validated($request, $this->target(), fn () => StaffHelper::setStaffStatus($request, TeamStatus::SUSPENDED));
    }

    public function reactivateStaff(Request $request): JsonResponse
    {
        return $this->validated($request, $this->target(), fn () => StaffHelper::setStaffStatus($request, TeamStatus::ACTIVE));
    }

    public function resetStaffPassword(Request $request): JsonResponse
    {
        return $this->validated($request, $this->target(), fn () => StaffHelper::resetStaffPassword($request));
    }

    public function deleteStaff(Request $request): JsonResponse
    {
        return $this->validated($request, $this->target(), fn () => StaffHelper::deleteStaff($request));
    }

    private function target(): array
    {
        return [
            'staff_id' => 'required|string|exists:teams,team_id',
            'note' => 'nullable|string|max:500',
        ];
    }

    private function validated(Request $request, array $rules, callable $next): JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        return $next();
    }
}

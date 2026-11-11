<?php

namespace App\Helpers;

use App\Mail\BookingUpdateMail;
use App\Models\Booking;
use App\Models\Team;
use App\Models\TeamSession;
use App\Models\TeamStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StaffHelper
{
    public static function getStaff(): JsonResponse
    {
        $staff = Team::with('currentStatus')->orderBy('full_name')->get();
        $lastActive = TeamSession::select('team_id', DB::raw('MAX(last_activity) as last_activity'))
            ->groupBy('team_id')
            ->pluck('last_activity', 'team_id');

        return response()->json([
            'success' => true,
            'message' => 'Staff retrieved successfully.',
            'data' => $staff->map(fn (Team $member) => self::present($member, $lastActive[$member->team_id] ?? null))->values(),
        ], 200);
    }

    public static function addStaff(Request $request): JsonResponse
    {
        $password = self::temporaryPassword();

        $member = DB::transaction(function () use ($request, $password) {
            $teamId = 'TEAM' . strtoupper(Str::random(10));
            $stateId = 'STATE-' . strtoupper(Str::random(10));
            $email = strtolower(trim($request->email));

            TeamStatus::create([
                'team_id' => $teamId,
                'team_setter_id' => $request->attributes->get('team')->team_id,
                'state_id' => $stateId,
                'name' => TeamStatus::ACTIVE,
                'code' => 'ACTIVE',
                'note' => 'Account created',
            ]);

            return Team::create([
                'team_id' => $teamId,
                'email' => $email,
                'hashed_email' => hash('sha256', $email),
                'full_name' => trim($request->full_name),
                'phone' => $request->filled('phone') ? trim($request->phone) : null,
                'account_type' => 'admin',
                'role' => $request->role,
                'access_level' => $request->access_level,
                'employee_id' => $request->filled('employee_id') ? trim($request->employee_id) : null,
                'state_id' => $stateId,
                'password' => $password,
            ]);
        });

        $emailed = self::sendPassword($member, $password, 'Your Ariana staff account', 'Welcome to Ariana Auto Service',
            'An account has been created for you on the Ariana admin dashboard. Sign in with the details below and change your password afterwards.');

        return response()->json([
            'success' => true,
            'message' => 'Staff member added successfully.',
            'data' => [
                'staff' => self::present($member->load('currentStatus')),
                'temporary_password' => $password,
                'emailed' => $emailed,
            ],
        ], 201);
    }

    public static function updateStaff(Request $request): JsonResponse
    {
        $member = Team::with('currentStatus')->where('team_id', $request->staff_id)->firstOrFail();

        $data = collect($request->only(['full_name', 'email', 'phone', 'role', 'access_level', 'employee_id']))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        if (array_key_exists('phone', $data) && $data['phone'] === '') {
            $data['phone'] = null;
        }
        if (array_key_exists('employee_id', $data) && $data['employee_id'] === '') {
            $data['employee_id'] = null;
        }
        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
            $data['hashed_email'] = hash('sha256', $data['email']);
        }

        if (
            isset($data['access_level'])
            && $member->access_level === Team::SUPER_ADMIN
            && $data['access_level'] !== Team::SUPER_ADMIN
            && self::isLastSuperAdmin($member)
        ) {
            return self::refuse('There must be at least one active Super Admin.');
        }

        $member->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully.',
            'data' => self::present($member->fresh('currentStatus')),
        ], 200);
    }

    public static function setStaffStatus(Request $request, string $status): JsonResponse
    {
        $actor = $request->attributes->get('team');
        $member = Team::with('currentStatus')->where('team_id', $request->staff_id)->firstOrFail();

        if ($status === TeamStatus::SUSPENDED) {
            if ($member->team_id === $actor->team_id) {
                return self::refuse("You can't suspend your own account.");
            }
            if ($member->access_level === Team::SUPER_ADMIN && self::isLastSuperAdmin($member)) {
                return self::refuse('There must be at least one active Super Admin.');
            }
        }

        DB::transaction(function () use ($member, $actor, $status, $request) {
            $stateId = 'STATE-' . strtoupper(Str::random(10));

            TeamStatus::create([
                'team_id' => $member->team_id,
                'team_setter_id' => $actor->team_id,
                'state_id' => $stateId,
                'name' => $status,
                'code' => strtoupper($status),
                'note' => $request->input('note'),
            ]);

            $member->update(['state_id' => $stateId]);

            if ($status === TeamStatus::SUSPENDED) {
                TeamSession::where('team_id', $member->team_id)->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => $status === TeamStatus::SUSPENDED ? 'Staff member suspended.' : 'Staff member reactivated.',
            'data' => self::present($member->fresh('currentStatus')),
        ], 200);
    }

    public static function resetStaffPassword(Request $request): JsonResponse
    {
        $member = Team::where('team_id', $request->staff_id)->firstOrFail();
        $password = self::temporaryPassword();

        $member->update(['password' => $password]);
        TeamSession::where('team_id', $member->team_id)->delete();

        $emailed = self::sendPassword($member, $password, 'Your Ariana password was reset', 'Your password was reset',
            'An administrator reset your password for the Ariana admin dashboard. Sign in with the temporary password below and change it afterwards.');

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
            'data' => [
                'temporary_password' => $password,
                'emailed' => $emailed,
            ],
        ], 200);
    }

    public static function deleteStaff(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('team');
        $member = Team::with('currentStatus')->where('team_id', $request->staff_id)->firstOrFail();

        if ($member->team_id === $actor->team_id) {
            return self::refuse("You can't delete your own account.");
        }
        if ($member->access_level === Team::SUPER_ADMIN && self::isLastSuperAdmin($member)) {
            return self::refuse('There must be at least one active Super Admin.');
        }

        DB::transaction(function () use ($member) {
            Booking::where('technician_id', $member->team_id)->update(['technician_id' => null]);
            TeamSession::where('team_id', $member->team_id)->delete();
            TeamStatus::where('team_id', $member->team_id)->delete();
            $member->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Staff member deleted.',
        ], 200);
    }

    private static function present(Team $member, ?int $lastActivity = null): array
    {
        return [
            'team_id' => $member->team_id,
            'full_name' => $member->full_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'role' => $member->role,
            'access_level' => $member->access_level,
            'employee_id' => $member->employee_id,
            'status' => $member->currentStatus?->name ?? TeamStatus::ACTIVE,
            'last_active_at' => $lastActivity ? Carbon::createFromTimestamp($lastActivity)->toIso8601String() : null,
            'created_at' => $member->created_at?->toIso8601String(),
        ];
    }

    private static function isLastSuperAdmin(Team $member): bool
    {
        return !Team::with('currentStatus')
            ->where('access_level', Team::SUPER_ADMIN)
            ->where('team_id', '!=', $member->team_id)
            ->get()
            ->contains(fn (Team $other) => !$other->isSuspended());
    }

    private static function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    private static function sendPassword(Team $member, string $password, string $subject, string $heading, string $message): bool
    {
        try {
            Mail::to($member->email)->send(new BookingUpdateMail($subject, [
                'name' => strtok((string) $member->full_name, ' ') ?: 'there',
                'heading' => $heading,
                'message' => $message,
                'note' => null,
                'status' => null,
                'rows' => [
                    ['Email', $member->email],
                    ['Temporary password', $password],
                ],
                'footer' => 'If you did not expect this email, please contact your administrator.',
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Staff password email failed: ' . $e->getMessage());

            return false;
        }
    }

    private static function refuse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VslaMeetingAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Get list of attendance records with optional filters
     * 
     * Query Parameters:
     * - search: Search by member name or code
     * - sort_by: Field to sort by (member_name, meeting_date, status)
     * - sort_order: asc or desc
     * - status: Filter by attendance status (present, absent)
     * - meeting_id: Filter by specific meeting
     * - member_id: Filter by specific member
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Base query with relationships
        $query = VslaMeetingAttendance::with(['member', 'meeting']);

        // Role-based access control
        if ($user->is_group_admin == 'Yes' || 
            $user->is_group_secretary == 'Yes' || 
            $user->is_group_treasurer == 'Yes') {
            // Admin users see all attendance for their group
            $query->whereHas('meeting', function($q) use ($user) {
                $q->where('group_id', $user->group_id);
            });
        } else {
            // Regular members see only their own attendance
            $query->where('member_id', $user->id);
        }

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('member', function($q) use ($search) {
                $q->where(function($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('member_code', 'like', "%{$search}%");
                });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $isPresent = $request->status === 'present';
            $query->where('is_present', $isPresent);
        }

        // Meeting filter
        if ($request->has('meeting_id')) {
            $query->where('meeting_id', $request->meeting_id);
        }

        // Member filter
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'member_name') {
            $query->join('users', 'vsla_meeting_attendance.member_id', '=', 'users.id')
                  ->orderBy('users.first_name', $sortOrder)
                  ->select('vsla_meeting_attendance.*');
        } elseif ($sortBy === 'meeting_date') {
            $query->join('vsla_meetings', 'vsla_meeting_attendance.meeting_id', '=', 'vsla_meetings.id')
                  ->orderBy('vsla_meetings.meeting_date', $sortOrder)
                  ->select('vsla_meeting_attendance.*');
        } elseif ($sortBy === 'status') {
            $query->orderBy('is_present', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $attendance = $query->get();

        // Transform data for mobile app
        $transformedAttendance = $attendance->map(function($record) {
            return [
                'id' => $record->id,
                'meeting_id' => $record->meeting_id,
                'member_id' => $record->member_id,
                'is_present' => $record->is_present,
                'absent_reason' => $record->absent_reason,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                
                // Member details
                'member' => $record->member ? [
                    'id' => $record->member->id,
                    'name' => $record->member->name,
                    'first_name' => $record->member->first_name,
                    'last_name' => $record->member->last_name,
                    'member_code' => $record->member->member_code,
                    'phone_number' => $record->member->phone_number,
                    'avatar' => $record->member->avatar,
                ] : null,
                
                // Meeting details
                'meeting' => $record->meeting ? [
                    'id' => $record->meeting->id,
                    'meeting_number' => $record->meeting->meeting_number,
                    'meeting_date' => $record->meeting->meeting_date,
                    'notes' => $record->meeting->notes,
                ] : null,
            ];
        });

        return response()->json([
            'code' => 1,
            'message' => 'Attendance records retrieved successfully',
            'data' => $transformedAttendance,
        ]);
    }

    /**
     * Get single attendance record
     */
    public function show($id)
    {
        $user = auth()->user();
        
        $attendance = VslaMeetingAttendance::with(['member', 'meeting'])->find($id);

        if (!$attendance) {
            return response()->json([
                'code' => 0,
                'message' => 'Attendance record not found',
                'data' => null,
            ], 404);
        }

        // Check access permissions
        if ($user->is_group_admin != 'Yes' && 
            $user->is_group_secretary != 'Yes' && 
            $user->is_group_treasurer != 'Yes') {
            // Regular members can only view their own attendance
            if ($attendance->member_id != $user->id) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Unauthorized access',
                    'data' => null,
                ], 403);
            }
        }

        // Transform data
        $transformedAttendance = [
            'id' => $attendance->id,
            'meeting_id' => $attendance->meeting_id,
            'member_id' => $attendance->member_id,
            'is_present' => $attendance->is_present,
            'absent_reason' => $attendance->absent_reason,
            'created_at' => $attendance->created_at,
            'updated_at' => $attendance->updated_at,
            
            'member' => $attendance->member ? [
                'id' => $attendance->member->id,
                'name' => $attendance->member->name,
                'first_name' => $attendance->member->first_name,
                'last_name' => $attendance->member->last_name,
                'member_code' => $attendance->member->member_code,
                'phone_number' => $attendance->member->phone_number,
                'avatar' => $attendance->member->avatar,
            ] : null,
            
            'meeting' => $attendance->meeting ? [
                'id' => $attendance->meeting->id,
                'meeting_number' => $attendance->meeting->meeting_number,
                'meeting_date' => $attendance->meeting->meeting_date,
                'notes' => $attendance->meeting->notes,
            ] : null,
        ];

        return response()->json([
            'code' => 1,
            'message' => 'Attendance record retrieved successfully',
            'data' => $transformedAttendance,
        ]);
    }

    /**
     * Get attendance statistics
     */
    public function stats(Request $request)
    {
        $user = auth()->user();
        
        $query = VslaMeetingAttendance::query();

        // Role-based access control
        if ($user->is_group_admin == 'Yes' || 
            $user->is_group_secretary == 'Yes' || 
            $user->is_group_treasurer == 'Yes') {
            $query->whereHas('meeting', function($q) use ($user) {
                $q->where('group_id', $user->group_id);
            });
        } else {
            $query->where('member_id', $user->id);
        }

        // Filters
        if ($request->has('cycle_id')) {
            $query->whereHas('meeting', function($q) use ($request) {
                $q->where('cycle_id', $request->cycle_id);
            });
        }

        if ($request->has('meeting_id')) {
            $query->where('meeting_id', $request->meeting_id);
        }

        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        $totalRecords = $query->count();
        $totalPresent = $query->clone()->where('is_present', true)->count();
        $totalAbsent = $query->clone()->where('is_present', false)->count();
        $attendanceRate = $totalRecords > 0 ? ($totalPresent / $totalRecords * 100) : 0;

        return response()->json([
            'code' => 1,
            'message' => 'Statistics retrieved successfully',
            'data' => [
                'total_records' => $totalRecords,
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'attendance_rate' => round($attendanceRate, 2),
            ],
        ]);
    }

    /**
     * Export attendance to PDF
     */
    public function exportPdf(Request $request)
    {
        // Placeholder for PDF export functionality
        return response()->json([
            'code' => 1,
            'message' => 'PDF export functionality coming soon',
            'data' => null,
        ]);
    }
}

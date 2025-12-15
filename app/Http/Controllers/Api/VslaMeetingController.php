<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VslaMeeting;
use App\Models\Project;
use App\Models\FfsGroup;
use App\Services\MeetingProcessingService;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * VSLA Meeting API Controller
 * Handles offline meeting submission and synchronization from mobile app
 */
class VslaMeetingController extends Controller
{
    use ApiResponser;

    protected $meetingProcessor;

    public function __construct(MeetingProcessingService $meetingProcessor)
    {
        $this->meetingProcessor = $meetingProcessor;
    }

    /**
     * Submit a new meeting from mobile app
     * POST /api/vsla-meetings/submit
     * 
     * This is the main endpoint for offline meeting synchronization
     */
    public function submit(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'local_id' => 'required|string|max:255',
                'cycle_id' => 'required|integer|exists:projects,id',
                'group_id' => 'nullable|integer',
                'meeting_date' => 'required|date',
                'notes' => 'nullable|string',
                
                // Member counts
                'members_present' => 'required|integer|min:0',
                'members_absent' => 'nullable|integer|min:0',
                
                // Financial totals
                'total_savings_collected' => 'nullable|numeric|min:0',
                'total_welfare_collected' => 'nullable|numeric|min:0',
                'total_social_fund_collected' => 'nullable|numeric|min:0',
                'total_fines_collected' => 'nullable|numeric|min:0',
                'total_loans_disbursed' => 'nullable|numeric|min:0',
                'total_shares_sold' => 'nullable|integer|min:0',
                'total_share_value' => 'nullable|numeric|min:0',
                
                // JSON data arrays
                'attendance_data' => 'required|array',
                'transactions_data' => 'nullable|array',
                'loans_data' => 'nullable|array',
                'share_purchases_data' => 'nullable|array',
                'previous_action_plans_data' => 'nullable|array',
                'upcoming_action_plans_data' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation failed', 422, [
                    'errors' => $validator->errors()
                ]);
            }

            // Check for duplicate submission (by local_id)
            $existing = VslaMeeting::where('local_id', $request->local_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting already submitted',
                    'code' => 409,
                    'meeting_id' => $existing->id,
                    'meeting_number' => $existing->meeting_number,
                    'processing_status' => $existing->processing_status,
                    'submitted_at' => $existing->created_at
                ], 409);
            }

            // Validate cycle
            $cycle = Project::find($request->cycle_id);
            if (!$cycle) {
                return $this->error('Cycle not found', 404);
            }

            // Validate cycle is active
            if ($cycle->is_active_cycle !== 'Yes') {
                return $this->error('This cycle is not active. Please select an active cycle.', 422, [
                    'error_type' => 'inactive_cycle',
                    'cycle_status' => $cycle->is_active_cycle
                ]);
            }

            // Validate cycle is VSLA type
            if ($cycle->is_vsla_cycle !== 'Yes') {
                return $this->error('This cycle is not a VSLA cycle', 422, [
                    'error_type' => 'invalid_cycle_type'
                ]);
            }

            // Validate group belongs to cycle (if group_id provided)
            if ($request->group_id) {
                $group = FfsGroup::find($request->group_id);
                if (!$group) {
                    return $this->error('Group not found', 404);
                }
                
                if ($group->type !== 'VSLA') {
                    return $this->error('Group is not a VSLA group', 422);
                }
            }

            // Auto-generate meeting number (server-controlled)
            $meetingNumber = $this->generateMeetingNumber($request->cycle_id, $request->group_id);

            // Get authenticated user ID (server-controlled)
            // Try multiple methods: Auth facade, request user, request userModel, or request parameter
            $createdById = Auth::id() 
                ?? optional($request->user())->id 
                ?? optional($request->userModel)->id
                ?? $request->user_id 
                ?? 1; // Fallback to admin user ID 1 if all else fails

            // Create meeting record
            DB::beginTransaction();

            $meeting = VslaMeeting::create([
                'local_id' => $request->local_id,
                'cycle_id' => $request->cycle_id,
                'group_id' => $request->group_id,
                'meeting_date' => $request->meeting_date,
                'meeting_number' => $meetingNumber,
                'notes' => $request->notes,
                'members_present' => $request->members_present,
                'members_absent' => $request->members_absent ?? 0,
                'total_savings_collected' => $request->total_savings_collected ?? 0,
                'total_welfare_collected' => $request->total_welfare_collected ?? 0,
                'total_social_fund_collected' => $request->total_social_fund_collected ?? 0,
                'total_fines_collected' => $request->total_fines_collected ?? 0,
                'total_loans_disbursed' => $request->total_loans_disbursed ?? 0,
                'total_shares_sold' => $request->total_shares_sold ?? 0,
                'total_share_value' => $request->total_share_value ?? 0,
                'attendance_data' => $request->attendance_data,
                'transactions_data' => $request->transactions_data ?? [],
                'loans_data' => $request->loans_data ?? [],
                'share_purchases_data' => $request->share_purchases_data ?? [],
                'previous_action_plans_data' => $request->previous_action_plans_data ?? [],
                'upcoming_action_plans_data' => $request->upcoming_action_plans_data ?? [],
                'processing_status' => 'pending',
                'created_by_id' => $createdById,
                'submitted_from_app_at' => now(),
                'received_at' => now(),
            ]);

            // Process meeting immediately
            $processingResult = $this->meetingProcessor->processMeeting($meeting);

            DB::commit();

            // Reload meeting to get updated status
            $meeting->refresh();

            return response()->json([
                'success' => $processingResult['success'],
                'message' => $processingResult['success'] 
                    ? 'Meeting submitted and processed successfully'
                    : 'Meeting submitted but processing had errors',
                'code' => $processingResult['success'] ? 200 : 207, // 207 = Multi-Status
                'meeting_id' => $meeting->id,
                'meeting_number' => $meeting->meeting_number,
                'processing_status' => $meeting->processing_status,
                'has_errors' => $meeting->has_errors,
                'has_warnings' => $meeting->has_warnings,
                'errors' => $processingResult['errors'] ?? [],
                'warnings' => $processingResult['warnings'] ?? [],
                'meeting_data' => [
                    'id' => $meeting->id,
                    'local_id' => $meeting->local_id,
                    'meeting_number' => $meeting->meeting_number,
                    'meeting_date' => $meeting->meeting_date,
                    'cycle_id' => $meeting->cycle_id,
                    'group_id' => $meeting->group_id,
                    'processing_status' => $meeting->processing_status,
                    'processed_at' => $meeting->processed_at,
                ]
            ], $processingResult['success'] ? 200 : 207);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->error('Failed to submit meeting: ' . $e->getMessage(), 500, [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Get list of meetings
     * GET /api/vsla-meetings or /api/vsla/meetings
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = VslaMeeting::with(['cycle', 'group', 'creator'])
                ->where('processing_status', 'completed'); // Only show completed meetings

            // Filter by current user's group if they are not an admin
            if (!$user->isAdmin()) {
                // Get user's VSLA group
                $userGroup = $user->group_id ?? null;
                if ($userGroup) {
                    $query->where('group_id', $userGroup);
                } else {
                    // User has no group, return empty result
                    return response()->json([
                        'code' => 1,
                        'message' => 'Meetings retrieved successfully',
                        'data' => [],
                    ]);
                }
            }

            // Filter by cycle
            if ($request->has('cycle_id')) {
                $query->where('cycle_id', $request->cycle_id);
            }

            // Filter by group (admin only)
            if ($request->has('group_id') && $user->isAdmin()) {
                $query->where('group_id', $request->group_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $status = strtolower($request->status);
                if ($status === 'completed') {
                    $query->where('processing_status', 'completed')
                          ->where('has_errors', false);
                } elseif ($status === 'cancelled') {
                    // Add cancelled logic if needed
                    $query->where('has_errors', true);
                }
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('meeting_number', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%")
                      ->orWhereHas('creator', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            }

            // Sorting
            $sortBy = $request->sort_by ?? 'meeting_date';
            $sortOrder = $request->sort_order ?? 'desc';
            
            // Map frontend sort fields to database fields
            $sortFieldMap = [
                'meeting_date' => 'meeting_date',
                'meeting_number' => 'meeting_number',
                'total_savings' => 'total_savings_collected',
            ];

            $dbSortField = $sortFieldMap[$sortBy] ?? 'meeting_date';
            $query->orderBy($dbSortField, $sortOrder);

            // Get all meetings (no pagination - keep it simple)
            $meetings = $query->get();

            // Transform data to match mobile app expectations
            $transformedData = $meetings->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'cycle_id' => $meeting->cycle_id,
                    'cycle_name' => $meeting->cycle->name ?? null,
                    'meeting_number' => $meeting->meeting_number,
                    'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
                    'location' => $meeting->notes ?? 'N/A', // Using notes as location for now
                    'status' => $meeting->has_errors ? 'cancelled' : 'completed',
                    'chairperson_id' => null, // Not stored in current schema
                    'chairperson_name' => null,
                    'secretary_id' => null,
                    'secretary_name' => null,
                    'treasurer_id' => null,
                    'treasurer_name' => null,
                    'total_attendees' => $meeting->members_present,
                    'total_absentees' => $meeting->members_absent,
                    'total_savings' => (float) $meeting->total_savings_collected,
                    'total_fines' => (float) $meeting->total_fines_collected,
                    'total_welfare' => (float) $meeting->total_welfare_collected,
                    'total_loans_issued' => (float) $meeting->total_loans_disbursed,
                    'total_loans_repaid' => 0.0, // Not stored in current schema
                    'cash_at_hand' => (float) $meeting->net_cash_flow,
                    'notes' => $meeting->notes,
                    'submitted_by' => $meeting->creator->name ?? null,
                    'submitted_at' => $meeting->created_at?->format('Y-m-d H:i:s'),
                    'created_at' => $meeting->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $meeting->updated_at?->format('Y-m-d H:i:s'),
                ];
            })->values()->toArray();

            return response()->json([
                'code' => 1,
                'message' => 'Meetings retrieved successfully',
                'data' => $transformedData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to retrieve meetings: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get single meeting details
     * GET /api/vsla-meetings/{id} or /api/vsla/meetings/{id}
     */
    public function show($id)
    {
        try {
            $meeting = VslaMeeting::with([
                'cycle',
                'group',
                'creator',
                'processor',
                'attendance.member',
                'actionPlans.assignedTo',
                'loans.borrower'
            ])->find($id);

            if (!$meeting) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Meeting not found',
                    'data' => null,
                ], 404);
            }

            // Check access permission
            $user = Auth::user();
            if (!$user->isAdmin() && $meeting->group_id !== $user->group_id) {
                return response()->json([
                    'code' => 0,
                    'message' => 'You do not have permission to view this meeting',
                    'data' => null,
                ], 403);
            }

            // Transform data to match mobile app expectations
            $transformedData = [
                'id' => $meeting->id,
                'cycle_id' => $meeting->cycle_id,
                'cycle_name' => $meeting->cycle->name ?? null,
                'meeting_number' => $meeting->meeting_number,
                'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
                'location' => $meeting->notes ?? 'N/A',
                'status' => $meeting->has_errors ? 'cancelled' : 'completed',
                'chairperson_id' => null,
                'chairperson_name' => null,
                'secretary_id' => null,
                'secretary_name' => null,
                'treasurer_id' => null,
                'treasurer_name' => null,
                'total_attendees' => $meeting->members_present,
                'total_absentees' => $meeting->members_absent,
                'total_savings' => (float) $meeting->total_savings_collected,
                'total_fines' => (float) $meeting->total_fines_collected,
                'total_welfare' => (float) $meeting->total_welfare_collected,
                'total_loans_issued' => (float) $meeting->total_loans_disbursed,
                'total_loans_repaid' => 0.0,
                'cash_at_hand' => (float) $meeting->net_cash_flow,
                'notes' => $meeting->notes,
                'submitted_by' => $meeting->creator->name ?? null,
                'submitted_at' => $meeting->created_at?->format('Y-m-d H:i:s'),
                'created_at' => $meeting->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $meeting->updated_at?->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Meeting details retrieved successfully',
                'data' => $transformedData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to retrieve meeting: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get meeting statistics
     * GET /api/vsla-meetings/stats or /api/vsla/meetings/stats
     */
    public function stats(Request $request)
    {
        try {
            $user = Auth::user();
            $query = VslaMeeting::query();

            // Filter by current user's group if they are not an admin
            if (!$user->isAdmin()) {
                $userGroup = $user->group_id ?? null;
                if ($userGroup) {
                    $query->where('group_id', $userGroup);
                } else {
                    // User has no group, return empty stats
                    return response()->json([
                        'code' => 1,
                        'message' => 'Meeting statistics retrieved successfully',
                        'data' => [
                            'total_meetings' => 0,
                            'total_savings' => 0.0,
                            'completed_meetings' => 0,
                            'cancelled_meetings' => 0,
                        ],
                    ]);
                }
            }

            // Filter by cycle if provided
            if ($request->has('cycle_id')) {
                $query->where('cycle_id', $request->cycle_id);
            }

            // Filter by group if provided (admin only)
            if ($request->has('group_id') && $user->isAdmin()) {
                $query->where('group_id', $request->group_id);
            }

            $stats = [
                'total_meetings' => (clone $query)->where('processing_status', 'completed')->count(),
                'total_savings' => (clone $query)->where('processing_status', 'completed')->sum('total_savings_collected'),
                'completed_meetings' => (clone $query)->where('processing_status', 'completed')->where('has_errors', false)->count(),
                'cancelled_meetings' => (clone $query)->where('has_errors', true)->count(),
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Meeting statistics retrieved successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Reprocess a failed meeting
     * PUT /api/vsla-meetings/{id}/reprocess
     */
    public function reprocess($id)
    {
        try {
            $meeting = VslaMeeting::find($id);

            if (!$meeting) {
                return $this->error('Meeting not found', 404);
            }

            // Only allow reprocessing of failed or error meetings
            if (!in_array($meeting->processing_status, ['failed', 'needs_review'])) {
                return $this->error('Only failed or needs_review meetings can be reprocessed', 422);
            }

            // Reprocess
            DB::beginTransaction();
            
            $processingResult = $this->meetingProcessor->processMeeting($meeting);
            
            DB::commit();

            $meeting->refresh();

            return response()->json([
                'success' => $processingResult['success'],
                'message' => $processingResult['success'] 
                    ? 'Meeting reprocessed successfully'
                    : 'Reprocessing completed with errors',
                'processing_status' => $meeting->processing_status,
                'has_errors' => $meeting->has_errors,
                'has_warnings' => $meeting->has_warnings,
                'errors' => $processingResult['errors'] ?? [],
                'warnings' => $processingResult['warnings'] ?? [],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to reprocess meeting: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a meeting (admin only, pending meetings only)
     * DELETE /api/vsla-meetings/{id} or /api/vsla/meetings/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin
            if (!$user->isAdmin()) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Only administrators can delete meetings',
                ], 403);
            }

            $meeting = VslaMeeting::find($id);

            if (!$meeting) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Meeting not found',
                ], 404);
            }

            // Only allow deletion of pending meetings
            if ($meeting->processing_status !== 'pending') {
                return response()->json([
                    'code' => 0,
                    'message' => 'Only pending meetings can be deleted',
                ], 422);
            }

            $meeting->delete();

            return response()->json([
                'code' => 1,
                'message' => 'Meeting deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to delete meeting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export meeting to PDF
     * GET /api/vsla/meetings/{id}/export-pdf
     */
    public function exportPdf($id)
    {
        try {
            $meeting = VslaMeeting::with([
                'cycle',
                'group',
                'creator',
                'attendance.member'
            ])->find($id);

            if (!$meeting) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Meeting not found',
                ], 404);
            }

            // Check access permission
            $user = Auth::user();
            if (!$user->isAdmin() && $meeting->group_id !== $user->group_id) {
                return response()->json([
                    'code' => 0,
                    'message' => 'You do not have permission to export this meeting',
                ], 403);
            }

            // Return PDF URL or generate PDF
            // For now, return success with PDF generation info
            return response()->json([
                'code' => 1,
                'message' => 'PDF export functionality coming soon',
                'data' => [
                    'meeting_id' => $meeting->id,
                    'meeting_number' => $meeting->meeting_number,
                    'pdf_url' => null, // Will be implemented later
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to export PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate meeting number for the cycle/group
     * Server-controlled field
     */
    private function generateMeetingNumber($cycleId, $groupId = null)
    {
        $query = VslaMeeting::where('cycle_id', $cycleId);
        
        if ($groupId) {
            $query->where('group_id', $groupId);
        }
        
        $lastMeetingNumber = $query->max('meeting_number') ?? 0;
        
        return $lastMeetingNumber + 1;
    }
}

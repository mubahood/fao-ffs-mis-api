<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\VslaTransactionService;
use App\Models\ProjectTransaction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * VSLA Transaction API Controller
 * 
 * Handles all VSLA financial transaction endpoints using double-entry accounting
 */
class VslaTransactionController extends Controller
{
    protected $transactionService;

    public function __construct(VslaTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Record a savings transaction
     * 
     * POST /api/vsla/transactions/saving
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordSaving(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'project_id' => 'required|integer|exists:projects,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->transactionService->recordSaving($request->all());

        if ($result['success']) {
            return response()->json([
                'code' => 1,
                'message' => $result['message'],
                'data' => $result['data'],
            ], 201);
        }

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => null,
        ], 400);
    }

    /**
     * Disburse a loan to a member
     * 
     * POST /api/vsla/transactions/loan-disbursement
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disburseLoan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'project_id' => 'required|integer|exists:projects,id',
            'amount' => 'required|numeric|min:1',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->transactionService->disburseLoan($request->all());

        if ($result['success']) {
            return response()->json([
                'code' => 1,
                'message' => $result['message'],
                'data' => $result['data'],
            ], 201);
        }

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => null,
        ], 400);
    }

    /**
     * Record a loan repayment
     * 
     * POST /api/vsla/transactions/loan-repayment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordLoanRepayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'project_id' => 'required|integer|exists:projects,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
            'transaction_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->transactionService->recordLoanRepayment($request->all());

        if ($result['success']) {
            return response()->json([
                'code' => 1,
                'message' => $result['message'],
                'data' => $result['data'],
            ], 201);
        }

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => null,
        ], 400);
    }

    /**
     * Record a fine or penalty
     * 
     * POST /api/vsla/transactions/fine
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordFine(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'project_id' => 'required|integer|exists:projects,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
            'transaction_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->transactionService->recordFine($request->all());

        if ($result['success']) {
            return response()->json([
                'code' => 1,
                'message' => $result['message'],
                'data' => $result['data'],
            ], 201);
        }

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => null,
        ], 400);
    }

    /**
     * Get member balance for a specific user
     * 
     * GET /api/vsla/transactions/member-balance/{user_id}
     * 
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberBalance(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $projectId = $request->query('project_id');

            if ($projectId) {
                Project::findOrFail($projectId);
            }

            $balances = ProjectTransaction::calculateUserBalances($userId, $projectId);

            return response()->json([
                'code' => 1,
                'message' => 'Member balance retrieved successfully',
                'data' => [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'project_id' => $projectId,
                    'balances' => $balances,
                    'formatted' => [
                        'savings' => 'UGX ' . number_format($balances['savings'], 2),
                        'loans' => 'UGX ' . number_format($balances['loans'], 2),
                        'fines' => 'UGX ' . number_format($balances['fines'], 2),
                        'interest' => 'UGX ' . number_format($balances['interest'], 2),
                        'net_position' => 'UGX ' . number_format($balances['net_position'], 2),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 404);
        }
    }

    /**
     * Get group balance
     * 
     * GET /api/vsla/transactions/group-balance/{group_id}
     * 
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupBalance(Request $request, $groupId)
    {
        try {
            $projectId = $request->query('project_id');

            if ($projectId) {
                Project::findOrFail($projectId);
            }

            $balances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            // Verify accounting equation
            $verification = $projectId ? ProjectTransaction::verifyAccountingBalance($projectId) : null;

            return response()->json([
                'code' => 1,
                'message' => 'Group balance retrieved successfully',
                'data' => [
                    'group_id' => $groupId,
                    'project_id' => $projectId,
                    'balances' => $balances,
                    'formatted' => [
                        'cash' => 'UGX ' . number_format($balances['cash'], 2),
                        'total_savings' => 'UGX ' . number_format($balances['total_savings'], 2),
                        'loans_outstanding' => 'UGX ' . number_format($balances['loans_outstanding'], 2),
                        'fines_collected' => 'UGX ' . number_format($balances['fines_collected'], 2),
                    ],
                    'accounting_verification' => $verification,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 404);
        }
    }

    /**
     * Get member statement (transaction history)
     * 
     * GET /api/vsla/transactions/member-statement
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberStatement(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            $projectId = $request->query('project_id');
            $accountType = $request->query('account_type');
            $limit = $request->query('limit', 50);

            if (!$userId) {
                return response()->json([
                    'code' => 0,
                    'message' => 'user_id is required',
                    'data' => null,
                ], 422);
            }

            $query = ProjectTransaction::userTransactions($userId)
                ->with(['project', 'contraEntry'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc');

            if ($projectId) {
                $query->where('project_id', $projectId);
            }

            if ($accountType) {
                $query->where('account_type', $accountType);
            }

            $transactions = $query->limit($limit)->get();
            $balances = ProjectTransaction::calculateUserBalances($userId, $projectId);

            return response()->json([
                'code' => 1,
                'message' => 'Member statement retrieved successfully',
                'data' => [
                    'transactions' => $transactions,
                    'balances' => $balances,
                    'count' => $transactions->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Get group statement (transaction history)
     * 
     * GET /api/vsla/transactions/group-statement
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupStatement(Request $request)
    {
        try {
            $groupId = $request->query('group_id');
            $projectId = $request->query('project_id');
            $accountType = $request->query('account_type');
            $limit = $request->query('limit', 50);

            if (!$groupId) {
                return response()->json([
                    'code' => 0,
                    'message' => 'group_id is required',
                    'data' => null,
                ], 422);
            }

            $query = ProjectTransaction::groupTransactions($groupId)
                ->with(['project', 'contraEntry'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc');

            if ($projectId) {
                $query->where('project_id', $projectId);
            }

            if ($accountType) {
                $query->where('account_type', $accountType);
            }

            $transactions = $query->limit($limit)->get();
            $balances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            return response()->json([
                'code' => 1,
                'message' => 'Group statement retrieved successfully',
                'data' => [
                    'transactions' => $transactions,
                    'balances' => $balances,
                    'count' => $transactions->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Get recent transactions for dashboard
     * 
     * GET /api/vsla/transactions/recent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentTransactions(Request $request)
    {
        try {
            $groupId = $request->query('group_id');
            $projectId = $request->query('project_id');
            $type = $request->query('type'); // savings, loans, transactions
            $limit = $request->query('limit', 10);

            if (!$groupId && !$projectId) {
                return response()->json([
                    'code' => 0,
                    'message' => 'group_id or project_id is required',
                    'data' => null,
                ], 422);
            }

            $query = ProjectTransaction::query()
                ->with(['project', 'creator', 'contraEntry'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc');

            if ($projectId) {
                $query->where('project_id', $projectId);
            }

            // Filter by type
            if ($type === 'savings') {
                $query->where('account_type', 'savings')
                    ->where('owner_type', 'user');
            } elseif ($type === 'loans') {
                $query->where('account_type', 'loan');
            } elseif ($type === 'transactions') {
                $query->where('owner_type', 'group');
            }

            $transactions = $query->limit($limit)->get()->map(function ($transaction) {
                $user = User::find($transaction->owner_id);
                
                return [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'amount_signed' => $transaction->amount_signed,
                    'formatted_amount' => 'UGX ' . number_format($transaction->amount, 0),
                    'description' => $transaction->description,
                    'account_type' => $transaction->account_type,
                    'owner_type' => $transaction->owner_type,
                    'owner_name' => $user ? $user->name : 'Group',
                    'transaction_date' => $transaction->transaction_date->format('M d, Y'),
                    'type' => $transaction->type,
                    'is_contra_entry' => $transaction->is_contra_entry,
                ];
            });

            return response()->json([
                'code' => 1,
                'message' => 'Recent transactions retrieved successfully',
                'data' => [
                    'transactions' => $transactions,
                    'count' => $transactions->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Get dashboard summary for VSLA admin
     * 
     * GET /api/vsla/transactions/dashboard-summary
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardSummary(Request $request)
    {
        try {
            $groupId = $request->query('group_id');
            $projectId = $request->query('project_id');

            if (!$groupId) {
                return response()->json([
                    'code' => 0,
                    'message' => 'group_id is required',
                    'data' => null,
                ], 422);
            }

            // Get group balances
            $groupBalances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            // Count members with savings
            $membersWithSavings = ProjectTransaction::where('account_type', 'savings')
                ->where('owner_type', 'user');
            
            if ($projectId) {
                $membersWithSavings->where('project_id', $projectId);
            }
            
            $totalMembers = $membersWithSavings->distinct('owner_id')->count('owner_id');

            // Count active loans
            $activeLoans = ProjectTransaction::where('account_type', 'loan')
                ->where('owner_type', 'user')
                ->where('amount_signed', '>', 0);
            
            if ($projectId) {
                $activeLoans->where('project_id', $projectId);
            }
            
            $activeLoanCount = $activeLoans->distinct('owner_id')->count('owner_id');

            // Get cycle progress
            $cycleProgress = null;
            if ($projectId) {
                $project = Project::find($projectId);
                if ($project) {
                    $start = $project->vsla_cycle_start_date ? \Carbon\Carbon::parse($project->vsla_cycle_start_date) : null;
                    $end = $project->vsla_cycle_end_date ? \Carbon\Carbon::parse($project->vsla_cycle_end_date) : null;
                    
                    if ($start && $end) {
                        $now = now();
                        $totalDays = $start->diffInDays($end);
                        $elapsedDays = $start->diffInDays($now);
                        $percentage = $totalDays > 0 ? min(100, round(($elapsedDays / $totalDays) * 100)) : 0;
                        
                        $cycleProgress = [
                            'start_date' => $start->format('M d, Y'),
                            'end_date' => $end->format('M d, Y'),
                            'elapsed_weeks' => round($elapsedDays / 7),
                            'total_weeks' => round($totalDays / 7),
                            'percentage' => $percentage,
                        ];
                    }
                }
            }

            return response()->json([
                'code' => 1,
                'message' => 'Dashboard summary retrieved successfully',
                'data' => [
                    'overview' => [
                        'total_savings' => $groupBalances['total_savings'],
                        'formatted_savings' => 'UGX ' . number_format($groupBalances['total_savings'], 0),
                        'active_loans' => $activeLoanCount,
                        'loans_outstanding' => $groupBalances['loans_outstanding'],
                        'formatted_loans' => 'UGX ' . number_format($groupBalances['loans_outstanding'], 0),
                        'total_members' => $totalMembers,
                        'cash_balance' => $groupBalances['cash'],
                        'formatted_cash' => 'UGX ' . number_format($groupBalances['cash'], 0),
                        'fines_collected' => $groupBalances['fines_collected'],
                    ],
                    'cycle_progress' => $cycleProgress,
                    'group_id' => $groupId,
                    'project_id' => $projectId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Get all members of a VSLA group
     * 
     * GET /api/vsla/group-members
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $projectId = $request->input('project_id');
            
            // Get project to verify it exists
            $project = Project::find($projectId);
            if (!$project) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Project not found',
                    'data' => null,
                ], 404);
            }

            // Get all users who have shares in this project (investors/members)
            $members = User::whereHas('projectShares', function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->where('status', 1)
                ->select('id', 'name', 'member_code', 'phone_number', 'email')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'code' => 1,
                'message' => 'Group members retrieved successfully',
                'data' => $members,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }
}

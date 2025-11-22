<?php

namespace App\Services;

use App\Models\ProjectTransaction;
use App\Models\Project;
use App\Models\User;
use App\Models\FfsGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * VSLA Transaction Service
 * 
 * Handles all VSLA financial transactions using double-entry accounting.
 * Every transaction creates two entries: a primary entry and a contra entry.
 * 
 * Transaction Types:
 * - Savings: Member saves money
 * - Loan Disbursement: Group lends money to member
 * - Loan Repayment: Member repays loan
 * - Fine/Penalty: Member fined for violations
 * - Interest: Interest charged on loans
 */
class VslaTransactionService
{
    /**
     * Record a savings transaction
     *
     * When a member saves money:
     * - Debit: Member's savings account (+)
     * - Credit: Group's cash account (+)
     *
     * @param array $data
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function recordSaving(array $data)
    {
        try {
            // Validate required fields
            $this->validateSavingData($data);

            $userId = $data['user_id'];
            $projectId = $data['project_id'];
            $amount = (float) $data['amount'];
            $description = $data['description'] ?? 'Savings contribution';
            $transactionDate = $data['transaction_date'] ?? now();

            // Get project and group
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);
            $groupId = $project->ffs_group_id ?? $user->group_id;

            if (!$groupId) {
                throw new \Exception('Group not found for this savings cycle');
            }

            // Validate user is member of the group
            if ($user->group_id != $groupId) {
                throw new \Exception('User is not a member of this VSLA group');
            }

            // Validate amount is positive
            if ($amount <= 0) {
                throw new \Exception('Savings amount must be greater than zero');
            }

            // Create double-entry transactions
            $primaryData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => $amount, // Positive for user savings
                'transaction_date' => $transactionDate,
                'created_by_id' => $userId,
                'description' => $description,
                'type' => 'income',
                'source' => 'share_purchase',
                'owner_type' => 'user',
                'owner_id' => $userId,
                'account_type' => 'savings',
                'is_contra_entry' => false,
            ];

            $contraData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => $amount, // Positive for group cash
                'transaction_date' => $transactionDate,
                'created_by_id' => $userId,
                'description' => "Savings received from {$user->name}",
                'type' => 'income',
                'source' => 'share_purchase',
                'owner_type' => 'group',
                'owner_id' => $groupId,
                'account_type' => 'cash',
            ];

            $transactions = ProjectTransaction::createWithContra($primaryData, $contraData);

            // Calculate new balances
            $userBalances = ProjectTransaction::calculateUserBalances($userId, $projectId);
            $groupBalances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            return [
                'success' => true,
                'message' => 'Savings recorded successfully',
                'data' => [
                    'user_transaction' => $transactions['primary'],
                    'group_transaction' => $transactions['contra'],
                    'user_balances' => $userBalances,
                    'group_balances' => $groupBalances,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('VSLA Savings Recording Error: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Disburse a loan to a member
     *
     * When a member receives a loan:
     * - Debit: Group's cash account (-)
     * - Credit: Member's loan account (+)
     *
     * @param array $data
     * @return array
     */
    public function disburseLoan(array $data)
    {
        try {
            // Validate required fields
            $this->validateLoanDisbursementData($data);

            $userId = $data['user_id'];
            $projectId = $data['project_id'];
            $amount = (float) $data['amount'];
            $description = $data['description'] ?? 'Loan disbursement';
            $transactionDate = $data['transaction_date'] ?? now();
            $interestRate = (float) ($data['interest_rate'] ?? 0);

            // Get project and user
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);
            $groupId = $project->ffs_group_id ?? $user->group_id;

            if (!$groupId) {
                throw new \Exception('Group not found for this savings cycle');
            }

            // Validate user is member
            if ($user->group_id != $groupId) {
                throw new \Exception('User is not a member of this VSLA group');
            }

            // Check if user has sufficient savings for loan
            $userBalances = ProjectTransaction::calculateUserBalances($userId, $projectId);
            $maxLoan = $userBalances['savings'] * ($project->loan_max_multiplier ?? 3);

            if ($amount > $maxLoan) {
                throw new \Exception("Loan amount exceeds maximum allowed (UGX " . number_format($maxLoan) . ")");
            }

            // Check if group has sufficient cash
            $groupBalances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);
            if ($amount > $groupBalances['cash']) {
                throw new \Exception("Insufficient group funds. Available: UGX " . number_format($groupBalances['cash']));
            }

            // Check if user has outstanding loans
            $existingLoan = $userBalances['loans'];
            if ($existingLoan > 0) {
                throw new \Exception("User has an outstanding loan of UGX " . number_format($existingLoan));
            }

            // Create double-entry transactions
            $primaryData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => -$amount, // Negative for group (cash out)
                'transaction_date' => $transactionDate,
                'created_by_id' => auth()->id() ?? $userId,
                'description' => "Loan disbursed to {$user->name}",
                'type' => 'expense',
                'source' => 'project_expense',
                'owner_type' => 'group',
                'owner_id' => $groupId,
                'account_type' => 'cash',
                'is_contra_entry' => false,
            ];

            $contraData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => $amount, // Positive for user (loan received)
                'transaction_date' => $transactionDate,
                'created_by_id' => auth()->id() ?? $userId,
                'description' => $description . ($interestRate > 0 ? " (Interest: {$interestRate}%)" : ''),
                'type' => 'income',
                'source' => 'project_expense',
                'owner_type' => 'user',
                'owner_id' => $userId,
                'account_type' => 'loan',
            ];

            $transactions = ProjectTransaction::createWithContra($primaryData, $contraData);

            // Calculate new balances
            $userBalances = ProjectTransaction::calculateUserBalances($userId, $projectId);
            $groupBalances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            return [
                'success' => true,
                'message' => 'Loan disbursed successfully',
                'data' => [
                    'group_transaction' => $transactions['primary'],
                    'user_transaction' => $transactions['contra'],
                    'user_balances' => $userBalances,
                    'group_balances' => $groupBalances,
                    'interest_rate' => $interestRate,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('VSLA Loan Disbursement Error: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Record a loan repayment
     *
     * When a member repays a loan:
     * - Debit: Member's loan account (-)
     * - Credit: Group's cash account (+)
     *
     * @param array $data
     * @return array
     */
    public function recordLoanRepayment(array $data)
    {
        try {
            // Validate required fields
            $this->validateLoanRepaymentData($data);

            $userId = $data['user_id'];
            $projectId = $data['project_id'];
            $amount = (float) $data['amount'];
            $description = $data['description'] ?? 'Loan repayment';
            $transactionDate = $data['transaction_date'] ?? now();

            // Get project and user
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);
            $groupId = $project->ffs_group_id ?? $user->group_id;

            if (!$groupId) {
                throw new \Exception('Group not found for this savings cycle');
            }

            // Check outstanding loan balance
            $userBalances = ProjectTransaction::calculateUserBalances($userId, $projectId);
            $outstandingLoan = $userBalances['loans'];

            if ($outstandingLoan <= 0) {
                throw new \Exception('No outstanding loan found for this user');
            }

            if ($amount > $outstandingLoan) {
                throw new \Exception("Repayment amount exceeds outstanding loan (UGX " . number_format($outstandingLoan) . ")");
            }

            // Create double-entry transactions
            $primaryData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => -$amount, // Negative for user (loan reduced)
                'transaction_date' => $transactionDate,
                'created_by_id' => $userId,
                'description' => $description,
                'type' => 'expense',
                'source' => 'returns_distribution',
                'owner_type' => 'user',
                'owner_id' => $userId,
                'account_type' => 'loan',
                'is_contra_entry' => false,
            ];

            $contraData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => $amount, // Positive for group (cash in)
                'transaction_date' => $transactionDate,
                'created_by_id' => $userId,
                'description' => "Loan repayment from {$user->name}",
                'type' => 'income',
                'source' => 'returns_distribution',
                'owner_type' => 'group',
                'owner_id' => $groupId,
                'account_type' => 'cash',
            ];

            $transactions = ProjectTransaction::createWithContra($primaryData, $contraData);

            // Calculate new balances
            $userBalances = ProjectTransaction::calculateUserBalances($userId, $projectId);
            $groupBalances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            return [
                'success' => true,
                'message' => 'Loan repayment recorded successfully',
                'data' => [
                    'user_transaction' => $transactions['primary'],
                    'group_transaction' => $transactions['contra'],
                    'user_balances' => $userBalances,
                    'group_balances' => $groupBalances,
                    'remaining_loan' => $userBalances['loans'],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('VSLA Loan Repayment Error: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Record a fine or penalty
     *
     * When a member is fined:
     * - Debit: Member's fine account (-)
     * - Credit: Group's cash account (+)
     *
     * @param array $data
     * @return array
     */
    public function recordFine(array $data)
    {
        try {
            // Validate required fields
            $this->validateFineData($data);

            $userId = $data['user_id'];
            $projectId = $data['project_id'];
            $amount = (float) $data['amount'];
            $description = $data['description'] ?? 'Fine';
            $transactionDate = $data['transaction_date'] ?? now();

            // Get project and user
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);
            $groupId = $project->ffs_group_id ?? $user->group_id;

            if (!$groupId) {
                throw new \Exception('Group not found for this savings cycle');
            }

            // Create double-entry transactions
            $primaryData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => -$amount, // Negative for user (expense)
                'transaction_date' => $transactionDate,
                'created_by_id' => auth()->id() ?? $userId,
                'description' => $description,
                'type' => 'expense',
                'source' => 'project_profit',
                'owner_type' => 'user',
                'owner_id' => $userId,
                'account_type' => 'fine',
                'is_contra_entry' => false,
            ];

            $contraData = [
                'project_id' => $projectId,
                'amount' => $amount,
                'amount_signed' => $amount, // Positive for group (income)
                'transaction_date' => $transactionDate,
                'created_by_id' => auth()->id() ?? $userId,
                'description' => "Fine collected from {$user->name}: {$description}",
                'type' => 'income',
                'source' => 'project_profit',
                'owner_type' => 'group',
                'owner_id' => $groupId,
                'account_type' => 'cash',
            ];

            $transactions = ProjectTransaction::createWithContra($primaryData, $contraData);

            // Calculate new balances
            $userBalances = ProjectTransaction::calculateUserBalances($userId, $projectId);
            $groupBalances = ProjectTransaction::calculateGroupBalances($groupId, $projectId);

            return [
                'success' => true,
                'message' => 'Fine recorded successfully',
                'data' => [
                    'user_transaction' => $transactions['primary'],
                    'group_transaction' => $transactions['contra'],
                    'user_balances' => $userBalances,
                    'group_balances' => $groupBalances,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('VSLA Fine Recording Error: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    // Validation Methods

    private function validateSavingData(array $data)
    {
        if (empty($data['user_id'])) {
            throw new \Exception('User ID is required');
        }
        if (empty($data['project_id'])) {
            throw new \Exception('Project (Savings Cycle) ID is required');
        }
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Valid amount is required');
        }
    }

    private function validateLoanDisbursementData(array $data)
    {
        if (empty($data['user_id'])) {
            throw new \Exception('User ID is required');
        }
        if (empty($data['project_id'])) {
            throw new \Exception('Project (Savings Cycle) ID is required');
        }
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Valid loan amount is required');
        }
    }

    private function validateLoanRepaymentData(array $data)
    {
        if (empty($data['user_id'])) {
            throw new \Exception('User ID is required');
        }
        if (empty($data['project_id'])) {
            throw new \Exception('Project (Savings Cycle) ID is required');
        }
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Valid repayment amount is required');
        }
    }

    private function validateFineData(array $data)
    {
        if (empty($data['user_id'])) {
            throw new \Exception('User ID is required');
        }
        if (empty($data['project_id'])) {
            throw new \Exception('Project (Savings Cycle) ID is required');
        }
        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Valid fine amount is required');
        }
        if (empty($data['description'])) {
            throw new \Exception('Fine description/reason is required');
        }
    }
}

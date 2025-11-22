<?php

/**
 * VSLA Transaction System - Test Script
 * 
 * Tests all transaction types with double-entry accounting verification
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\VslaTransactionService;
use App\Models\ProjectTransaction;
use App\Models\User;
use App\Models\Project;
use App\Models\FfsGroup;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "VSLA TRANSACTION SYSTEM - TEST SCRIPT\n";
echo "========================================\n\n";

// Get test data
$user = User::first();
$project = Project::first();

if (!$user || !$project) {
    echo "❌ ERROR: No users or projects found.\n";
    echo "Please create test data first.\n\n";
    exit(1);
}

// Ensure user has group_id
if (!$user->group_id) {
    $group = FfsGroup::first();
    if ($group) {
        $user->group_id = $group->id;
        $user->save();
        echo "✓ Assigned user to group {$group->id}\n";
    }
}

// Note: Project-group association handled by service layer

echo "\nTest Data:\n";
echo "- User: {$user->name} (ID: {$user->id})\n";
echo "- Project: {$project->name} (ID: {$project->id})\n";
echo "- Group: {$user->group_id}\n\n";

$service = new VslaTransactionService();

// =====================================
// TEST 1: Record Savings
// =====================================
echo "TEST 1: Recording Savings...\n";
echo str_repeat("-", 40) . "\n";

$result1 = $service->recordSaving([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 50000,
    'description' => 'Test savings contribution',
]);

if ($result1['success']) {
    echo "✅ SUCCESS: " . $result1['message'] . "\n";
    echo "   User Savings Balance: UGX " . number_format($result1['data']['user_balances']['savings']) . "\n";
    echo "   Group Cash Balance: UGX " . number_format($result1['data']['group_balances']['cash']) . "\n";
    echo "   Primary Transaction ID: " . $result1['data']['user_transaction']['id'] . "\n";
    echo "   Contra Transaction ID: " . $result1['data']['group_transaction']['id'] . "\n";
} else {
    echo "❌ FAILED: " . $result1['message'] . "\n";
}

echo "\n";

// =====================================
// TEST 2: Disburse Loan
// =====================================
echo "TEST 2: Disbursing Loan...\n";
echo str_repeat("-", 40) . "\n";

// First add more savings to allow loan
$result_extra_savings = $service->recordSaving([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 100000,
    'description' => 'Additional savings for loan eligibility',
]);

echo "✓ Added extra savings for loan eligibility\n";

$result2 = $service->disburseLoan([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 100000,
    'interest_rate' => 10,
    'description' => 'Test loan disbursement',
]);

if ($result2['success']) {
    echo "✅ SUCCESS: " . $result2['message'] . "\n";
    echo "   Loan Amount: UGX " . number_format(100000) . "\n";
    echo "   User Loan Balance: UGX " . number_format($result2['data']['user_balances']['loans']) . "\n";
    echo "   Group Cash Balance: UGX " . number_format($result2['data']['group_balances']['cash']) . "\n";
    echo "   Interest Rate: " . $result2['data']['interest_rate'] . "%\n";
} else {
    echo "❌ FAILED: " . $result2['message'] . "\n";
}

echo "\n";

// =====================================
// TEST 3: Loan Repayment
// =====================================
echo "TEST 3: Recording Loan Repayment...\n";
echo str_repeat("-", 40) . "\n";

$result3 = $service->recordLoanRepayment([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 50000,
    'description' => 'Partial loan repayment',
]);

if ($result3['success']) {
    echo "✅ SUCCESS: " . $result3['message'] . "\n";
    echo "   Repayment Amount: UGX " . number_format(50000) . "\n";
    echo "   Remaining Loan: UGX " . number_format($result3['data']['remaining_loan']) . "\n";
    echo "   Group Cash Updated: UGX " . number_format($result3['data']['group_balances']['cash']) . "\n";
} else {
    echo "❌ FAILED: " . $result3['message'] . "\n";
}

echo "\n";

// =====================================
// TEST 4: Record Fine
// =====================================
echo "TEST 4: Recording Fine...\n";
echo str_repeat("-", 40) . "\n";

$result4 = $service->recordFine([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 5000,
    'description' => 'Late meeting attendance fine',
]);

if ($result4['success']) {
    echo "✅ SUCCESS: " . $result4['message'] . "\n";
    echo "   Fine Amount: UGX " . number_format(5000) . "\n";
    echo "   User Fines Balance: UGX " . number_format($result4['data']['user_balances']['fines']) . "\n";
} else {
    echo "❌ FAILED: " . $result4['message'] . "\n";
}

echo "\n";

// =====================================
// VERIFICATION: Accounting Equation
// =====================================
echo "VERIFICATION: Accounting Equation\n";
echo str_repeat("-", 40) . "\n";

$verification = ProjectTransaction::verifyAccountingBalance($project->id);

echo "Total Debits:  UGX " . number_format($verification['total_debits']) . "\n";
echo "Total Credits: UGX " . number_format($verification['total_credits']) . "\n";
echo "Difference:    UGX " . number_format($verification['difference']) . "\n";

if ($verification['is_balanced']) {
    echo "✅ ACCOUNTING EQUATION BALANCED!\n";
} else {
    echo "❌ WARNING: Accounting equation not balanced!\n";
}

echo "\n";

// =====================================
// SUMMARY: Final Balances
// =====================================
echo "FINAL BALANCES SUMMARY\n";
echo str_repeat("-", 40) . "\n";

$userBalances = ProjectTransaction::calculateUserBalances($user->id, $project->id);
$groupBalances = ProjectTransaction::calculateGroupBalances($user->group_id, $project->id);

echo "Member: {$user->name}\n";
echo "  Savings:      UGX " . number_format($userBalances['savings']) . "\n";
echo "  Loans:        UGX " . number_format($userBalances['loans']) . "\n";
echo "  Fines:        UGX " . number_format($userBalances['fines']) . "\n";
echo "  Net Position: UGX " . number_format($userBalances['net_position']) . "\n";

echo "\nGroup: ID {$user->group_id}\n";
echo "  Cash Balance:       UGX " . number_format($groupBalances['cash']) . "\n";
echo "  Total Savings:      UGX " . number_format($groupBalances['total_savings']) . "\n";
echo "  Loans Outstanding:  UGX " . number_format($groupBalances['loans_outstanding']) . "\n";
echo "  Fines Collected:    UGX " . number_format($groupBalances['fines_collected']) . "\n";

echo "\n";

// =====================================
// TRANSACTION COUNT
// =====================================
$transactionCount = ProjectTransaction::where('project_id', $project->id)->count();
echo "Total Transactions Created: {$transactionCount}\n";
echo "(Expected: 10 - 5 operations × 2 entries each)\n\n";

echo "========================================\n";
echo "ALL TESTS COMPLETED!\n";
echo "========================================\n\n";

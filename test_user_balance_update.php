<?php

/**
 * Test script to verify user balance auto-update functionality
 * 
 * Run with: php test_user_balance_update.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use App\Models\ProjectTransaction;
use App\Services\VslaTransactionService;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==============================================\n";
echo "USER BALANCE AUTO-UPDATE TEST\n";
echo "==============================================\n\n";

// Find a test user and project
$user = User::where('user_type', 'Customer')->first();
$project = Project::where('status', 'active')->first();

if (!$user || !$project) {
    echo "❌ ERROR: Need at least one customer and one active project\n";
    exit(1);
}

echo "Test User: {$user->name} (ID: {$user->id})\n";
echo "Test Project: {$project->name} (ID: {$project->id})\n\n";

// Store initial balance
$initialBalance = $user->balance;
$initialLoanBalance = $user->loan_balance;

echo "INITIAL STATE:\n";
echo "- Balance: UGX " . number_format($initialBalance, 2) . "\n";
echo "- Loan Balance: UGX " . number_format($initialLoanBalance, 2) . "\n\n";

// Assign user to a group if not already
if (!$user->group_id) {
    $group = \App\Models\FfsGroup::first();
    if ($group) {
        $user->group_id = $group->id;
        $user->save();
        echo "✓ Assigned user to group {$group->id}\n\n";
    }
}

// Assign project to group if needed
if (!$project->ffs_group_id && $user->group_id) {
    $project->ffs_group_id = $user->group_id;
    $project->save();
    echo "✓ Assigned project to group {$user->group_id}\n\n";
}

$service = new VslaTransactionService();

echo "==============================================\n";
echo "TEST 1: Creating Savings Transaction\n";
echo "==============================================\n";

$result1 = $service->recordSaving([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 50000,
    'description' => 'Test savings contribution',
]);

if ($result1['success']) {
    echo "✅ Savings recorded successfully\n";
    
    // Reload user to get updated balance
    $user->refresh();
    
    echo "AFTER SAVINGS:\n";
    echo "- Balance: UGX " . number_format($user->balance, 2) . " (change: +" . number_format($user->balance - $initialBalance, 2) . ")\n";
    echo "- Loan Balance: UGX " . number_format($user->loan_balance, 2) . "\n\n";
} else {
    echo "❌ FAILED: " . $result1['message'] . "\n\n";
}

echo "==============================================\n";
echo "TEST 2: Recording Fine\n";
echo "==============================================\n";

$balanceBeforeFine = $user->balance;

$result2 = $service->recordFine([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 5000,
    'description' => 'Test late attendance fine',
]);

if ($result2['success']) {
    echo "✅ Fine recorded successfully\n";
    
    $user->refresh();
    
    echo "AFTER FINE:\n";
    echo "- Balance: UGX " . number_format($user->balance, 2) . " (change: " . number_format($user->balance - $balanceBeforeFine, 2) . ")\n";
    echo "- Loan Balance: UGX " . number_format($user->loan_balance, 2) . "\n\n";
} else {
    echo "❌ FAILED: " . $result2['message'] . "\n\n";
}

echo "==============================================\n";
echo "TEST 3: Disbursing Loan\n";
echo "==============================================\n";

$balanceBeforeLoan = $user->balance;

$result3 = $service->disburseLoan([
    'user_id' => $user->id,
    'project_id' => $project->id,
    'amount' => 30000,
    'description' => 'Test loan disbursement',
    'interest_rate' => 10,
]);

if ($result3['success']) {
    echo "✅ Loan disbursed successfully\n";
    
    $user->refresh();
    
    echo "AFTER LOAN:\n";
    echo "- Balance: UGX " . number_format($user->balance, 2) . " (change: " . number_format($user->balance - $balanceBeforeLoan, 2) . ")\n";
    echo "- Loan Balance: UGX " . number_format($user->loan_balance, 2) . " (should be 30000)\n\n";
} else {
    echo "❌ FAILED: " . $result3['message'] . "\n\n";
}

echo "==============================================\n";
echo "VERIFICATION: Calculate vs Stored Balance\n";
echo "==============================================\n";

// Calculate balance using the model method
$calculated = ProjectTransaction::calculateUserBalances($user->id, null);

echo "Calculated from Transactions:\n";
echo "- Savings: UGX " . number_format($calculated['savings'], 2) . "\n";
echo "- Loans: UGX " . number_format($calculated['loans'], 2) . "\n";
echo "- Fines: UGX " . number_format($calculated['fines'], 2) . "\n";
echo "- Net Position: UGX " . number_format($calculated['net_position'], 2) . "\n\n";

echo "Stored in User Table:\n";
echo "- Balance: UGX " . number_format($user->balance, 2) . "\n";
echo "- Loan Balance: UGX " . number_format($user->loan_balance, 2) . "\n\n";

// Verify they match
$expectedBalance = $calculated['savings'] - abs($calculated['fines']);
$expectedLoanBalance = abs($calculated['loans']);

$balanceMatch = abs($user->balance - $expectedBalance) < 0.01;
$loanMatch = abs($user->loan_balance - $expectedLoanBalance) < 0.01;

if ($balanceMatch && $loanMatch) {
    echo "✅ SUCCESS: Stored balances match calculated balances!\n";
} else {
    echo "❌ ERROR: Mismatch detected!\n";
    echo "  Expected Balance: UGX " . number_format($expectedBalance, 2) . "\n";
    echo "  Actual Balance: UGX " . number_format($user->balance, 2) . "\n";
    echo "  Expected Loan Balance: UGX " . number_format($expectedLoanBalance, 2) . "\n";
    echo "  Actual Loan Balance: UGX " . number_format($user->loan_balance, 2) . "\n";
}

echo "\n==============================================\n";
echo "TEST COMPLETE\n";
echo "==============================================\n";

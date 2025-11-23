<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\ProjectTransaction;
use Illuminate\Console\Command;

class RecalculateUserBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:recalculate-balances {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate user balance and loan_balance fields from ProjectTransaction records';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            // Recalculate single user
            $user = User::find($userId);
            
            if (!$user) {
                $this->error("User with ID {$userId} not found");
                return 1;
            }

            $this->info("Recalculating balance for user: {$user->name} (ID: {$userId})");
            
            $beforeBalance = $user->balance;
            $beforeLoanBalance = $user->loan_balance;
            
            ProjectTransaction::updateUserAccountBalance($userId, null);
            
            $user->refresh();
            
            $this->info("✓ Complete!");
            $this->table(
                ['Field', 'Before', 'After', 'Change'],
                [
                    [
                        'Balance',
                        number_format($beforeBalance, 2),
                        number_format($user->balance, 2),
                        number_format($user->balance - $beforeBalance, 2)
                    ],
                    [
                        'Loan Balance',
                        number_format($beforeLoanBalance, 2),
                        number_format($user->loan_balance, 2),
                        number_format($user->loan_balance - $beforeLoanBalance, 2)
                    ],
                ]
            );
            
        } else {
            // Recalculate all users who have transactions
            $this->info("Recalculating balances for all users with transactions...");
            
            // Get all users who have ProjectTransactions
            $userIds = ProjectTransaction::where('owner_type', 'user')
                ->distinct()
                ->pluck('owner_id');
            
            if ($userIds->isEmpty()) {
                $this->info("No users with transactions found.");
                return 0;
            }
            
            $bar = $this->output->createProgressBar($userIds->count());
            $bar->start();
            
            $updated = 0;
            $errors = 0;
            $totalBalanceChange = 0;
            $totalLoanChange = 0;
            
            foreach ($userIds as $uid) {
                try {
                    $user = User::find($uid);
                    if (!$user) {
                        $errors++;
                        continue;
                    }
                    
                    $beforeBalance = $user->balance;
                    $beforeLoanBalance = $user->loan_balance;
                    
                    ProjectTransaction::updateUserAccountBalance($uid, null);
                    
                    $user->refresh();
                    
                    $totalBalanceChange += abs($user->balance - $beforeBalance);
                    $totalLoanChange += abs($user->loan_balance - $beforeLoanBalance);
                    
                    $updated++;
                } catch (\Exception $e) {
                    $this->error("\nError updating user {$uid}: " . $e->getMessage());
                    $errors++;
                }
                
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            $this->info("✓ Recalculation complete!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Users processed', $updated],
                    ['Errors', $errors],
                    ['Total balance adjustments', 'UGX ' . number_format($totalBalanceChange, 2)],
                    ['Total loan adjustments', 'UGX ' . number_format($totalLoanChange, 2)],
                ]
            );
        }

        return 0;
    }
}

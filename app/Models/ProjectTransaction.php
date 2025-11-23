<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'amount',
        'transaction_date',
        'created_by_id',
        'description',
        'type',
        'source',
        'related_share_id',
        // VSLA Double-Entry Fields
        'owner_type',
        'owner_id',
        'contra_entry_id',
        'account_type',
        'is_contra_entry',
        'amount_signed',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'amount_signed' => 'decimal:2',
        'is_contra_entry' => 'boolean',
    ];

    protected $appends = [
        'type_label',
        'source_label',
        'formatted_amount',
    ];

    // Boot method - Model Events
    protected static function boot()
    {
        parent::boot();

        // After creating a transaction, update project computed fields
        static::created(function ($transaction) {
            if ($transaction->project_id) {
                $project = Project::find($transaction->project_id);
                if ($project) {
                    $project->recalculateFromTransactions();
                }
            }
            
            // Update user account balance if this is a user transaction
            if ($transaction->owner_type === 'user' && $transaction->owner_id) {
                static::updateUserAccountBalance($transaction->owner_id, $transaction->project_id);
            }
        });

        // After updating a transaction, update project computed fields
        static::updated(function ($transaction) {
            if ($transaction->project_id) {
                $project = Project::find($transaction->project_id);
                if ($project) {
                    $project->recalculateFromTransactions();
                }
            }
            
            // Update user account balance if this is a user transaction
            if ($transaction->owner_type === 'user' && $transaction->owner_id) {
                static::updateUserAccountBalance($transaction->owner_id, $transaction->project_id);
            }
        });

        // After deleting a transaction, update project computed fields
        static::deleted(function ($transaction) {
            if ($transaction->project_id) {
                $project = Project::find($transaction->project_id);
                if ($project) {
                    $project->recalculateFromTransactions();
                }
            }
            
            // Update user account balance if this is a user transaction
            if ($transaction->owner_type === 'user' && $transaction->owner_id) {
                static::updateUserAccountBalance($transaction->owner_id, $transaction->project_id);
            }
        });

        // After restoring a soft-deleted transaction, update project
        static::restored(function ($transaction) {
            if ($transaction->project_id) {
                $project = Project::find($transaction->project_id);
                if ($project) {
                    $project->recalculateFromTransactions();
                }
            }
            
            // Update user account balance if this is a user transaction
            if ($transaction->owner_type === 'user' && $transaction->owner_id) {
                static::updateUserAccountBalance($transaction->owner_id, $transaction->project_id);
            }
        });
    }

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function relatedShare()
    {
        return $this->belongsTo(ProjectShare::class, 'related_share_id');
    }

    // VSLA Relationships
    public function owner()
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    public function contraEntry()
    {
        return $this->belongsTo(ProjectTransaction::class, 'contra_entry_id');
    }

    public function linkedContraEntries()
    {
        return $this->hasMany(ProjectTransaction::class, 'contra_entry_id');
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return ucfirst($this->type);
    }

    public function getSourceLabelAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->source));
    }

    public function getFormattedAmountAttribute()
    {
        $prefix = $this->type === 'income' ? '+' : '-';
        return $prefix . number_format($this->amount, 2);
    }

    // Scopes
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    // VSLA Scopes
    public function scopeUserTransactions($query, $userId)
    {
        return $query->where('owner_type', 'user')
            ->where('owner_id', $userId);
    }

    public function scopeGroupTransactions($query, $groupId)
    {
        return $query->where('owner_type', 'group')
            ->where('owner_id', $groupId);
    }

    public function scopeByAccountType($query, $accountType)
    {
        return $query->where('account_type', $accountType);
    }

    public function scopeContraEntriesOnly($query)
    {
        return $query->where('is_contra_entry', true);
    }

    public function scopePrimaryEntriesOnly($query)
    {
        return $query->where('is_contra_entry', false);
    }

    // VSLA Helper Methods

    /**
     * Create a transaction with its contra entry (double-entry accounting)
     *
     * @param array $primaryData Primary transaction data
     * @param array $contraData Contra transaction data
     * @return array ['primary' => ProjectTransaction, 'contra' => ProjectTransaction]
     */
    public static function createWithContra(array $primaryData, array $contraData)
    {
        DB::beginTransaction();

        try {
            // Create primary transaction
            $primary = self::create($primaryData);

            // Add contra_entry_id to contra data and mark as contra
            $contraData['contra_entry_id'] = $primary->id;
            $contraData['is_contra_entry'] = true;

            // Create contra transaction
            $contra = self::create($contraData);

            // Update primary with contra_entry_id
            $primary->contra_entry_id = $contra->id;
            $primary->save();

            DB::commit();

            return [
                'primary' => $primary->fresh(),
                'contra' => $contra->fresh(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate balance for a specific owner and account type
     *
     * @param string $ownerType 'user' or 'group'
     * @param int $ownerId User ID or Group ID
     * @param string $accountType 'savings', 'loan', 'cash', 'fine', etc.
     * @param int|null $projectId Optional project filter
     * @return float
     */
    public static function calculateBalance($ownerType, $ownerId, $accountType, $projectId = null)
    {
        $query = self::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('account_type', $accountType);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return (float) $query->sum('amount_signed') ?? 0;
    }

    /**
     * Calculate all balances for a user
     *
     * @param int $userId
     * @param int|null $projectId
     * @return array
     */
    public static function calculateUserBalances($userId, $projectId = null)
    {
        $savings = self::calculateBalance('user', $userId, 'savings', $projectId);
        $loans = self::calculateBalance('user', $userId, 'loan', $projectId);
        $fines = self::calculateBalance('user', $userId, 'fine', $projectId);
        $interest = self::calculateBalance('user', $userId, 'interest', $projectId);

        return [
            'savings' => $savings,
            'loans' => abs($loans), // Show as positive liability
            'fines' => abs($fines),
            'interest' => abs($interest),
            'net_position' => $savings - abs($loans) - abs($fines),
        ];
    }

    /**
     * Calculate all balances for a group
     *
     * @param int $groupId
     * @param int|null $projectId
     * @return array
     */
    public static function calculateGroupBalances($groupId, $projectId = null)
    {
        $cash = self::calculateBalance('group', $groupId, 'cash', $projectId);

        // Calculate total savings collected (from user accounts)
        $query = self::where('owner_type', 'user')
            ->where('account_type', 'savings');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $totalSavings = (float) $query->sum('amount_signed') ?? 0;

        // Calculate total loans outstanding (positive loan balances)
        $loansQuery = self::where('owner_type', 'user')
            ->where('account_type', 'loan');

        if ($projectId) {
            $loansQuery->where('project_id', $projectId);
        }

        $loansOutstanding = (float) $loansQuery->sum('amount_signed') ?? 0;

        return [
            'cash' => $cash,
            'total_savings' => $totalSavings,
            'loans_outstanding' => abs($loansOutstanding),
            'fines_collected' => abs(self::calculateBalance('group', $groupId, 'fine', $projectId)),
        ];
    }

    /**
     * Verify accounting equation balance
     *
     * @param int $projectId
     * @return array
     */
    public static function verifyAccountingBalance($projectId)
    {
        $totalDebits = self::where('project_id', $projectId)
            ->where('amount_signed', '>', 0)
            ->sum('amount_signed');

        $totalCredits = abs(self::where('project_id', $projectId)
            ->where('amount_signed', '<', 0)
            ->sum('amount_signed'));

        $difference = abs($totalDebits - $totalCredits);
        $isBalanced = $difference < 0.01; // Allow for floating point rounding

        return [
            'total_debits' => (float) $totalDebits,
            'total_credits' => (float) $totalCredits,
            'difference' => $difference,
            'is_balanced' => $isBalanced,
        ];
    }

    /**
     * Update user's balance and loan_balance fields in users table
     * This keeps the stored balance in sync with the calculated balance from transactions
     *
     * @param int $userId
     * @param int|null $projectId Optional project filter (null = all projects)
     * @return void
     */
    public static function updateUserAccountBalance($userId, $projectId = null)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return;
            }

            // Calculate balances from all user's transactions
            $balances = self::calculateUserBalances($userId, $projectId);

            // Update user's balance fields
            // Balance = savings - fines (net position excluding loans)
            $user->balance = $balances['savings'] - abs($balances['fines']);
            
            // Loan balance = outstanding loan amount (always show as positive)
            $user->loan_balance = abs($balances['loans']);

            $user->save();
        } catch (\Exception $e) {
            Log::error('Failed to update user account balance', [
                'user_id' => $userId,
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProjectTransaction;

class Disbursement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'amount',
        'disbursement_date',
        'description',
        'created_by_id',
    ];

    protected $casts = [
        'disbursement_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected $appends = [
        'formatted_amount',
        'formatted_date',
    ];

    // Boot method - Model Events
    protected static function boot()
    {
        parent::boot();

        // Validate before creating disbursement
        static::creating(function ($disbursement) {
            self::validateDisbursement($disbursement);
        });

        // After creating a disbursement, create account transactions for investors
        static::created(function ($disbursement) {
            // 1. Create negative project transaction to deduct disbursed amount from project
            ProjectTransaction::create([
                'project_id' => $disbursement->project_id,
                'amount' => $disbursement->amount,
                'transaction_date' => $disbursement->disbursement_date,
                'type' => 'expense',
                'source' => 'disbursement',
                'description' => 'Profit disbursement to investors: ' . $disbursement->description,
                'created_by_id' => $disbursement->created_by_id,
            ]);
            
            // 2. Distribute to investors' accounts
            $disbursement->distributeToInvestors();
            
            // 3. Recalculate project totals
            if ($disbursement->project_id) {
                $project = Project::find($disbursement->project_id);
                if ($project) {
                    $project->recalculateFromTransactions();
                }
            }
        });

        // After deleting a disbursement, delete related account transactions
        // and update project totals
        static::deleting(function ($disbursement) {
            // Delete related account transactions
            AccountTransaction::where('related_disbursement_id', $disbursement->id)->delete();
        });

        static::deleted(function ($disbursement) {
            if ($disbursement->project_id) {
                $project = Project::find($disbursement->project_id);
                if ($project) {
                    $project->recalculateFromTransactions();
                }
            }
        });
    }

    /**
     * Validate disbursement before creation
     */
    protected static function validateDisbursement($disbursement)
    {
        $project = Project::find($disbursement->project_id);
        
        if (!$project) {
            throw new \Exception("Project not found.", 1);
        }

        // Calculate available funds for disbursement (income - expenses)
        $income = ProjectTransaction::where('project_id', $project->id)
            ->where('type', 'income')
            ->sum('amount');
        $expenses = ProjectTransaction::where('project_id', $project->id)
            ->where('type', 'expense')
            ->sum('amount');
        $availableFunds = $income - $expenses;
        
        if ($availableFunds <= 0) {
            throw new \Exception("No funds available for disbursement. Project has no net profit.", 1);
        }

        if ($disbursement->amount > $availableFunds) {
            throw new \Exception(
                "Insufficient funds for disbursement. Available: UGX " . number_format($availableFunds, 2) . 
                ", Requested: UGX " . number_format($disbursement->amount, 2), 
                1
            );
        }

        // Check if project has investors
        $totalShares = $project->shares()->sum('number_of_shares');
        if ($totalShares <= 0) {
            throw new \Exception("Cannot disburse funds. Project has no investors.", 1);
        }
    }

    /**
     * Distribute disbursement amount to investors proportionally
     */
    public function distributeToInvestors()
    {
        $project = $this->project;
        
        if (!$project) {
            return;
        }

        // Get all investors and their shares
        $investors = $project->shares()
            ->selectRaw('investor_id, SUM(number_of_shares) as total_shares')
            ->groupBy('investor_id')
            ->get();

        $totalProjectShares = $project->shares()->sum('number_of_shares');

        if ($totalProjectShares <= 0) {
            return;
        }

        // Create account transaction for each investor
        foreach ($investors as $investor) {
            $investorSharePercentage = ($investor->total_shares / $totalProjectShares);
            $investorAmount = $this->amount * $investorSharePercentage;

            AccountTransaction::create([
                'user_id' => $investor->investor_id,
                'amount' => $investorAmount,
                'transaction_date' => $this->disbursement_date,
                'description' => 'Project Returns Distribution: ' . $project->title . ' - ' . $this->description,
                'source' => 'disbursement',
                'related_disbursement_id' => $this->id,
                'created_by_id' => $this->created_by_id,
            ]);
        }
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

    public function accountTransactions()
    {
        return $this->hasMany(AccountTransaction::class, 'related_disbursement_id');
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return 'UGX ' . number_format($this->amount, 2);
    }

    public function getFormattedDateAttribute()
    {
        return $this->disbursement_date->format('d M Y');
    }

    // Scopes
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('disbursement_date', [$startDate, $endDate]);
    }
}

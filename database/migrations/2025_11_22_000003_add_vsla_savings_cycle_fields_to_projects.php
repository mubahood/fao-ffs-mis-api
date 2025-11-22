<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add VSLA Savings Cycle Fields to Projects Table
 * 
 * Purpose: Extend projects table to support VSLA savings cycles with loan settings.
 * In the system architecture:
 * - Frontend: Users see "Savings Cycle"
 * - Backend: Stored as "Project"
 * 
 * This maintains system consistency while providing VSLA-specific functionality.
 * 
 * New Fields:
 * - is_vsla_cycle: Marks project as VSLA savings cycle
 * - group_id: Links cycle to VSLA group
 * - cycle_name: Name of the savings cycle
 * - share_value: Amount per share contribution
 * - meeting_frequency: How often group meets
 * - loan_interest_rate: Primary interest rate for loans
 * - interest_frequency: Weekly or Monthly interest calculation
 * - weekly_loan_interest_rate: Interest rate if weekly
 * - monthly_loan_interest_rate: Interest rate if monthly
 * - minimum_loan_amount: Minimum loan that can be given
 * - maximum_loan_multiple: Maximum loan as multiple of shares
 * - late_payment_penalty: Penalty percentage for late payments
 * - is_active_cycle: Only one active cycle per group at a time
 */
class AddVslaSavingsCycleFieldsToProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            // VSLA Cycle Identification
            $table->enum('is_vsla_cycle', ['Yes', 'No'])
                ->default('No')
                ->after('status')
                ->comment('Is this project a VSLA savings cycle?');
            
            $table->bigInteger('group_id')
                ->unsigned()
                ->nullable()
                ->after('is_vsla_cycle')
                ->comment('FFS Group ID (VSLA group) this cycle belongs to');
            
            $table->string('cycle_name', 200)
                ->nullable()
                ->after('group_id')
                ->comment('Name of the savings cycle');
            
            // Share and Meeting Configuration
            $table->decimal('share_value', 15, 2)
                ->nullable()
                ->after('cycle_name')
                ->comment('Amount each member contributes per meeting (share value)');
            
            $table->enum('meeting_frequency', ['Weekly', 'Bi-weekly', 'Monthly'])
                ->nullable()
                ->after('share_value')
                ->comment('How often the VSLA group meets');
            
            // Loan Interest Configuration
            $table->decimal('loan_interest_rate', 5, 2)
                ->nullable()
                ->after('meeting_frequency')
                ->comment('Primary loan interest rate percentage');
            
            $table->enum('interest_frequency', ['Weekly', 'Monthly'])
                ->nullable()
                ->after('loan_interest_rate')
                ->comment('How often interest is calculated');
            
            $table->decimal('weekly_loan_interest_rate', 5, 2)
                ->nullable()
                ->after('interest_frequency')
                ->comment('Interest rate if calculated weekly (%)');
            
            $table->decimal('monthly_loan_interest_rate', 5, 2)
                ->nullable()
                ->after('weekly_loan_interest_rate')
                ->comment('Interest rate if calculated monthly (%)');
            
            // Loan Amount Limits
            $table->decimal('minimum_loan_amount', 15, 2)
                ->nullable()
                ->after('monthly_loan_interest_rate')
                ->comment('Minimum loan amount that can be given');
            
            $table->integer('maximum_loan_multiple')
                ->unsigned()
                ->nullable()
                ->after('minimum_loan_amount')
                ->comment('Maximum loan as multiple of member shares (e.g., 10x, 20x)');
            
            $table->decimal('late_payment_penalty', 5, 2)
                ->nullable()
                ->after('maximum_loan_multiple')
                ->comment('Penalty percentage for late loan payments');
            
            // Active Cycle Tracking
            $table->enum('is_active_cycle', ['Yes', 'No'])
                ->default('No')
                ->after('late_payment_penalty')
                ->comment('Is this the active savings cycle for the group?');
            
            // Add indexes for performance
            $table->index('is_vsla_cycle');
            $table->index('group_id');
            $table->index('is_active_cycle');
            $table->index(['group_id', 'is_active_cycle']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'is_active_cycle']);
            $table->dropIndex(['is_active_cycle']);
            $table->dropIndex(['group_id']);
            $table->dropIndex(['is_vsla_cycle']);
            
            $table->dropColumn([
                'is_vsla_cycle',
                'group_id',
                'cycle_name',
                'share_value',
                'meeting_frequency',
                'loan_interest_rate',
                'interest_frequency',
                'weekly_loan_interest_rate',
                'monthly_loan_interest_rate',
                'minimum_loan_amount',
                'maximum_loan_multiple',
                'late_payment_penalty',
                'is_active_cycle'
            ]);
        });
    }
}

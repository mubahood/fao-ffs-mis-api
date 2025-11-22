<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVslaDoubleEntryFieldsToProjectTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('project_transactions', function (Blueprint $table) {
            // Owner tracking - polymorphic relationship
            $table->string('owner_type', 10)->nullable()->after('source')
                ->comment('Owner type: user or group');
            $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type')
                ->comment('User ID or Group ID based on owner_type');
            
            // Contra entry linking for double-entry accounting
            $table->unsignedBigInteger('contra_entry_id')->nullable()->after('owner_id')
                ->comment('Links to paired contra transaction');
            
            // Account classification
            $table->string('account_type', 20)->nullable()->after('contra_entry_id')
                ->comment('Account type: savings, loan, cash, fine, interest, penalty');
            
            // Flags and signed amount
            $table->boolean('is_contra_entry')->default(false)->after('account_type')
                ->comment('Flag indicating if this is a contra entry');
            $table->decimal('amount_signed', 15, 2)->nullable()->after('is_contra_entry')
                ->comment('Signed amount for balance calculations (+/-)');
            
            // Indexes for performance
            $table->index(['owner_type', 'owner_id'], 'idx_owner');
            $table->index('contra_entry_id', 'idx_contra');
            $table->index('account_type', 'idx_account_type');
            $table->index(['project_id', 'owner_type', 'owner_id'], 'idx_project_owner');
            
            // Foreign key for contra entry (self-referencing)
            $table->foreign('contra_entry_id', 'fk_contra_entry')
                ->references('id')->on('project_transactions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('project_transactions', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign('fk_contra_entry');
            
            // Drop indexes
            $table->dropIndex('idx_owner');
            $table->dropIndex('idx_contra');
            $table->dropIndex('idx_account_type');
            $table->dropIndex('idx_project_owner');
            
            // Drop columns
            $table->dropColumn([
                'owner_type',
                'owner_id',
                'contra_entry_id',
                'account_type',
                'is_contra_entry',
                'amount_signed',
            ]);
        });
    }
}

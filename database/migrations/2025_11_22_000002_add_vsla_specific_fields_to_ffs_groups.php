<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add VSLA-Specific Fields to FFS Groups Table
 * 
 * Purpose: Extend groups table to support VSLA-specific information
 * including establishment date, estimated members, and core member roles.
 * 
 * New Fields:
 * - establishment_date: When the VSLA group was established
 * - estimated_members: Initial estimate of group size
 * - secretary_id: User ID of group secretary
 * - treasurer_id: User ID of group treasurer
 * - admin_id: User ID of group admin (chairperson)
 */
class AddVslaSpecificFieldsToFfsGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ffs_groups', function (Blueprint $table) {
            // VSLA establishment and membership tracking
            $table->date('establishment_date')
                ->nullable()
                ->after('registration_date')
                ->comment('Date when VSLA group was established');
            
            $table->integer('estimated_members')
                ->unsigned()
                ->nullable()
                ->after('pwd_members')
                ->comment('Estimated number of members at formation');
            
            // Core member role assignments
            $table->bigInteger('admin_id')
                ->unsigned()
                ->nullable()
                ->after('facilitator_id')
                ->comment('User ID of group administrator (chairperson)');
            
            $table->bigInteger('secretary_id')
                ->unsigned()
                ->nullable()
                ->after('admin_id')
                ->comment('User ID of group secretary');
            
            $table->bigInteger('treasurer_id')
                ->unsigned()
                ->nullable()
                ->after('secretary_id')
                ->comment('User ID of group treasurer');
            
            // Text fields for better location specificity
            $table->string('subcounty_text', 100)
                ->nullable()
                ->after('subcounty_id')
                ->comment('Subcounty name (text entry)');
            
            $table->string('parish_text', 100)
                ->nullable()
                ->after('parish_id')
                ->comment('Parish name (text entry)');
            
            // Add indexes
            $table->index('admin_id');
            $table->index('secretary_id');
            $table->index('treasurer_id');
            $table->index('establishment_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ffs_groups', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['secretary_id']);
            $table->dropIndex(['treasurer_id']);
            $table->dropIndex(['establishment_date']);
            
            $table->dropColumn([
                'establishment_date',
                'estimated_members',
                'admin_id',
                'secretary_id',
                'treasurer_id',
                'subcounty_text',
                'parish_text'
            ]);
        });
    }
}

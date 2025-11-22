<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add VSLA Onboarding Fields to Users Table
 * 
 * Purpose: Support the VSLA group onboarding process with role tracking
 * and onboarding progress management.
 * 
 * New Fields:
 * - is_group_admin: Tracks if user is a VSLA group administrator
 * - is_group_secretary: Tracks if user is a VSLA group secretary
 * - is_group_treasurer: Tracks if user is a VSLA group treasurer  
 * - onboarding_step: Tracks current onboarding progress
 * - onboarding_completed_at: Timestamp when onboarding was completed
 * - last_onboarding_step_at: Last time user progressed in onboarding
 */
class AddVslaOnboardingFieldsToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // VSLA Role Management Fields
            $table->enum('is_group_admin', ['Yes', 'No'])
                ->default('No')
                ->after('status')
                ->comment('Is this user a VSLA group administrator?');
            
            $table->enum('is_group_secretary', ['Yes', 'No'])
                ->default('No')
                ->after('is_group_admin')
                ->comment('Is this user a VSLA group secretary?');
            
            $table->enum('is_group_treasurer', ['Yes', 'No'])
                ->default('No')
                ->after('is_group_secretary')
                ->comment('Is this user a VSLA group treasurer?');
            
            // Onboarding Progress Tracking
            $table->enum('onboarding_step', [
                'not_started',      // User hasn't started onboarding
                'step_1_welcome',   // Completed welcome screen
                'step_2_terms',     // Accepted terms and privacy policy
                'step_3_registration', // User account created
                'step_4_group',     // VSLA group created
                'step_5_members',   // Secretary and treasurer registered
                'step_6_cycle',     // Savings cycle configured
                'step_7_complete'   // Onboarding completed
            ])
                ->default('not_started')
                ->after('is_group_treasurer')
                ->comment('Current step in onboarding process');
            
            $table->timestamp('onboarding_completed_at')
                ->nullable()
                ->after('onboarding_step')
                ->comment('When user completed onboarding');
            
            $table->timestamp('last_onboarding_step_at')
                ->nullable()
                ->after('onboarding_completed_at')
                ->comment('Last time user progressed in onboarding');
            
            // Add indexes for performance
            $table->index('is_group_admin');
            $table->index('onboarding_step');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_group_admin']);
            $table->dropIndex(['onboarding_step']);
            
            $table->dropColumn([
                'is_group_admin',
                'is_group_secretary',
                'is_group_treasurer',
                'onboarding_step',
                'onboarding_completed_at',
                'last_onboarding_step_at'
            ]);
        });
    }
}

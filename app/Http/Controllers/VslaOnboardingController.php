<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\FfsGroup;
use App\Models\Project;
use App\Models\Location;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

/**
 * VSLA Onboarding Controller
 * 
 * Manages the step-by-step onboarding process for VSLA groups:
 * Step 1: Welcome Screen (handled by frontend)
 * Step 2: Terms & Privacy (handled by frontend)
 * Step 3: User Registration (Group Admin)
 * Step 4: Group Creation
 * Step 5: Main Members Registration (Secretary & Treasurer)
 * Step 6: Savings Cycle Setup
 * Step 7: Completion
 * 
 * @package App\Http\Controllers
 */
class VslaOnboardingController extends Controller
{
    use ApiResponser;

    /**
     * Get onboarding configuration and data
     * 
     * Returns districts, meeting frequencies, and other dropdown data
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOnboardingConfig()
    {
        try {
            $data = [
                'districts' => Location::where('parent', 0)
                    ->orderBy('name', 'asc')
                    ->get(['id', 'name']),
                
                'meeting_frequencies' => [
                    'Weekly' => 'Weekly',
                    'Bi-weekly' => 'Bi-weekly', 
                    'Monthly' => 'Monthly'
                ],
                
                'interest_frequencies' => [
                    'Weekly' => 'Weekly',
                    'Monthly' => 'Monthly'
                ],
                
                'loan_multiples' => [
                    5 => '5x Share Value',
                    10 => '10x Share Value',
                    15 => '15x Share Value',
                    20 => '20x Share Value',
                    25 => '25x Share Value',
                    30 => '30x Share Value'
                ]
            ];

            return $this->success($data, 'Onboarding configuration retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve configuration: ' . $e->getMessage());
        }
    }

    /**
     * STEP 3: Register Group Admin
     * 
     * Creates a new user account and marks them as a group admin.
     * Automatically logs them in after registration.
     * 
     * Required fields:
     * - name: Full name
     * - phone_number: Uganda phone number
     * - password: Password
     * - password_confirmation: Password confirmation
     * 
     * Optional fields:
     * - email: Email address
     * - country: Country (defaults to Uganda)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerGroupAdmin(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+256|0)[7][0-9]{8}$/',
                'unique:users,phone_number'
            ],
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:4|confirmed',
            'country' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        DB::beginTransaction();
        
        try {
            // Prepare phone number (ensure it starts with +256)
            $phoneNumber = $request->phone_number;
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '+256' . substr($phoneNumber, 1);
            } elseif (substr($phoneNumber, 0, 4) !== '+256') {
                $phoneNumber = '+256' . $phoneNumber;
            }

            // Create user
            $user = new User();
            $user->name = $request->name;
            $user->first_name = $request->name;
            $user->phone_number = $phoneNumber;
            $user->username = $phoneNumber;
            $user->email = $request->email ?? '';
            $user->password = Hash::make($request->password);
            $user->country = $request->country ?? 'Uganda';
            $user->user_type = 'Customer';
            $user->status = 'Active';
            
            // VSLA Onboarding fields
            $user->is_group_admin = 'Yes';
            $user->onboarding_step = 'step_3_registration';
            $user->last_onboarding_step_at = Carbon::now();
            
            $user->save();

            // Generate token for auto-login with long expiry
            JWTAuth::factory()->setTTL(60 * 24 * 30 * 365);
            $token = auth('api')->login($user);

            // Set token on user object for response
            $user->token = $token;
            $user->remember_token = $token;

            DB::commit();

            return $this->success([
                'user' => $user,
                'token' => $token
            ], 'Registration successful! You are now logged in as a group admin.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Registration failed: ' . $e->getMessage());
        }
    }

    /**
     * STEP 4: Create VSLA Group
     * 
     * Creates a new VSLA group and links the current user as admin.
     * 
     * Required fields:
     * - name: Group name
     * - description: Group description
     * - meeting_frequency: Weekly, Bi-weekly, or Monthly
     * - establishment_date: When group was established
     * - district_id: District ID
     * - estimated_members: Estimated number of members
     * 
     * Optional fields:
     * - subcounty_text: Subcounty name
     * - parish_text: Parish name
     * - village: Village name
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createVslaGroup(Request $request)
    {
        // Get user from middleware (EnsureTokenIsValid)
        $user = $request->userModel ?? auth('api')->user();
        
        if (!$user) {
            return $this->error('You must be logged in to create a group');
        }

        if ($user->is_group_admin !== 'Yes') {
            return $this->error('Only group admins can create VSLA groups');
        }

        // Check if user already has a group
        $existingGroup = FfsGroup::where('admin_id', $user->id)
            ->where('status', 'Active')
            ->first();
        
        if ($existingGroup) {
            return $this->error('You already have an active VSLA group: ' . $existingGroup->name);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:10',
            'meeting_frequency' => 'required|in:Weekly,Bi-weekly,Monthly',
            'establishment_date' => 'required|date|before_or_equal:today',
            'district_id' => 'required|exists:locations,id',
            'estimated_members' => 'required|integer|min:10|max:50',
            'subcounty_text' => 'nullable|string|max:100',
            'parish_text' => 'nullable|string|max:100',
            'village' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        DB::beginTransaction();
        
        try {
            // Generate unique group code
            $district = Location::find($request->district_id);
            $districtCode = strtoupper(substr($district->name, 0, 3));
            $year = date('y');
            
            // Get last group code for this district
            $lastGroup = FfsGroup::where('code', 'like', "$districtCode-VSLA-$year-%")
                ->orderBy('code', 'desc')
                ->first();
            
            if ($lastGroup && preg_match('/-(\d{4})$/', $lastGroup->code, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            } else {
                $nextNumber = 1;
            }
            
            $groupCode = sprintf('%s-VSLA-%s-%04d', $districtCode, $year, $nextNumber);

            // Create group
            $group = new FfsGroup();
            $group->name = $request->name;
            $group->type = 'VSLA';
            $group->code = $groupCode;
            $group->description = $request->description;
            $group->meeting_frequency = $request->meeting_frequency;
            $group->establishment_date = $request->establishment_date;
            $group->registration_date = Carbon::now();
            $group->district_id = $request->district_id;
            $group->subcounty_text = $request->subcounty_text;
            $group->parish_text = $request->parish_text;
            $group->village = $request->village;
            $group->estimated_members = $request->estimated_members;
            $group->status = 'Active';
            $group->admin_id = $user->id;
            $group->created_by_id = $user->id;
            $group->facilitator_id = $user->id; // Admin is also facilitator initially
            
            $group->save();

            // Update user's onboarding step and link to group
            $user->onboarding_step = 'step_4_group';
            $user->last_onboarding_step_at = Carbon::now();
            $user->group_id = $group->id;
            $user->district_id = $request->district_id;
            $user->save();

            DB::commit();

            return $this->success([
                'group' => $group,
                'user' => $user
            ], 'VSLA group created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create group: ' . $e->getMessage());
        }
    }

    /**
     * STEP 5: Register Main Members (Secretary & Treasurer)
     * 
     * Registers or updates the group secretary and treasurer.
     * Creates user accounts if they don't exist.
     * Sends SMS with login credentials.
     * 
     * Required fields for each role:
     * - secretary_name: Secretary full name
     * - secretary_phone: Secretary phone number
     * - treasurer_name: Treasurer full name
     * - treasurer_phone: Treasurer phone number
     * 
     * Optional fields:
     * - secretary_email: Secretary email
     * - treasurer_email: Treasurer email
     * - send_sms: Whether to send SMS credentials (default: true)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerMainMembers(Request $request)
    {
        // Get user from middleware (EnsureTokenIsValid)
        $user = $request->userModel ?? auth('api')->user();
        
        if (!$user) {
            return $this->error('You must be logged in');
        }

        if ($user->is_group_admin !== 'Yes') {
            return $this->error('Only group admins can register main members');
        }

        if (!$user->group_id) {
            return $this->error('You must create a group first');
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'secretary_name' => 'required|string|min:3|max:255',
            'secretary_phone' => [
                'required',
                'string',
                'regex:/^(\+256|0)[7][0-9]{8}$/'
            ],
            'secretary_email' => 'nullable|email',
            'treasurer_name' => 'required|string|min:3|max:255',
            'treasurer_phone' => [
                'required',
                'string',
                'regex:/^(\+256|0)[7][0-9]{8}$/',
                'different:secretary_phone'
            ],
            'treasurer_email' => 'nullable|email',
            'send_sms' => 'nullable|in:0,1,true,false',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        // Check if admin is using their own phone number
        if ($request->secretary_phone === $user->phone_number || 
            $request->treasurer_phone === $user->phone_number) {
            return $this->error('You cannot register yourself as secretary or treasurer. You are the chairperson.');
        }

        DB::beginTransaction();
        
        try {
            $group = FfsGroup::find($user->group_id);
            
            if (!$group) {
                return $this->error('Group not found');
            }

            // Process Secretary
            $secretaryPassword = $this->generateSecurePassword();
            $secretary = $this->createOrUpdateMember(
                $request->secretary_name,
                $request->secretary_phone,
                $request->secretary_email,
                $secretaryPassword,
                'Secretary',
                $group,
                $user
            );

            // Process Treasurer
            $treasurerPassword = $this->generateSecurePassword();
            $treasurer = $this->createOrUpdateMember(
                $request->treasurer_name,
                $request->treasurer_phone,
                $request->treasurer_email,
                $treasurerPassword,
                'Treasurer',
                $group,
                $user
            );

            // Update group with member IDs
            $group->secretary_id = $secretary->id;
            $group->treasurer_id = $treasurer->id;
            $group->save();

            // Update admin's onboarding step
            $user->onboarding_step = 'step_5_members';
            $user->last_onboarding_step_at = Carbon::now();
            $user->save();

            // Send SMS credentials if requested
            $shouldSendSms = $request->filled('send_sms') && 
                             in_array($request->send_sms, ['1', 'true', true, 1], true);
            $smsResults = [];
            
            if ($shouldSendSms) {
                // Send SMS to secretary
                $secretarySmsResult = $this->sendCredentialsSMS(
                    $secretary,
                    $secretaryPassword,
                    $group,
                    'Secretary'
                );
                $smsResults['secretary'] = $secretarySmsResult;

                // Send SMS to treasurer
                $treasurerSmsResult = $this->sendCredentialsSMS(
                    $treasurer,
                    $treasurerPassword,
                    $group,
                    'Treasurer'
                );
                $smsResults['treasurer'] = $treasurerSmsResult;
            }

            DB::commit();

            return $this->success([
                'secretary' => $secretary,
                'treasurer' => $treasurer,
                'group' => $group,
                'sms_sent' => $shouldSendSms,
                'sms_results' => $smsResults
            ], 'Main members registered successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to register members: ' . $e->getMessage());
        }
    }

    /**
     * STEP 6: Create Savings Cycle
     * 
     * Creates a VSLA savings cycle (stored as Project in backend).
     * Only one active cycle allowed per group at a time.
     * 
     * Required fields:
     * - cycle_name: Name of the cycle
     * - start_date: Cycle start date
     * - end_date: Cycle end date
     * - share_value: Amount per share
     * - meeting_frequency: Weekly, Bi-weekly, Monthly
     * - loan_interest_rate: Primary interest rate (%)
     * - interest_frequency: Weekly or Monthly
     * - minimum_loan_amount: Minimum loan amount
     * - maximum_loan_multiple: Max loan as multiple of shares (e.g., 10x)
     * - late_payment_penalty: Penalty for late payments (%)
     * 
     * Conditional fields:
     * - weekly_loan_interest_rate: Required if interest_frequency is Weekly
     * - monthly_loan_interest_rate: Required if interest_frequency is Monthly
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSavingsCycle(Request $request)
    {
        // Get user from middleware (EnsureTokenIsValid)
        $user = $request->userModel ?? auth('api')->user();
        
        if (!$user) {
            return $this->error('You must be logged in');
        }

        if ($user->is_group_admin !== 'Yes') {
            return $this->error('Only group admins can create savings cycles');
        }

        if (!$user->group_id) {
            return $this->error('You must create a group first');
        }

        // Check if group already has an active cycle
        $existingCycle = Project::where('group_id', $user->group_id)
            ->where('is_vsla_cycle', 'Yes')
            ->where('is_active_cycle', 'Yes')
            ->first();
        
        if ($existingCycle) {
            return $this->error('Your group already has an active savings cycle: ' . $existingCycle->cycle_name);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'cycle_name' => 'required|string|min:3|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'share_value' => 'required|numeric|min:1000|max:100000',
            'meeting_frequency' => 'required|in:Weekly,Bi-weekly,Monthly',
            'loan_interest_rate' => 'required|numeric|min:0|max:100',
            'interest_frequency' => 'required|in:Weekly,Monthly',
            'weekly_loan_interest_rate' => 'required_if:interest_frequency,Weekly|nullable|numeric|min:0|max:100',
            'monthly_loan_interest_rate' => 'required_if:interest_frequency,Monthly|nullable|numeric|min:0|max:100',
            'minimum_loan_amount' => 'required|numeric|min:1000',
            'maximum_loan_multiple' => 'required|integer|min:5|max:30',
            'late_payment_penalty' => 'required|numeric|min:0|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        DB::beginTransaction();
        
        try {
            $group = FfsGroup::find($user->group_id);
            
            if (!$group) {
                return $this->error('Group not found');
            }

            // Create savings cycle (as Project)
            $cycle = new Project();
            $cycle->title = $request->cycle_name;
            $cycle->description = "VSLA Savings Cycle for {$group->name}";
            $cycle->start_date = $request->start_date;
            $cycle->end_date = $request->end_date;
            $cycle->status = 'ongoing';
            $cycle->created_by_id = $user->id;
            
            // VSLA-specific fields
            $cycle->is_vsla_cycle = 'Yes';
            $cycle->is_active_cycle = 'Yes';
            $cycle->group_id = $user->group_id;
            $cycle->cycle_name = $request->cycle_name;
            $cycle->share_value = $request->share_value;
            $cycle->share_price = $request->share_value; // Keep consistency
            $cycle->meeting_frequency = $request->meeting_frequency;
            $cycle->loan_interest_rate = $request->loan_interest_rate;
            $cycle->interest_frequency = $request->interest_frequency;
            $cycle->weekly_loan_interest_rate = $request->weekly_loan_interest_rate;
            $cycle->monthly_loan_interest_rate = $request->monthly_loan_interest_rate;
            $cycle->minimum_loan_amount = $request->minimum_loan_amount;
            $cycle->maximum_loan_multiple = $request->maximum_loan_multiple;
            $cycle->late_payment_penalty = $request->late_payment_penalty;
            
            $cycle->save();

            // Update group's cycle information
            $group->cycle_number = ($group->cycle_number ?? 0) + 1;
            $group->cycle_start_date = $request->start_date;
            $group->cycle_end_date = $request->end_date;
            $group->save();

            // Update user's onboarding step
            $user->onboarding_step = 'step_6_cycle';
            $user->last_onboarding_step_at = Carbon::now();
            $user->save();

            DB::commit();

            return $this->success([
                'cycle' => $cycle,
                'group' => $group,
                'user' => $user
            ], 'Savings cycle created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create savings cycle: ' . $e->getMessage());
        }
    }

    /**
     * STEP 7: Complete Onboarding
     * 
     * Marks the onboarding process as complete.
     * Returns summary of all created entities.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeOnboarding(Request $request)
    {
        // Get user from middleware (EnsureTokenIsValid)
        $user = $request->userModel ?? auth('api')->user();
        
        if (!$user) {
            return $this->error('You must be logged in');
        }

        if ($user->onboarding_step === 'step_7_complete') {
            return $this->error('Onboarding already completed');
        }

        if ($user->onboarding_step !== 'step_6_cycle') {
            return $this->error('Please complete all previous steps first');
        }

        DB::beginTransaction();
        
        try {
            // Update user's onboarding status
            $user->onboarding_step = 'step_7_complete';
            $user->onboarding_completed_at = Carbon::now();
            $user->last_onboarding_step_at = Carbon::now();
            $user->save();

            // Gather summary data
            $group = FfsGroup::find($user->group_id);
            $secretary = $group ? User::find($group->secretary_id) : null;
            $treasurer = $group ? User::find($group->treasurer_id) : null;
            $cycle = Project::where('group_id', $user->group_id)
                ->where('is_vsla_cycle', 'Yes')
                ->where('is_active_cycle', 'Yes')
                ->first();

            DB::commit();

            return $this->success([
                'user' => $user,
                'group' => $group,
                'secretary' => $secretary,
                'treasurer' => $treasurer,
                'cycle' => $cycle,
                'summary' => [
                    'group_name' => $group->name ?? '',
                    'group_code' => $group->code ?? '',
                    'total_members' => $group->estimated_members ?? 0,
                    'meeting_frequency' => $group->meeting_frequency ?? '',
                    'cycle_name' => $cycle->cycle_name ?? '',
                    'share_value' => $cycle->share_value ?? 0,
                    'cycle_duration' => $cycle ? Carbon::parse($cycle->start_date)->diffInMonths(Carbon::parse($cycle->end_date)) . ' months' : '',
                ]
            ], 'Congratulations! Your VSLA group setup is complete.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to complete onboarding: ' . $e->getMessage());
        }
    }

    /**
     * Get current onboarding status for logged-in user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOnboardingStatus(Request $request)
    {
        // Get user from middleware (EnsureTokenIsValid)
        $user = $request->userModel ?? auth('api')->user();
        
        if (!$user) {
            return $this->error('You must be logged in');
        }

        $data = [
            'current_step' => $user->onboarding_step,
            'is_complete' => $user->onboarding_step === 'step_7_complete',
            'completed_at' => $user->onboarding_completed_at,
            'last_step_at' => $user->last_onboarding_step_at,
            'user' => $user,
        ];

        // Include group data if available
        if ($user->group_id) {
            $group = FfsGroup::find($user->group_id);
            $data['group'] = $group;
            
            if ($group) {
                $data['secretary'] = $group->secretary_id ? User::find($group->secretary_id) : null;
                $data['treasurer'] = $group->treasurer_id ? User::find($group->treasurer_id) : null;
                
                $cycle = Project::where('group_id', $group->id)
                    ->where('is_vsla_cycle', 'Yes')
                    ->where('is_active_cycle', 'Yes')
                    ->first();
                $data['cycle'] = $cycle;
            }
        }

        return $this->success($data, 'Onboarding status retrieved successfully');
    }

    // ========== HELPER METHODS ==========

    /**
     * Create or update a member (secretary or treasurer)
     */
    private function createOrUpdateMember($name, $phone, $email, $password, $role, $group, $admin)
    {
        // Prepare phone number
        if (substr($phone, 0, 1) === '0') {
            $phone = '+256' . substr($phone, 1);
        } elseif (substr($phone, 0, 4) !== '+256') {
            $phone = '+256' . $phone;
        }

        // Check if user exists
        $member = User::where('phone_number', $phone)->first();

        if ($member) {
            // Update existing user
            $member->name = $name;
            $member->first_name = $name;
            $member->email = $email ?? $member->email;
            
            // Update role
            if ($role === 'Secretary') {
                $member->is_group_secretary = 'Yes';
            } elseif ($role === 'Treasurer') {
                $member->is_group_treasurer = 'Yes';
            }
            
            $member->group_id = $group->id;
            $member->district_id = $group->district_id;
            $member->save();
        } else {
            // Create new user
            $member = new User();
            $member->name = $name;
            $member->first_name = $name;
            $member->phone_number = $phone;
            $member->username = $phone;
            $member->email = $email ?? '';
            $member->password = Hash::make($password);
            $member->user_type = 'Customer';
            $member->status = 'Active';
            $member->country = 'Uganda';
            
            // Set role
            if ($role === 'Secretary') {
                $member->is_group_secretary = 'Yes';
            } elseif ($role === 'Treasurer') {
                $member->is_group_treasurer = 'Yes';
            }
            
            $member->group_id = $group->id;
            $member->district_id = $group->district_id;
            $member->onboarding_step = 'step_7_complete'; // They skip onboarding
            $member->onboarding_completed_at = Carbon::now();
            $member->created_by_id = $admin->id;
            $member->save();
        }

        return $member;
    }

    /**
     * Generate a secure random password
     */
    private function generateSecurePassword($length = 8)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $max)];
        }
        
        return $password;
    }

    /**
     * Send credentials via SMS
     */
    private function sendCredentialsSMS($user, $password, $group, $role)
    {
        try {
            $message = "Welcome to {$group->name}! You have been appointed as {$role}.\n\n";
            $message .= "Login Details:\n";
            $message .= "Phone: {$user->phone_number}\n";
            $message .= "Password: {$password}\n\n";
            $message .= "Download FAO FFS-MIS app to get started.";

            // Use existing SMS utility
            $result = Utils::send_sms($user->phone_number, $message);
            
            return [
                'success' => true,
                'phone' => $user->phone_number,
                'message' => 'SMS sent successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'phone' => $user->phone_number,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}

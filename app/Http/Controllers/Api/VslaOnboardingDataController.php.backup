<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VslaGroup;
use App\Models\SaccoSavingAccount;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VslaOnboardingDataController extends Controller
{
    /**
     * Get chairperson profile data for registration form
     * Fetches existing user data to pre-fill registration form
     */
    public function getChairpersonData(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is chairperson
            if ($user->is_group_admin != 'Yes') {
                return response()->json([
                    'status' => 0,
                    'message' => 'User is not a group chairperson',
                ], 403);
            }

            $data = [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone_number' => $user->phone_number,
                'phone_number_2' => $user->phone_number_2,
                'email' => $user->email,
                'sex' => $user->sex,
                'date_of_birth' => $user->date_of_birth,
                'address' => $user->address,
                'village' => $user->village,
                'parish_id' => $user->parish_id,
                'subcounty_id' => $user->subcounty_id,
                'district_id' => $user->district_id,
                'national_id' => $user->national_id,
                'is_group_admin' => $user->is_group_admin,
                'has_registered' => !empty($user->password) && $user->status == 1,
            ];

            // Get location names if IDs exist
            if ($user->district_id) {
                $district = Location::find($user->district_id);
                $data['district_name'] = $district ? $district->name : null;
            }
            
            if ($user->subcounty_id) {
                $subcounty = Location::find($user->subcounty_id);
                $data['subcounty_name'] = $subcounty ? $subcounty->name : null;
            }
            
            if ($user->parish_id) {
                $parish = Location::find($user->parish_id);
                $data['parish_name'] = $parish ? $parish->name : null;
            }

            return response()->json([
                'status' => 1,
                'message' => 'Chairperson data retrieved successfully',
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving chairperson data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's group data for group creation form
     * Fetches existing group data if user already has a group
     */
    public function getGroupData(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $group = null;
            
            // Try to find group where user is admin
            if ($user->vsla_group_id) {
                $group = VslaGroup::find($user->vsla_group_id);
            }
            
            // If no group found by vsla_group_id, search for group where user is admin
            if (!$group) {
                $group = VslaGroup::where('administrator_id', $user->id)->first();
            }

            if (!$group) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No existing group found',
                    'data' => null,
                ]);
            }

            $data = [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'phone_number' => $group->phone_number,
                'email' => $group->email,
                'address' => $group->address,
                'village' => $group->village,
                'parish_id' => $group->parish_id,
                'subcounty_id' => $group->subcounty_id,
                'district_id' => $group->district_id,
                'constitution_file' => $group->constitution_file,
                'registration_date' => $group->registration_date,
                'meeting_frequency' => $group->meeting_frequency,
                'meeting_day' => $group->meeting_day,
                'meeting_time' => $group->meeting_time,
                'meeting_venue' => $group->meeting_venue,
                'status' => $group->status,
                'administrator_id' => $group->administrator_id,
            ];

            // Get location names
            if ($group->district_id) {
                $district = Location::find($group->district_id);
                $data['district_name'] = $district ? $district->name : null;
            }
            
            if ($group->subcounty_id) {
                $subcounty = Location::find($group->subcounty_id);
                $data['subcounty_name'] = $subcounty ? $subcounty->name : null;
            }
            
            if ($group->parish_id) {
                $parish = Location::find($group->parish_id);
                $data['parish_name'] = $parish ? $parish->name : null;
            }

            return response()->json([
                'status' => 1,
                'message' => 'Group data retrieved successfully',
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving group data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get main members (secretary and treasurer) data
     * Fetches existing secretary and treasurer if they exist
     */
    public function getMainMembersData(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Find the group
            $group = null;
            if ($user->vsla_group_id) {
                $group = VslaGroup::find($user->vsla_group_id);
            }
            
            if (!$group) {
                $group = VslaGroup::where('administrator_id', $user->id)->first();
            }

            if (!$group) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No group found. Create a group first.',
                    'data' => [
                        'secretary' => null,
                        'treasurer' => null,
                    ],
                ]);
            }

            // Find secretary
            $secretary = User::where('vsla_group_id', $group->id)
                ->where('is_group_secretary', 'Yes')
                ->first();

            // Find treasurer
            $treasurer = User::where('vsla_group_id', $group->id)
                ->where('is_group_treasurer', 'Yes')
                ->first();

            $secretaryData = null;
            if ($secretary) {
                $secretaryData = [
                    'id' => $secretary->id,
                    'name' => $secretary->name,
                    'first_name' => $secretary->first_name,
                    'last_name' => $secretary->last_name,
                    'phone_number' => $secretary->phone_number,
                    'phone_number_2' => $secretary->phone_number_2,
                    'email' => $secretary->email,
                    'sex' => $secretary->sex,
                    'date_of_birth' => $secretary->date_of_birth,
                    'address' => $secretary->address,
                    'village' => $secretary->village,
                    'national_id' => $secretary->national_id,
                ];
            }

            $treasurerData = null;
            if ($treasurer) {
                $treasurerData = [
                    'id' => $treasurer->id,
                    'name' => $treasurer->name,
                    'first_name' => $treasurer->first_name,
                    'last_name' => $treasurer->last_name,
                    'phone_number' => $treasurer->phone_number,
                    'phone_number_2' => $treasurer->phone_number_2,
                    'email' => $treasurer->email,
                    'sex' => $treasurer->sex,
                    'date_of_birth' => $treasurer->date_of_birth,
                    'address' => $treasurer->address,
                    'village' => $treasurer->village,
                    'national_id' => $treasurer->national_id,
                ];
            }

            return response()->json([
                'status' => 1,
                'message' => 'Main members data retrieved successfully',
                'data' => [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'secretary' => $secretaryData,
                    'treasurer' => $treasurerData,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving main members data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active savings cycle data
     * Fetches current active cycle or most recent cycle for the group
     */
    public function getSavingsCycleData(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Find the group
            $group = null;
            if ($user->vsla_group_id) {
                $group = VslaGroup::find($user->vsla_group_id);
            }
            
            if (!$group) {
                $group = VslaGroup::where('administrator_id', $user->id)->first();
            }

            if (!$group) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No group found. Create a group first.',
                    'data' => null,
                ]);
            }

            // Find active cycle or most recent cycle
            $cycle = SaccoSavingAccount::where('vsla_group_id', $group->id)
                ->where('status', 'Active')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$cycle) {
                // Try to find any cycle (including completed ones)
                $cycle = SaccoSavingAccount::where('vsla_group_id', $group->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            if (!$cycle) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No existing savings cycle found',
                    'data' => [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'cycle' => null,
                    ],
                ]);
            }

            $cycleData = [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'vsla_group_id' => $cycle->vsla_group_id,
                'cycle_number' => $cycle->cycle_number,
                'start_date' => $cycle->start_date,
                'end_date' => $cycle->end_date,
                'expected_end_date' => $cycle->expected_end_date,
                'status' => $cycle->status,
                'share_price' => $cycle->share_price,
                'max_shares_per_member' => $cycle->max_shares_per_member,
                'min_shares_per_member' => $cycle->min_shares_per_member,
                'loan_interest_rate' => $cycle->loan_interest_rate,
                'interest_frequency' => $cycle->interest_frequency,
                'meeting_frequency' => $cycle->meeting_frequency,
                'registration_fee' => $cycle->registration_fee,
                'penalty_fee' => $cycle->penalty_fee,
                'fines_upon_late_payment' => $cycle->fines_upon_late_payment,
                'balance' => $cycle->balance,
                'total_members' => $cycle->total_members,
            ];

            return response()->json([
                'status' => 1,
                'message' => 'Savings cycle data retrieved successfully',
                'data' => [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'cycle' => $cycleData,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving savings cycle data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get complete onboarding data (all steps combined)
     * Useful for checking what data exists and what still needs to be filled
     */
    public function getAllOnboardingData(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Get chairperson data
            $chairpersonResponse = $this->getChairpersonData($request);
            $chairpersonData = json_decode($chairpersonResponse->getContent(), true);

            // Get group data
            $groupResponse = $this->getGroupData($request);
            $groupData = json_decode($groupResponse->getContent(), true);

            // Get main members data
            $membersResponse = $this->getMainMembersData($request);
            $membersData = json_decode($membersResponse->getContent(), true);

            // Get savings cycle data
            $cycleResponse = $this->getSavingsCycleData($request);
            $cycleData = json_decode($cycleResponse->getContent(), true);

            // Determine completion status
            $completionStatus = [
                'step_1_welcome' => true, // Always considered complete
                'step_2_privacy_terms' => true, // Assume complete if they're logged in
                'step_3_registration' => isset($chairpersonData['data']['has_registered']) && $chairpersonData['data']['has_registered'],
                'step_4_group_creation' => isset($groupData['data']) && $groupData['data'] !== null,
                'step_5_main_members' => isset($membersData['data']['secretary']) && isset($membersData['data']['treasurer']),
                'step_6_savings_cycle' => isset($cycleData['data']['cycle']) && $cycleData['data']['cycle'] !== null,
                'step_7_complete' => false, // Will be set by completion endpoint
            ];

            // Calculate overall completion percentage
            $completedSteps = array_filter($completionStatus);
            $totalSteps = count($completionStatus);
            $completionPercentage = round((count($completedSteps) / $totalSteps) * 100);

            return response()->json([
                'status' => 1,
                'message' => 'All onboarding data retrieved successfully',
                'data' => [
                    'chairperson' => $chairpersonData['data'] ?? null,
                    'group' => $groupData['data'] ?? null,
                    'main_members' => $membersData['data'] ?? null,
                    'savings_cycle' => $cycleData['data'] ?? null,
                    'completion_status' => $completionStatus,
                    'completion_percentage' => $completionPercentage,
                    'current_step' => $this->determineCurrentStep($completionStatus),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error retrieving onboarding data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine which step user should be on based on completion status
     */
    private function determineCurrentStep($completionStatus)
    {
        if (!$completionStatus['step_3_registration']) {
            return 3; // Registration
        } elseif (!$completionStatus['step_4_group_creation']) {
            return 4; // Group Creation
        } elseif (!$completionStatus['step_5_main_members']) {
            return 5; // Main Members
        } elseif (!$completionStatus['step_6_savings_cycle']) {
            return 6; // Savings Cycle
        } elseif (!$completionStatus['step_7_complete']) {
            return 7; // Complete
        }
        return 1; // Default to welcome
    }
}

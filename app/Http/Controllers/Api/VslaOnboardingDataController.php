<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FfsGroup;
use App\Models\Project;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VslaOnboardingDataController extends Controller
{
    /**
     * Get chairperson profile data for registration form
     * Fetches existing user data to pre-fill registration form
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChairpersonData(Request $request)
    {
        try {
            $user = $request->userModel;
            
            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is chairperson/admin
            if ($user->is_group_admin != 'Yes') {
                return response()->json([
                    'code' => 0,
                    'message' => 'User is not a group chairperson',
                ], 403);
            }

            $data = [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'sex' => $user->sex,
                'date_of_birth' => $user->date_of_birth,
                'address' => $user->address,
                'status' => $user->status,
                'nin' => $user->nin,
                'national_id_number' => $user->national_id_number,
                'is_group_admin' => $user->is_group_admin,
                'group_id' => $user->group_id,
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Chairperson data fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to fetch chairperson data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get VSLA group data for group creation form
     * Fetches existing group data to pre-fill form if group already exists
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupData(Request $request)
    {
        try {
            $user = $request->userModel;
            
            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is chairperson
            if ($user->is_group_admin != 'Yes') {
                return response()->json([
                    'code' => 0,
                    'message' => 'User is not authorized to access group data',
                ], 403);
            }

            // Try to find group by user's group_id first
            $group = null;
            if ($user->group_id) {
                $group = FfsGroup::find($user->group_id);
            }

            // If not found by group_id, try finding by admin_id
            if (!$group) {
                $group = FfsGroup::where('admin_id', $user->id)->first();
            }

            // If no group found, return empty data
            if (!$group) {
                return response()->json([
                    'code' => 1,
                    'message' => 'No group data found',
                    'data' => null,
                ], 200);
            }

            // Fetch related location data
            $district = Location::find($group->district_id);
            $subcounty = Location::find($group->subcounty_id);
            $parish = Location::find($group->parish_id);

            $data = [
                'id' => $group->id,
                'name' => $group->name,
                'group_name' => $group->name,
                'registration_date' => $group->registration_date,
                'status' => $group->status,
                'admin_id' => $group->admin_id,
                'secretary_id' => $group->secretary_id,
                'treasurer_id' => $group->treasurer_id,
                'district_id' => $group->district_id,
                'district_text' => $district ? $district->name_text : null,
                'subcounty_id' => $group->subcounty_id,
                'subcounty_text' => $subcounty ? $subcounty->name_text : null,
                'parish_id' => $group->parish_id,
                'parish_text' => $parish ? $parish->name_text : null,
                'village' => $group->village,
                'meeting_frequency' => $group->meeting_frequency,
                'meeting_day' => $group->meeting_day,
                'meeting_time' => $group->meeting_time,
                'meeting_venue' => $group->meeting_venue,
                'share_price' => $group->share_price,
                'max_shares_per_member' => $group->max_shares_per_member,
                'registration_fee' => $group->registration_fee,
                'contribution_fee' => $group->contribution_fee,
                'social_fund_fee' => $group->social_fund_fee,
                'is_onboarding_complete' => $group->is_onboarding_complete,
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Group data fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to fetch group data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get main members (secretary and treasurer) data
     * Fetches existing secretary and treasurer information for the group
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMainMembersData(Request $request)
    {
        try {
            $user = $request->userModel;
            
            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is chairperson
            if ($user->is_group_admin != 'Yes') {
                return response()->json([
                    'code' => 0,
                    'message' => 'User is not authorized to access members data',
                ], 403);
            }

            // Find the group
            $group = null;
            if ($user->group_id) {
                $group = FfsGroup::find($user->group_id);
            }

            if (!$group) {
                $group = FfsGroup::where('admin_id', $user->id)->first();
            }

            if (!$group) {
                return response()->json([
                    'code' => 1,
                    'message' => 'No group found',
                    'data' => null,
                ], 200);
            }

            // Fetch secretary and treasurer
            $secretary = $group->secretary_id ? User::find($group->secretary_id) : null;
            $treasurer = $group->treasurer_id ? User::find($group->treasurer_id) : null;

            $data = [
                'group_id' => $group->id,
                'secretary' => $secretary ? [
                    'id' => $secretary->id,
                    'first_name' => $secretary->first_name,
                    'last_name' => $secretary->last_name,
                    'phone_number' => $secretary->phone_number,
                    'email' => $secretary->email,
                    'sex' => $secretary->sex,
                    'date_of_birth' => $secretary->date_of_birth,
                    'nin' => $secretary->nin,
                    'national_id_number' => $secretary->national_id_number,
                ] : null,
                'treasurer' => $treasurer ? [
                    'id' => $treasurer->id,
                    'first_name' => $treasurer->first_name,
                    'last_name' => $treasurer->last_name,
                    'phone_number' => $treasurer->phone_number,
                    'email' => $treasurer->email,
                    'sex' => $treasurer->sex,
                    'date_of_birth' => $treasurer->date_of_birth,
                    'nin' => $treasurer->nin,
                    'national_id_number' => $treasurer->national_id_number,
                ] : null,
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Main members data fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to fetch members data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get savings cycle data
     * Fetches existing active VSLA cycle (Project) for the group
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSavingsCycleData(Request $request)
    {
        try {
            $user = $request->userModel;
            
            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is chairperson
            if ($user->is_group_admin != 'Yes') {
                return response()->json([
                    'code' => 0,
                    'message' => 'User is not authorized to access cycle data',
                ], 403);
            }

            // Find the group
            $group = null;
            if ($user->group_id) {
                $group = FfsGroup::find($user->group_id);
            }

            if (!$group) {
                $group = FfsGroup::where('admin_id', $user->id)->first();
            }

            if (!$group) {
                return response()->json([
                    'code' => 1,
                    'message' => 'No group found',
                    'data' => null,
                ], 200);
            }

            // Find active VSLA cycle for the group
            $cycle = Project::where('group_id', $group->id)
                ->where('is_vsla_cycle', 'Yes')
                ->where('is_active_cycle', 'Yes')
                ->first();

            if (!$cycle) {
                return response()->json([
                    'code' => 1,
                    'message' => 'No active savings cycle found',
                    'data' => null,
                ], 200);
            }

            $data = [
                'id' => $cycle->id,
                'cycle_name' => $cycle->name,
                'name' => $cycle->name,
                'start_date' => $cycle->start_date,
                'end_date' => $cycle->end_date,
                'expected_end_date' => $cycle->expected_end_date,
                'share_value' => $cycle->share_value,
                'share_price' => $cycle->share_value,
                'max_shares_per_member' => $cycle->max_shares_per_member,
                'meeting_frequency' => $cycle->meeting_frequency,
                'registration_fee' => $cycle->registration_fee,
                'contribution_fee' => $cycle->contribution_fee,
                'social_fund_fee' => $cycle->social_fund_fee,
                'status' => $cycle->status,
                'is_active_cycle' => $cycle->is_active_cycle,
                'is_vsla_cycle' => $cycle->is_vsla_cycle,
                'group_id' => $cycle->group_id,
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Savings cycle data fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to fetch cycle data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all onboarding data at once
     * Combines chairperson, group, members, and cycle data in one response
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllOnboardingData(Request $request)
    {
        try {
            $user = $request->userModel;
            
            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user is chairperson
            if ($user->is_group_admin != 'Yes') {
                return response()->json([
                    'code' => 0,
                    'message' => 'User is not authorized to access onboarding data',
                ], 403);
            }

            // Get chairperson data
            $chairpersonData = [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'sex' => $user->sex,
                'date_of_birth' => $user->date_of_birth,
                'address' => $user->address,
                'status' => $user->status,
                'nin' => $user->nin,
                'national_id_number' => $user->national_id_number,
                'is_group_admin' => $user->is_group_admin,
                'group_id' => $user->group_id,
            ];

            // Find the group
            $group = null;
            if ($user->group_id) {
                $group = FfsGroup::find($user->group_id);
            }

            if (!$group) {
                $group = FfsGroup::where('admin_id', $user->id)->first();
            }

            // Get group data
            $groupData = null;
            if ($group) {
                $district = Location::find($group->district_id);
                $subcounty = Location::find($group->subcounty_id);
                $parish = Location::find($group->parish_id);

                $groupData = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'group_name' => $group->name,
                    'registration_date' => $group->registration_date,
                    'status' => $group->status,
                    'admin_id' => $group->admin_id,
                    'secretary_id' => $group->secretary_id,
                    'treasurer_id' => $group->treasurer_id,
                    'district_id' => $group->district_id,
                    'district_text' => $district ? $district->name_text : null,
                    'subcounty_id' => $group->subcounty_id,
                    'subcounty_text' => $subcounty ? $subcounty->name_text : null,
                    'parish_id' => $group->parish_id,
                    'parish_text' => $parish ? $parish->name_text : null,
                    'village' => $group->village,
                    'meeting_frequency' => $group->meeting_frequency,
                    'meeting_day' => $group->meeting_day,
                    'meeting_time' => $group->meeting_time,
                    'meeting_venue' => $group->meeting_venue,
                    'share_price' => $group->share_price,
                    'max_shares_per_member' => $group->max_shares_per_member,
                    'registration_fee' => $group->registration_fee,
                    'contribution_fee' => $group->contribution_fee,
                    'social_fund_fee' => $group->social_fund_fee,
                    'is_onboarding_complete' => $group->is_onboarding_complete,
                ];
            }

            // Get members data
            $membersData = null;
            if ($group) {
                $secretary = $group->secretary_id ? User::find($group->secretary_id) : null;
                $treasurer = $group->treasurer_id ? User::find($group->treasurer_id) : null;

                $membersData = [
                    'group_id' => $group->id,
                    'secretary' => $secretary ? [
                        'id' => $secretary->id,
                        'first_name' => $secretary->first_name,
                        'last_name' => $secretary->last_name,
                        'phone_number' => $secretary->phone_number,
                        'email' => $secretary->email,
                        'sex' => $secretary->sex,
                        'date_of_birth' => $secretary->date_of_birth,
                        'nin' => $secretary->nin,
                        'national_id_number' => $secretary->national_id_number,
                    ] : null,
                    'treasurer' => $treasurer ? [
                        'id' => $treasurer->id,
                        'first_name' => $treasurer->first_name,
                        'last_name' => $treasurer->last_name,
                        'phone_number' => $treasurer->phone_number,
                        'email' => $treasurer->email,
                        'sex' => $treasurer->sex,
                        'date_of_birth' => $treasurer->date_of_birth,
                        'nin' => $treasurer->nin,
                        'national_id_number' => $treasurer->national_id_number,
                    ] : null,
                ];
            }

            // Get cycle data
            $cycleData = null;
            if ($group) {
                $cycle = Project::where('group_id', $group->id)
                    ->where('is_vsla_cycle', 'Yes')
                    ->where('is_active_cycle', 'Yes')
                    ->first();

                if ($cycle) {
                    $cycleData = [
                        'id' => $cycle->id,
                        'cycle_name' => $cycle->name,
                        'name' => $cycle->name,
                        'start_date' => $cycle->start_date,
                        'end_date' => $cycle->end_date,
                        'expected_end_date' => $cycle->expected_end_date,
                        'share_value' => $cycle->share_value,
                        'share_price' => $cycle->share_value,
                        'max_shares_per_member' => $cycle->max_shares_per_member,
                        'meeting_frequency' => $cycle->meeting_frequency,
                        'registration_fee' => $cycle->registration_fee,
                        'contribution_fee' => $cycle->contribution_fee,
                        'social_fund_fee' => $cycle->social_fund_fee,
                        'status' => $cycle->status,
                        'is_active_cycle' => $cycle->is_active_cycle,
                        'is_vsla_cycle' => $cycle->is_vsla_cycle,
                        'group_id' => $cycle->group_id,
                    ];
                }
            }

            return response()->json([
                'code' => 1,
                'message' => 'All onboarding data fetched successfully',
                'data' => [
                    'chairperson' => $chairpersonData,
                    'group' => $groupData,
                    'members' => $membersData,
                    'cycle' => $cycleData,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to fetch onboarding data: ' . $e->getMessage(),
            ], 500);
        }
    }
}

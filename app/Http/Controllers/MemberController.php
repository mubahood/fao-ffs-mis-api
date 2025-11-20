<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\FfsGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    /**
     * Register a new member
     * 
     * POST /api/members
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone_number' => 'required|string|unique:users,phone_number|max:50',
            'sex' => 'required|in:Male,Female',
            'group_id' => 'nullable|integer|exists:ffs_groups,id',
            'dob' => 'nullable|date',
            'email' => 'nullable|email|unique:users,email|max:255',
            'district_id' => 'nullable|integer|exists:locations,id',
            'subcounty_id' => 'nullable|integer|exists:locations,id',
            'parish_id' => 'nullable|integer|exists:locations,id',
            'village' => 'nullable|string|max:100',
            'education_level' => 'nullable|in:None,Primary,Secondary,Tertiary,University',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'occupation' => 'nullable|string|max:100',
            'household_size' => 'nullable|integer|min:0',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => $validator->errors()->first(),
                'data' => null,
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate member code (e.g., MEM-2025-0001)
            $year = date('Y');
            $lastMember = User::where('member_code', 'LIKE', "MEM-{$year}-%")
                ->orderBy('id', 'desc')
                ->first();
            
            $nextNumber = 1;
            if ($lastMember && preg_match("/MEM-{$year}-(\d+)/", $lastMember->member_code, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
            
            $memberCode = sprintf("MEM-%s-%04d", $year, $nextNumber);

            // Create member/user
            $member = new User();
            $member->first_name = $request->first_name;
            $member->last_name = $request->last_name;
            $member->name = $request->first_name . ' ' . $request->last_name;
            $member->phone_number = $request->phone_number;
            $member->sex = $request->sex;
            $member->member_code = $memberCode;
            
            // Set username and password to phone number by default
            $member->username = $request->phone_number;
            $member->password = Hash::make($request->phone_number);
            
            // Set user_type as Customer (member)
            $member->user_type = 'Customer';
            
            // Optional fields
            if ($request->filled('dob')) {
                $member->dob = $request->dob;
            }
            if ($request->filled('email')) {
                $member->email = $request->email;
            }
            if ($request->filled('group_id')) {
                $member->group_id = $request->group_id;
            }
            if ($request->filled('district_id')) {
                $member->district_id = $request->district_id;
            }
            if ($request->filled('subcounty_id')) {
                $member->subcounty_id = $request->subcounty_id;
            }
            if ($request->filled('parish_id')) {
                $member->parish_id = $request->parish_id;
            }
            if ($request->filled('village')) {
                $member->village = $request->village;
            }
            if ($request->filled('education_level')) {
                $member->education_level = $request->education_level;
            }
            if ($request->filled('marital_status')) {
                $member->marital_status = $request->marital_status;
            }
            if ($request->filled('occupation')) {
                $member->occupation = $request->occupation;
            }
            if ($request->filled('household_size')) {
                $member->household_size = $request->household_size;
            }
            if ($request->filled('emergency_contact_name')) {
                $member->emergency_contact_name = $request->emergency_contact_name;
            }
            if ($request->filled('emergency_contact_phone')) {
                $member->emergency_contact_phone = $request->emergency_contact_phone;
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarName = time() . '_' . $member->phone_number . '.' . $avatar->getClientOriginalExtension();
                $avatarPath = $avatar->storeAs('public/avatars', $avatarName);
                $member->avatar = 'avatars/' . $avatarName;
            }

            $member->save();

            DB::commit();

            // Load relationships
            $member->load(['group', 'district', 'subcounty', 'parish']);

            return response()->json([
                'code' => 1,
                'message' => 'Member registered successfully',
                'data' => [
                    'id' => $member->id,
                    'member_code' => $member->member_code,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'name' => $member->name,
                    'phone_number' => $member->phone_number,
                    'email' => $member->email,
                    'sex' => $member->sex,
                    'dob' => $member->dob,
                    'group_id' => $member->group_id,
                    'group' => $member->group ? [
                        'id' => $member->group->id,
                        'name' => $member->group->name,
                        'code' => $member->group->code,
                    ] : null,
                    'district_id' => $member->district_id,
                    'district' => $member->district ? $member->district->name : null,
                    'subcounty_id' => $member->subcounty_id,
                    'subcounty' => $member->subcounty ? $member->subcounty->name : null,
                    'parish_id' => $member->parish_id,
                    'parish' => $member->parish ? $member->parish->name : null,
                    'village' => $member->village,
                    'education_level' => $member->education_level,
                    'marital_status' => $member->marital_status,
                    'occupation' => $member->occupation,
                    'household_size' => $member->household_size,
                    'emergency_contact_name' => $member->emergency_contact_name,
                    'emergency_contact_phone' => $member->emergency_contact_phone,
                    'avatar' => $member->avatar ? url('storage/' . $member->avatar) : null,
                    'created_at' => $member->created_at->format('Y-m-d H:i:s'),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'code' => 0,
                'message' => 'Failed to register member: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get all members with optional filtering
     * 
     * GET /api/members
     * Query params: ?group_id=1&search=john&sex=Male
     */
    public function index(Request $request)
    {
        $query = User::where('user_type', 'Customer')
            ->with(['group', 'district', 'subcounty', 'parish']);

        // Filter by group
        if ($request->has('group_id') && $request->group_id != '') {
            $query->where('group_id', $request->group_id);
        }

        // Search by name or phone
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%")
                  ->orWhere('member_code', 'LIKE', "%{$search}%");
            });
        }

        // Filter by sex
        if ($request->has('sex') && $request->sex != '') {
            $query->where('sex', $request->sex);
        }

        $members = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'code' => 1,
            'message' => 'Members retrieved successfully',
            'data' => $members->map(function($member) {
                return [
                    'id' => $member->id,
                    'member_code' => $member->member_code,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'name' => $member->name,
                    'phone_number' => $member->phone_number,
                    'email' => $member->email,
                    'sex' => $member->sex,
                    'dob' => $member->dob,
                    'group_id' => $member->group_id,
                    'group' => $member->group ? [
                        'id' => $member->group->id,
                        'name' => $member->group->name,
                    ] : null,
                    'district' => $member->district ? $member->district->name : null,
                    'village' => $member->village,
                    'avatar' => $member->avatar ? url('storage/' . $member->avatar) : null,
                ];
            }),
        ]);
    }

    /**
     * Get single member details
     * 
     * GET /api/members/{id}
     */
    public function show($id)
    {
        $member = User::where('user_type', 'Customer')
            ->with(['group', 'district', 'subcounty', 'parish'])
            ->find($id);

        if (!$member) {
            return response()->json([
                'code' => 0,
                'message' => 'Member not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'code' => 1,
            'message' => 'Member retrieved successfully',
            'data' => [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'name' => $member->name,
                'phone_number' => $member->phone_number,
                'email' => $member->email,
                'sex' => $member->sex,
                'dob' => $member->dob,
                'group_id' => $member->group_id,
                'group' => $member->group ? [
                    'id' => $member->group->id,
                    'name' => $member->group->name,
                    'code' => $member->group->code,
                ] : null,
                'district_id' => $member->district_id,
                'district' => $member->district ? $member->district->name : null,
                'subcounty_id' => $member->subcounty_id,
                'subcounty' => $member->subcounty ? $member->subcounty->name : null,
                'parish_id' => $member->parish_id,
                'parish' => $member->parish ? $member->parish->name : null,
                'village' => $member->village,
                'education_level' => $member->education_level,
                'marital_status' => $member->marital_status,
                'occupation' => $member->occupation,
                'household_size' => $member->household_size,
                'emergency_contact_name' => $member->emergency_contact_name,
                'emergency_contact_phone' => $member->emergency_contact_phone,
                'avatar' => $member->avatar ? url('storage/' . $member->avatar) : null,
                'created_at' => $member->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Send member credentials via SMS
     * 
     * POST /api/members/{id}/send-credentials
     */
    public function sendCredentials($id)
    {
        $member = User::where('user_type', 'Customer')->find($id);

        if (!$member) {
            return response()->json([
                'code' => 0,
                'message' => 'Member not found',
                'data' => null,
            ], 404);
        }

        try {
            $phone = $member->phone_number;
            $username = $member->username ?? $member->phone_number;
            $password = $member->phone_number; // Default password is phone number
            
            $message = "Welcome to FAO FFS-MIS!\n\n";
            $message .= "Your account has been created.\n\n";
            $message .= "Username: {$username}\n";
            $message .= "Password: {$password}\n\n";
            $message .= "Member Code: {$member->member_code}\n\n";
            $message .= "Please change your password after logging in.";

            // Send SMS using your SMS gateway
            // Assuming you have an SMS service configured
            // Utils::send_sms($phone, $message);
            
            // For now, we'll just log it
            \Log::info("Credentials SMS to {$phone}: {$message}");

            return response()->json([
                'code' => 1,
                'message' => 'Credentials sent successfully to ' . $phone,
                'data' => [
                    'phone' => $phone,
                    'username' => $username,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to send credentials: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Send welcome message via SMS
     * 
     * POST /api/members/{id}/send-welcome
     */
    public function sendWelcomeMessage($id)
    {
        $member = User::where('user_type', 'Customer')->find($id);

        if (!$member) {
            return response()->json([
                'code' => 0,
                'message' => 'Member not found',
                'data' => null,
            ], 404);
        }

        try {
            $phone = $member->phone_number;
            $groupName = $member->group ? $member->group->name : 'FAO FFS';
            
            $message = "Welcome {$member->first_name} {$member->last_name}!\n\n";
            $message .= "You have been successfully registered as a member of {$groupName}.\n\n";
            $message .= "Your Member Code: {$member->member_code}\n\n";
            $message .= "We are excited to have you join us in improving agricultural practices and building a stronger farming community.\n\n";
            $message .= "For support, please contact your facilitator.\n\n";
            $message .= "Thank you,\nFAO FFS-MIS Team";

            // Send SMS using your SMS gateway
            // Utils::send_sms($phone, $message);
            
            // For now, we'll just log it
            \Log::info("Welcome SMS to {$phone}: {$message}");

            return response()->json([
                'code' => 1,
                'message' => 'Welcome message sent successfully to ' . $phone,
                'data' => [
                    'phone' => $phone,
                    'member_name' => $member->name,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to send welcome message: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

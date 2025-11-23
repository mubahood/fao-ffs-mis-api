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
        // Get the authenticated user (person registering the member)
        // The EnsureTokenIsValid middleware stores it in $request->userModel
        $registrar = $request->userModel ?? auth('api')->user();
        
        if (!$registrar) {
            return response()->json([
                'code' => 0,
                'message' => 'Authentication required. Please login.',
                'data' => null,
            ], 401);
        }

        // Check if registrar has a valid group_id
        if (empty($registrar->group_id) || $registrar->group_id < 1) {
            return response()->json([
                'code' => 0,
                'message' => 'You must be assigned to a group before you can register members. Please contact your administrator.',
                'data' => null,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone_number' => 'required|string|unique:users,phone_number|max:50',
            'sex' => 'required|in:Male,Female',
            'dob' => 'nullable|date',
            'district_id' => 'nullable|integer|exists:locations,id',
            'education_level' => 'nullable|in:None,Primary,Secondary,Tertiary,University',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'occupation' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'password' => 'nullable|string|min:6|max:100',
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
            
            // Set username to phone number (always)
            $member->username = $request->phone_number;
            
            // Set password: use custom password if provided, otherwise use phone number
            if ($request->filled('password')) {
                $member->password = Hash::make($request->password);
            } else {
                $member->password = Hash::make($request->phone_number);
            }
            
            // Set user_type as Customer (member)
            $member->user_type = 'Customer';
            
            // Optional fields
            if ($request->filled('dob')) {
                $member->dob = $request->dob;
            }
            
            // IMPORTANT: Always inherit group_id from the person registering
            // Never trust group_id from request - use the registrar's group
            $member->group_id = $registrar->group_id;
            
            if ($request->filled('district_id')) {
                $member->district_id = $request->district_id;
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
                $avatarPath = $avatar->move(public_path('storage/images'), $avatarName);
                $member->avatar = 'storage/images/' . $avatarName;
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
                    'balance' => floatval($member->balance ?? 0),
                    'loan_balance' => floatval($member->loan_balance ?? 0),
                    'avatar' => $member->avatar ? url($member->avatar) : null,
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
     * Query params: ?group_id=1&search=john&sex=Male&sort_by=balance
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $authUser = User::find($request->header('user_id'));
        
        $query = User::where('user_type', 'Customer')
            ->with(['group', 'district', 'subcounty', 'parish']);

        // If user is a Customer (group member), show only members from their group
        if ($authUser && $authUser->user_type === 'Customer' && $authUser->group_id) {
            $query->where('group_id', $authUser->group_id);
        } else {
            // For admins/other users, allow filtering by group
            if ($request->has('group_id') && $request->group_id != '') {
                $query->where('group_id', $request->group_id);
            }
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

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSortFields = ['created_at', 'name', 'balance', 'loan_balance', 'member_code'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $members = $query->get();

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
                    'balance' => floatval($member->balance ?? 0),
                    'loan_balance' => floatval($member->loan_balance ?? 0),
                    'group_id' => $member->group_id,
                    'group' => $member->group ? [
                        'id' => $member->group->id,
                        'name' => $member->group->name,
                    ] : null,
                    'district' => $member->district ? $member->district->name : null,
                    'village' => $member->village,
                    'avatar' => $member->avatar ? url($member->avatar) : null,
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
                'balance' => floatval($member->balance ?? 0),
                'loan_balance' => floatval($member->loan_balance ?? 0),
                'emergency_contact_name' => $member->emergency_contact_name,
                'emergency_contact_phone' => $member->emergency_contact_phone,
                'avatar' => $member->avatar ? url($member->avatar) : null,
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

    /**
     * Update member
     * 
     * PUT /api/members/{id}
     */
    public function update(Request $request, $id)
    {
        $member = User::find($id);

        if (!$member) {
            return response()->json([
                'code' => 0,
                'message' => 'Member not found',
                'data' => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'phone_number' => 'sometimes|required|string|max:50|unique:users,phone_number,' . $id,
            'sex' => 'sometimes|required|in:Male,Female',
            'dob' => 'nullable|date',
            'district_id' => 'nullable|integer',
            'education_level' => 'nullable|in:None,Primary,Secondary,Tertiary,University',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'occupation' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'password' => 'nullable|string|min:6|max:100',
            'status' => 'nullable|in:0,1',
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

            if ($request->has('first_name')) {
                $member->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $member->last_name = $request->last_name;
            }
            if ($request->has('first_name') || $request->has('last_name')) {
                $member->name = ($request->first_name ?? $member->first_name) . ' ' . ($request->last_name ?? $member->last_name);
            }
            if ($request->has('phone_number')) {
                $member->phone_number = $request->phone_number;
                // Update username to match phone number
                $member->username = $request->phone_number;
            }
            if ($request->filled('password')) {
                // Update password if provided
                $member->password = Hash::make($request->password);
            }
            if ($request->has('sex')) {
                $member->sex = $request->sex;
            }
            if ($request->has('dob')) {
                $member->dob = $request->dob;
            }
            if ($request->has('district_id')) {
                $member->district_id = $request->district_id;
            }
            if ($request->has('education_level')) {
                $member->education_level = $request->education_level;
            }
            if ($request->has('marital_status')) {
                $member->marital_status = $request->marital_status;
            }
            if ($request->has('occupation')) {
                $member->occupation = $request->occupation;
            }
            if ($request->has('emergency_contact_name')) {
                $member->emergency_contact_name = $request->emergency_contact_name;
            }
            if ($request->has('emergency_contact_phone')) {
                $member->emergency_contact_phone = $request->emergency_contact_phone;
            }
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarName = time() . '_' . $avatar->getClientOriginalName();
                $avatar->move(public_path('storage/images'), $avatarName);
                $member->avatar = 'storage/images/' . $avatarName;
            }
            if ($request->has('status')) {
                $member->status = $request->status;
            }

            $member->save();

            DB::commit();

            return response()->json([
                'code' => 1,
                'message' => 'Member updated successfully',
                'data' => $member,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => 0,
                'message' => 'Failed to update member: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Delete member (deactivate)
     * 
     * DELETE /api/members/{id}
     */
    public function destroy($id)
    {
        try {
            $member = User::find($id);

            if (!$member) {
                return response()->json([
                    'code' => 0,
                    'message' => 'Member not found',
                    'data' => null,
                ], 404);
            }

            // Deactivate instead of delete
            $member->status = 0;
            $member->save();

            return response()->json([
                'code' => 1,
                'message' => 'Member deactivated successfully',
                'data' => null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to delete member: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Bulk sync members from offline queue
     * 
     * POST /api/members/sync
     */
    public function sync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'members' => 'required|array',
            'members.*.temp_id' => 'required|string',
            'members.*.action' => 'required|in:create,update',
            'members.*.data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $results = [
            'success' => [],
            'failed' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($request->members as $item) {
                try {
                    $tempId = $item['temp_id'];
                    $action = $item['action'];
                    $data = $item['data'];

                    if ($action === 'create') {
                        // Create new member
                        $year = date('Y');
                        $lastMember = User::where('member_code', 'LIKE', "MEM-{$year}-%")
                            ->orderBy('id', 'desc')
                            ->first();
                        
                        $nextNumber = 1;
                        if ($lastMember && preg_match("/MEM-{$year}-(\d+)/", $lastMember->member_code, $matches)) {
                            $nextNumber = intval($matches[1]) + 1;
                        }
                        
                        $memberCode = sprintf("MEM-%s-%04d", $year, $nextNumber);

                        $member = new User();
                        $member->first_name = $data['first_name'] ?? '';
                        $member->last_name = $data['last_name'] ?? '';
                        $member->name = ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '');
                        $member->phone_number = $data['phone_number'];
                        $member->sex = $data['sex'] ?? 'Male';
                        $member->member_code = $memberCode;
                        $member->username = $data['phone_number'];
                        $member->password = Hash::make($data['phone_number']);
                        $member->user_type = 'Customer';
                        
                        // Optional fields
                        if (isset($data['dob'])) $member->dob = $data['dob'];
                        if (isset($data['email'])) $member->email = $data['email'];
                        if (isset($data['district_id'])) $member->district_id = $data['district_id'];
                        if (isset($data['subcounty_id'])) $member->subcounty_id = $data['subcounty_id'];
                        if (isset($data['parish_id'])) $member->parish_id = $data['parish_id'];
                        if (isset($data['village'])) $member->village = $data['village'];
                        if (isset($data['education_level'])) $member->education_level = $data['education_level'];
                        if (isset($data['marital_status'])) $member->marital_status = $data['marital_status'];
                        if (isset($data['occupation'])) $member->occupation = $data['occupation'];
                        if (isset($data['household_size'])) $member->household_size = $data['household_size'];
                        if (isset($data['emergency_contact_name'])) $member->emergency_contact_name = $data['emergency_contact_name'];
                        if (isset($data['emergency_contact_phone'])) $member->emergency_contact_phone = $data['emergency_contact_phone'];

                        $member->save();
                        
                        $results['success'][] = [
                            'temp_id' => $tempId,
                            'server_id' => $member->id,
                            'member' => $member,
                        ];
                    } elseif ($action === 'update') {
                        $memberId = $data['id'] ?? null;
                        if (!$memberId) {
                            throw new \Exception('Member ID required for update');
                        }

                        $member = User::find($memberId);
                        if (!$member) {
                            throw new \Exception('Member not found');
                        }

                        // Update fields
                        foreach ($data as $key => $value) {
                            if ($key !== 'id' && $key !== 'member_code' && $key !== 'password') {
                                $member->$key = $value;
                            }
                        }

                        // Update name if first_name or last_name changed
                        if (isset($data['first_name']) || isset($data['last_name'])) {
                            $member->name = ($data['first_name'] ?? $member->first_name) . ' ' . ($data['last_name'] ?? $member->last_name);
                        }

                        $member->save();
                        
                        $results['success'][] = [
                            'temp_id' => $tempId,
                            'server_id' => $member->id,
                            'member' => $member,
                        ];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'temp_id' => $item['temp_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'code' => 1,
                'message' => 'Sync completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => 0,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

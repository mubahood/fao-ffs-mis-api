<?php

namespace App\Http\Controllers;

use App\Models\FfsGroup;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FfsGroupController extends Controller
{
    /**
     * Get list of groups with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = FfsGroup::query()
                ->with(['district', 'subcounty', 'parish', 'facilitator']);

            // Filter by type
            if ($request->has('type') && $request->type != 'All') {
                $query->where('type', $request->type);
            }

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('village', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $groups = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'code' => 1,
                'message' => 'Success',
                'data' => $groups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new group
     */
    public function store(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:FFS,FBS,VSLA,Association',
            'code' => 'nullable|string|unique:ffs_groups,code',
            'registration_date' => 'nullable|date',
            'district_id' => 'required|integer|exists:locations,id',
            'subcounty_id' => 'nullable|integer|exists:locations,id',
            'parish_id' => 'nullable|integer|exists:locations,id',
            'village' => 'nullable|string|max:255',
            'meeting_venue' => 'nullable|string|max:255',
            'meeting_day' => 'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'meeting_frequency' => 'nullable|in:Weekly,Bi-weekly,Monthly',
            'primary_value_chain' => 'required|string|max:100',
            'secondary_value_chains' => 'nullable|array',
            'facilitator_id' => 'nullable|integer|exists:users,id',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();

            // Auto-generate code if not provided
            if (empty($data['code'])) {
                $data['code'] = FfsGroup::generateGroupCode(
                    $data['type'],
                    $data['district_id']
                );
            }

            // Set default registration date
            if (empty($data['registration_date'])) {
                $data['registration_date'] = now()->format('Y-m-d');
            }

            // Set default status
            $data['status'] = 'Active';

            // Set created_by_id
            if (auth()->check()) {
                $data['created_by_id'] = auth()->id();
            }

            // Create group
            $group = FfsGroup::create($data);

            DB::commit();

            // Load relationships
            $group->load(['district', 'subcounty', 'parish', 'facilitator']);

            return response()->json([
                'code' => 1,
                'message' => 'Group registered successfully',
                'data' => $group,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code' => 0,
                'message' => 'Failed to create group: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get group by ID
     */
    public function show($id)
    {
        try {
            $group = FfsGroup::with(['district', 'subcounty', 'parish', 'facilitator'])
                ->findOrFail($id);

            return response()->json([
                'code' => 1,
                'message' => 'Success',
                'data' => $group,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Group not found',
            ], 404);
        }
    }

    /**
     * Get dropdown options for registration form
     */
    public function getFormOptions()
    {
        try {
            $districts = Location::where('type', 'District')
                ->orderBy('name')
                ->get(['id', 'name']);

            $facilitators = User::where('user_type', 'Admin')
                ->orWhere('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'phone_number']);

            $groupTypes = [
                ['value' => 'FFS', 'label' => 'Farmer Field School (FFS)'],
                ['value' => 'FBS', 'label' => 'Farmer Business School (FBS)'],
                ['value' => 'VSLA', 'label' => 'Village Savings & Loan (VSLA)'],
                ['value' => 'Association', 'label' => 'Group Association'],
            ];

            $meetingDays = [
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 
                'Friday', 'Saturday', 'Sunday'
            ];

            $meetingFrequencies = [
                ['value' => 'Weekly', 'label' => 'Weekly'],
                ['value' => 'Bi-weekly', 'label' => 'Bi-weekly'],
                ['value' => 'Monthly', 'label' => 'Monthly'],
            ];

            $valueChains = [
                'Maize', 'Beans', 'Sorghum', 'Millet', 'Groundnuts',
                'Simsim', 'Cassava', 'Sweet Potato', 'Vegetables',
                'Fruits', 'Poultry', 'Goats', 'Cattle', 'Beekeeping', 'Fish Farming'
            ];

            return response()->json([
                'code' => 1,
                'message' => 'Success',
                'data' => [
                    'districts' => $districts,
                    'facilitators' => $facilitators,
                    'group_types' => $groupTypes,
                    'meeting_days' => $meetingDays,
                    'meeting_frequencies' => $meetingFrequencies,
                    'value_chains' => $valueChains,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subcounties by district
     */
    public function getSubcounties($districtId)
    {
        try {
            $subcounties = Location::where('type', 'Subcounty')
                ->where('parent_id', $districtId)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'code' => 1,
                'message' => 'Success',
                'data' => $subcounties,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get parishes by subcounty
     */
    public function getParishes($subcountyId)
    {
        try {
            $parishes = Location::where('type', 'Parish')
                ->where('parent_id', $subcountyId)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'code' => 1,
                'message' => 'Success',
                'data' => $parishes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}

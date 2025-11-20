<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get all districts
     * 
     * GET /api/locations/districts
     */
    public function getDistricts()
    {
        try {
            $districts = Location::where('type', 'District')
                ->orWhere('parent', '<', 1)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code']);

            return response()->json([
                'code' => 1,
                'message' => 'Districts retrieved successfully',
                'data' => $districts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to retrieve districts: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get subcounties by district ID
     * 
     * GET /api/locations/subcounties/{districtId}
     */
    public function getSubcounties($districtId)
    {
        try {
            $subcounties = Location::where('parent', $districtId)
                ->orWhere(function($query) use ($districtId) {
                    $query->where('type', 'Subcounty')
                          ->where('parent_id', $districtId);
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'parent', 'parent_id']);

            return response()->json([
                'code' => 1,
                'message' => 'Subcounties retrieved successfully',
                'data' => $subcounties,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to retrieve subcounties: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get parishes by subcounty ID
     * 
     * GET /api/locations/parishes/{subcountyId}
     */
    public function getParishes($subcountyId)
    {
        try {
            $parishes = Location::where('parent', $subcountyId)
                ->orWhere(function($query) use ($subcountyId) {
                    $query->where('type', 'Parish')
                          ->where('parent_id', $subcountyId);
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'parent', 'parent_id']);

            return response()->json([
                'code' => 1,
                'message' => 'Parishes retrieved successfully',
                'data' => $parishes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'message' => 'Failed to retrieve parishes: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

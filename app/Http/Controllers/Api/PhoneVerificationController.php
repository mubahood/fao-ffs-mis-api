<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PhoneVerificationController extends Controller
{
    /**
     * Check if phone number exists and user is chairperson
     */
    public function checkPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
            ]);
        }

        $phone = $request->phone_number;
        
        // Remove all spaces and non-numeric chars except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // Get just the digits
        $digitsOnly = preg_replace('/[^\d]/', '', $phone);
        
        // Generate all possible phone number variants
        $phoneVariants = [
            $phone,                                    // Original
            $cleanPhone,                               // No spaces
            Utils::prepare_phone_number($phone),       // Adds +256 if needed
            '+256' . $digitsOnly,                      // +256XXXXXXXXX
            '+256 ' . substr($digitsOnly, -9),         // +256 XXXXXXXXX (with space)
            '0' . substr($digitsOnly, -9),             // 0XXXXXXXXX
            substr($digitsOnly, -9),                   // XXXXXXXXX
        ];
        
        // Remove duplicates
        $phoneVariants = array_unique($phoneVariants);

        // Search for user - use LIKE to handle spaces in database
        $user = User::where(function($query) use ($phoneVariants, $digitsOnly) {
            foreach ($phoneVariants as $variant) {
                $query->orWhere('phone_number', $variant)
                      ->orWhere('phone_number_2', $variant);
            }
            // Also search by digits only (removes spaces from DB value)
            $query->orWhereRaw("REPLACE(REPLACE(phone_number, ' ', ''), '+', '') LIKE ?", ["%{$digitsOnly}"])
                  ->orWhereRaw("REPLACE(REPLACE(phone_number_2, ' ', ''), '+', '') LIKE ?", ["%{$digitsOnly}"]);
        })->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'Phone number not registered in the system. Please contact admin for registration.',
                'data' => null,
            ]);
        }

        // Check if user is chairperson
        if ($user->is_group_admin !== 'Yes') {
            return response()->json([
                'status' => 0,
                'message' => 'Only chairpersons can register through this portal. Please contact your group administrator.',
                'data' => null,
            ]);
        }

        // Chairperson found - allow them to proceed with OTP verification for onboarding
        return response()->json([
            'status' => 1,
            'message' => 'Phone number verified successfully!',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'can_request_otp' => true,
                'is_chairperson' => true,
            ],
        ]);
    }

    /**
     * Send OTP code to phone number
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
            ]);
        }

        $phone = $request->phone_number;
        
        // Remove all spaces and non-numeric chars except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // Get just the digits
        $digitsOnly = preg_replace('/[^\d]/', '', $phone);
        
        // Generate all possible phone number variants
        $phoneVariants = [
            $phone,
            $cleanPhone,
            Utils::prepare_phone_number($phone),
            '+256' . $digitsOnly,
            '+256 ' . substr($digitsOnly, -9),
            '0' . substr($digitsOnly, -9),
            substr($digitsOnly, -9),
        ];
        
        $phoneVariants = array_unique($phoneVariants);

        // Verify user exists and is group admin
        $user = User::where(function($query) use ($phoneVariants, $digitsOnly) {
            foreach ($phoneVariants as $variant) {
                $query->orWhere('phone_number', $variant)
                      ->orWhere('phone_number_2', $variant);
            }
            $query->orWhereRaw("REPLACE(REPLACE(phone_number, ' ', ''), '+', '') LIKE ?", ["%{$digitsOnly}"])
                  ->orWhereRaw("REPLACE(REPLACE(phone_number_2, ' ', ''), '+', '') LIKE ?", ["%{$digitsOnly}"]);
        })->first();

        if (!$user || $user->is_group_admin !== 'Yes') {
            return response()->json([
                'status' => 0,
                'message' => 'Phone number not verified',
            ]);
        }

        // Delete any existing OTP for this phone
        OtpVerification::whereIn('phone_number', $phoneVariants)->delete();

        // Generate 6-digit OTP
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in database
        $otp = OtpVerification::create([
            'phone_number' => $phone,
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addYears(10),
            'user_id' => $user->id,
        ]);

        // Send SMS
        $message = "Your FAO FFS-MIS verification code is: {$otpCode}.";
        
        try {
            Utils::send_sms($phone, $message);
            
            return response()->json([
                'status' => 1,
                'message' => 'OTP code sent to your phone number',
                'data' => [
                    'otp_sent' => true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to send OTP. Please try again.',
            ]);
        }
    }

    /**
     * Verify OTP code and return user data for pre-filling
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
            ]);
        }

        $phone = $request->phone_number;
        $code = $request->otp_code;
        
        // Remove all spaces and non-numeric chars except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // Get just the digits
        $digitsOnly = preg_replace('/[^\d]/', '', $phone);
        
        // Generate all possible phone number variants
        $phoneVariants = [
            $phone,
            $cleanPhone,
            Utils::prepare_phone_number($phone),
            '+256' . $digitsOnly,
            '+256 ' . substr($digitsOnly, -9),
            '0' . substr($digitsOnly, -9),
            substr($digitsOnly, -9),
        ];
        
        $phoneVariants = array_unique($phoneVariants);
        
        $otp = OtpVerification::whereIn('phone_number', $phoneVariants)
            ->where('otp_code', $code)
            ->whereNull('verified_at')
            ->first();

        if (!$otp) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid OTP code',
            ]);
        }

        // Mark OTP as verified
        $otp->verified_at = Carbon::now();
        $otp->save();

        // Get user data with group information
        $user = User::where(function($query) use ($phoneVariants, $digitsOnly) {
            foreach ($phoneVariants as $variant) {
                $query->orWhere('phone_number', $variant)
                      ->orWhere('phone_number_2', $variant);
            }
            $query->orWhereRaw("REPLACE(REPLACE(phone_number, ' ', ''), '+', '') LIKE ?", ["%{$digitsOnly}"])
                  ->orWhereRaw("REPLACE(REPLACE(phone_number_2, ' ', ''), '+', '') LIKE ?", ["%{$digitsOnly}"]);
        })->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'User not found',
            ]);
        }

        // Get group information
        $group = null;
        if ($user->group_id) {
            $group = \App\Models\FfsGroup::find($user->group_id);
        }

        // Get secretary and treasurer information if available
        $secretary = null;
        $treasurer = null;
        
        if ($group) {
            $secretary = User::where('group_id', $group->id)
                ->where('is_group_secretary', 'Yes')
                ->first();
            
            $treasurer = User::where('group_id', $group->id)
                ->where('is_group_treasurer', 'Yes')
                ->first();
        }

        return response()->json([
            'status' => 1,
            'message' => 'OTP verified successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_number' => $user->phone_number,
                    'phone_number_2' => $user->phone_number_2,
                    'email' => $user->email,
                    'is_group_admin' => $user->is_group_admin,
                    'is_group_secretary' => $user->is_group_secretary,
                    'is_group_treasurer' => $user->is_group_treasurer,
                    'group_id' => $user->group_id,
                ],
                'group' => $group ? [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => $group->type,
                ] : null,
                'secretary' => $secretary ? [
                    'name' => $secretary->name,
                    'phone_number' => $secretary->phone_number,
                    'email' => $secretary->email,
                ] : null,
                'treasurer' => $treasurer ? [
                    'name' => $treasurer->name,
                    'phone_number' => $treasurer->phone_number,
                    'email' => $treasurer->email,
                ] : null,
            ],
        ]);
    }
}

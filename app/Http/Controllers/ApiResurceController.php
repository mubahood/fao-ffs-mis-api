<?php

namespace App\Http\Controllers;

use App\Models\Association;
use App\Models\ChatHead;
use App\Models\ChatMessage;
use App\Models\CounsellingCentre;
use App\Models\Crop;
use App\Models\CropProtocol;
use App\Models\DeliveryAddress;
use App\Models\Event;
use App\Models\Garden;
use App\Models\GardenActivity;
use App\Models\Group;
use App\Models\Image;
use App\Models\Institution;
use App\Models\Job;
use App\Models\Location;
use App\Models\MembershipPayment;
use App\Models\NewsPost;
use App\Models\Order;
use App\Models\OrderedItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SearchHistory;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Utils;
use App\Services\PesapalService;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiResurceController extends Controller
{

    use ApiResponser;


    public function become_vendor(Request $request)
    {
        $administrator_id = $request->user;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (
            $request->first_name == null ||
            strlen($request->first_name) < 2
        ) {
            return $this->error('First name is missing.');
        }
        //validate all
        if (
            $request->last_name == null ||
            strlen($request->last_name) < 2
        ) {
            return $this->error('Last name is missing.');
        }

        //validate all
        if (
            $request->business_name == null ||
            strlen($request->business_name) < 2
        ) {
            return $this->error('Business name is missing.');
        }

        if (
            $request->business_license_number == null ||
            strlen($request->business_license_number) < 2
        ) {
            return $this->error('Business license number is missing.');
        }

        if (
            $request->business_license_issue_authority == null ||
            strlen($request->business_license_issue_authority) < 2
        ) {
            return $this->error('Business license issue authority is missing.');
        }

        if (
            $request->business_license_issue_date == null ||
            strlen($request->business_license_issue_date) < 2
        ) {
            return $this->error('Business license issue date is missing.');
        }

        if (
            $request->business_license_validity == null ||
            strlen($request->business_license_validity) < 2
        ) {
            return $this->error('Business license validity is missing.');
        }

        if (
            $request->business_address == null ||
            strlen($request->business_address) < 2
        ) {
            return $this->error('Business address is missing.');
        }

        if (
            $request->business_phone_number == null ||
            strlen($request->business_phone_number) < 2
        ) {
            return $this->error('Business phone number is missing.');
        }

        if (
            $request->business_whatsapp == null ||
            strlen($request->business_whatsapp) < 2
        ) {
            return $this->error('Business whatsapp is missing.');
        }

        if (
            $request->business_email == null ||
            strlen($request->business_email) < 2
        ) {
            return $this->error('Business email is missing.');
        }




        $msg = "";
        $u->first_name = $request->first_name;
        $u->last_name = $request->last_name;
        $u->nin = $request->campus_id;
        $u->business_name = $request->business_name;
        $u->business_license_number = $request->business_license_number;
        $u->business_license_issue_authority = $request->business_license_issue_authority;
        $u->business_license_issue_date = $request->business_license_issue_date;
        $u->business_license_validity = $request->business_license_validity;
        $u->business_address = $request->business_address;
        $u->business_phone_number = $request->business_phone_number;
        $u->business_whatsapp = $request->business_whatsapp;
        $u->business_email = $request->business_email;
        $u->business_cover_photo = $request->business_cover_photo;
        $u->business_cover_details = $request->business_cover_details;


        if ($u->status != 'Active') {
            $u->status = 'Pending';
        }

        $images = [];
        if (!empty($_FILES)) {
            $images = Utils::upload_images_2($_FILES, false);
        }
        if (!empty($images)) {
            $u->business_logo = 'images/' . $images[0];
        }

        $code = 1;
        try {
            $u->save();
            $msg = "Submitted successfully.";
            return $this->success($u, $msg, $code);
        } catch (\Throwable $th) {
            $msg = $th->getMessage();
            $code = 0;
            return $this->error($msg);
        }
        return $this->success(null, $msg, $code);
    }


    public function update_profile(Request $request)
    {
        $administrator_id = $request->user;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        // Log the incoming data for debugging - expanded logging
        Log::info('Profile update request - All data:', [
            'all_request_data' => $request->all(),
            'first_name_raw' => $request->first_name,
            'first_name_exists' => $request->has('first_name'),
            'first_name_filled' => $request->filled('first_name'),
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
        ]);

        // Validate required fields with detailed logging
        $first_name = trim($request->first_name ?? '');
        if (empty($first_name) || strlen($first_name) < 2) {
            Log::warning('First name validation failed', [
                'first_name_raw' => $request->first_name,
                'first_name_trimmed' => $first_name,
                'trimmed_length' => strlen($first_name)
            ]);
            return $this->error('First name is required and must be at least 2 characters.');
        }

        $last_name = trim($request->last_name ?? '');
        if (empty($last_name) || strlen($last_name) < 2) {
            Log::warning('Last name validation failed', [
                'last_name_raw' => $request->last_name,
                'last_name_trimmed' => $last_name,
                'trimmed_length' => strlen($last_name)
            ]);
            return $this->error('Last name is required and must be at least 2 characters.');
        }

        $phone_number_1 = trim($request->phone_number_1 ?? '');
        if (empty($phone_number_1) || strlen($phone_number_1) < 5) {
            Log::warning('Phone number validation failed', [
                'phone_number_1_raw' => $request->phone_number_1,
                'phone_number_1_trimmed' => $phone_number_1,
                'trimmed_length' => strlen($phone_number_1)
            ]);
            return $this->error('Phone number is required and must be at least 5 characters.');
        }

        // Check for duplicate phone number
        $anotherUser = Administrator::where('phone_number', $request->phone_number_1)
            ->where('id', '!=', $u->id)
            ->first();
        if ($anotherUser != null) {
            return $this->error('Phone number is already taken.');
        }

        // Check for duplicate username (phone)
        $anotherUser = Administrator::where('username', $request->phone_number_1)
            ->where('id', '!=', $u->id)
            ->first();
        if ($anotherUser != null) {
            return $this->error('Phone number is already taken.');
        }

        // Validate email if provided
        if ($request->email != null && strlen($request->email) > 5) {
            if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Invalid email address.');
            }

            $anotherUser = Administrator::where('email', $request->email)
                ->where('id', '!=', $u->id)
                ->first();
            if ($anotherUser != null) {
                return $this->error('Email is already taken.');
            }

            $anotherUser = Administrator::where('username', $request->email)
                ->where('id', '!=', $u->id)
                ->first();
            if ($anotherUser != null) {
                return $this->error('Email is already taken.');
            }
        }

        // Validate date of birth if provided
        if ($request->date_of_birth != null && strlen($request->date_of_birth) > 0) {
            try {
                $date = \Carbon\Carbon::createFromFormat('Y-m-d', $request->date_of_birth);
                if ($date->isFuture()) {
                    return $this->error('Date of birth cannot be in the future.');
                }
            } catch (\Exception $e) {
                return $this->error('Invalid date of birth format. Use YYYY-MM-DD.');
            }
        }

        try {
            // Update basic information using the validated trimmed values
            $u->first_name = ucfirst($first_name);
            $u->last_name = ucfirst($last_name);
            $u->phone_number = $phone_number_1;  // Correct database field
            $u->name = ucfirst($first_name) . ' ' . ucfirst($last_name);  // Update full name

            // Update email if provided
            if ($request->email != null && strlen($request->email) > 5) {
                $u->email = strtolower(trim($request->email));
            }

            // Update optional fields
            if ($request->date_of_birth != null) {
                $u->dob = $request->date_of_birth; // Correct database field is 'dob'
            }

            if ($request->gender != null && in_array($request->gender, ['male', 'female', 'other', 'prefer-not-to-say'])) {
                $u->sex = $request->gender;
            }

            // Also handle 'sex' field directly for compatibility
            if ($request->sex != null && in_array($request->sex, ['male', 'female', 'other', 'prefer-not-to-say'])) {
                $u->sex = $request->sex;
            }

            if ($request->bio != null) {
                $u->intro = trim($request->bio);
            }

            // Also handle 'intro' field directly for compatibility
            if ($request->intro != null) {
                $u->intro = trim($request->intro);
            }

            if ($request->address != null) {
                $u->address = ucfirst(trim($request->address));
            }

            // Handle avatar upload if provided
            $images = [];
            if (!empty($_FILES)) {
                $images = Utils::upload_images_2($_FILES, false);
                if (!empty($images)) {
                    $u->avatar = 'images/' . $images[0];
                }
            }

            $u->save();

            // Return updated user data
            $updatedUser = Administrator::find($u->id);
            return $this->success($updatedUser, "Profile updated successfully.", 1);
        } catch (\Throwable $th) {
            return $this->error('Failed to update profile: ' . $th->getMessage());
        }
    }


    /**
     * Comprehensive profile update endpoint
     * Handles all profile fields including insurance-specific data
     * POST /api/profile-update-comprehensive
     */
    public function profile_update_comprehensive(Request $request)
    {
        $administrator_id = $request->user;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        // Log incoming data for debugging
        Log::info('Comprehensive profile update request', [
            'user_id' => $administrator_id,
            'data' => $request->except(['password', 'file'])
        ]);

        // Validate required fields
        $first_name = trim($request->first_name ?? '');
        if (empty($first_name) || strlen($first_name) < 2) {
            return $this->error('First name is required and must be at least 2 characters.');
        }

        $last_name = trim($request->last_name ?? '');
        if (empty($last_name) || strlen($last_name) < 2) {
            return $this->error('Last name is required and must be at least 2 characters.');
        }

        $phone_number = trim($request->phone_number ?? '');
        if (empty($phone_number) || strlen($phone_number) < 5) {
            return $this->error('Phone number is required and must be at least 5 characters.');
        }

        // Check for duplicate phone number
        $anotherUser = Administrator::where('phone_number', $phone_number)
            ->where('id', '!=', $u->id)
            ->first();
        if ($anotherUser != null) {
            return $this->error('Phone number is already taken.');
        }

        // Check for duplicate username (phone)
        $anotherUser = Administrator::where('username', $phone_number)
            ->where('id', '!=', $u->id)
            ->first();
        if ($anotherUser != null) {
            return $this->error('Phone number is already taken.');
        }

        // Validate email if provided
        if ($request->email != null && strlen($request->email) > 5) {
            if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Invalid email address.');
            }

            $anotherUser = Administrator::where('email', $request->email)
                ->where('id', '!=', $u->id)
                ->first();
            if ($anotherUser != null) {
                return $this->error('Email is already taken.');
            }

            $anotherUser = Administrator::where('username', $request->email)
                ->where('id', '!=', $u->id)
                ->first();
            if ($anotherUser != null) {
                return $this->error('Email is already taken.');
            }
        }

        // Validate date of birth if provided
        if ($request->dob != null && strlen($request->dob) > 0) {
            try {
                $date = \Carbon\Carbon::parse($request->dob);
                if ($date->isFuture()) {
                    return $this->error('Date of birth cannot be in the future.');
                }
            } catch (\Exception $e) {
                return $this->error('Invalid date of birth format.');
            }
        }

        try {
            // Update basic information
            $u->first_name = ucfirst($first_name);
            $u->last_name = ucfirst($last_name);
            $u->name = ucfirst($first_name) . ' ' . ucfirst($last_name);
            $u->phone_number = $phone_number;

            // Update gender/sex
            if ($request->sex != null && in_array($request->sex, ['Male', 'Female', 'male', 'female'])) {
                $u->sex = ucfirst(strtolower($request->sex));
            }

            // Update date of birth
            if ($request->dob != null && strlen($request->dob) > 0) {
                $u->dob = $request->dob;
            }

            // Update email
            if ($request->email != null && strlen($request->email) > 5) {
                $u->email = strtolower(trim($request->email));
            }

            // Update country and address
            if ($request->nationality != null) {
                $u->country = $request->nationality;
            }

            if ($request->referral != null) {
                // District field - stored in country for now
                $u->address = ucfirst(trim($request->referral));
            }

            if ($request->home_address != null) {
                $u->address = ucfirst(trim($request->home_address));
            }

            if ($request->current_address != null) {
                // Can be stored in intro or another field
                $u->intro = ucfirst(trim($request->current_address));
            }

            // Update tribe (swimming field)
            if ($request->swimming != null) {
                $u->occupation = trim($request->swimming); // Store tribe in occupation field
            }

            // Update parent names
            if ($request->father_name != null) {
                $u->title = ucfirst(trim($request->father_name)); // Store father's name in title field
            }

            if ($request->mother_name != null) {
                $u->about = ucfirst(trim($request->mother_name)); // Store mother's name in about field
            }

            // Update children names (using available fields creatively)
            // We'll need to add these to the database schema or use existing fields
            // For now, we'll concatenate them in a JSON format in a text field
            $childrenData = [];
            if ($request->transportation != null) {
                $childrenData['child_1'] = trim($request->transportation);
            }
            if ($request->residential_type != null) {
                $childrenData['child_2'] = trim($request->residential_type);
            }
            if ($request->school_pay_account_id != null) {
                $childrenData['child_3'] = trim($request->school_pay_account_id);
            }
            if ($request->phd_university_year_graduated != null) {
                $childrenData['child_4'] = trim($request->phd_university_year_graduated);
            }

            // Store children data as JSON in remember_token or another text field
            if (!empty($childrenData)) {
                $u->remember_token = json_encode($childrenData);
            }

            // Update sponsor ID
            if ($request->phd_university_name != null) {
                $u->username = trim($request->phd_university_name); // Store sponsor ID in username backup
            }

            // Handle password change if provided
            if ($request->password != null && strlen($request->password) >= 6) {
                $u->password = password_hash($request->password, PASSWORD_DEFAULT);
            }

            // Handle avatar upload if provided
            if (!empty($_FILES)) {
                $images = Utils::upload_images_2($_FILES, false);
                if (!empty($images)) {
                    $u->avatar = 'images/' . $images[0];
                    $u->profile_photo = 'images/' . $images[0];
                }
            }

            $u->save();

            // Fetch fresh user data from database to ensure all relationships and computed fields are included
            $updatedUser = Administrator::with(['roles', 'permissions'])
                ->find($u->id);
            
            // Ensure all fields are fresh from database
            $updatedUser->refresh();
            
            // Add insurance-specific field mappings for frontend compatibility
            // Map stored fields to expected field names
            $updatedUser->swimming = $updatedUser->occupation ?? ''; // Tribe
            $updatedUser->tribe = $updatedUser->occupation ?? ''; // Alternative field name
            $updatedUser->father_name = $updatedUser->title ?? ''; // Father's name
            $updatedUser->mother_name = $updatedUser->about ?? ''; // Mother's name
            
            // Parse children data from JSON if stored
            $childrenData = [];
            if (!empty($updatedUser->remember_token)) {
                try {
                    $childrenData = json_decode($updatedUser->remember_token, true);
                    if (is_array($childrenData)) {
                        $updatedUser->child_1 = $childrenData['child_1'] ?? '';
                        $updatedUser->child_2 = $childrenData['child_2'] ?? '';
                        $updatedUser->child_3 = $childrenData['child_3'] ?? '';
                        $updatedUser->child_4 = $childrenData['child_4'] ?? '';
                        $updatedUser->transportation = $childrenData['child_1'] ?? ''; // 1st child
                        $updatedUser->residential_type = $childrenData['child_2'] ?? ''; // 2nd child
                        $updatedUser->school_pay_account_id = $childrenData['child_3'] ?? ''; // 3rd child
                        $updatedUser->phd_university_year_graduated = $childrenData['child_4'] ?? ''; // 4th child
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse children data', [
                        'user_id' => $updatedUser->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Add sponsor ID with alternative field names
            $updatedUser->sponsor_id = $updatedUser->username ?? '';
            $updatedUser->phd_university_name = $updatedUser->username ?? '';
            
            // Ensure avatar URL is complete
            if (!empty($updatedUser->avatar) && !str_starts_with($updatedUser->avatar, 'http')) {
                $updatedUser->avatar_url = url('storage/' . $updatedUser->avatar);
            }
            
            if (!empty($updatedUser->profile_photo) && !str_starts_with($updatedUser->profile_photo, 'http')) {
                $updatedUser->profile_photo_url = url('storage/' . $updatedUser->profile_photo);
            }
            
            Log::info('Profile updated successfully - returning fresh data', [
                'user_id' => $updatedUser->id,
                'name' => $updatedUser->name,
                'avatar' => $updatedUser->avatar,
                'profile_photo' => $updatedUser->profile_photo,
                'swimming/tribe' => $updatedUser->swimming,
                'father_name' => $updatedUser->father_name,
                'mother_name' => $updatedUser->mother_name,
                'transportation' => $updatedUser->transportation,
                'residential_type' => $updatedUser->residential_type,
                'school_pay_account_id' => $updatedUser->school_pay_account_id,
                'phd_university_year_graduated' => $updatedUser->phd_university_year_graduated,
                'phd_university_name/sponsor' => $updatedUser->phd_university_name
            ]);

            return $this->success($updatedUser, "Profile updated successfully.", 1);
        } catch (\Throwable $th) {
            Log::error('Profile update failed', [
                'user_id' => $administrator_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return $this->error('Failed to update profile: ' . $th->getMessage());
        }
    }

    public function delete_profile(Request $request)
    {
        $administrator_id = $request->user;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }
        $u->status = 'Deleted';
        $u->save();
        return $this->success(null, $message = "Deleted successfully!", 1);

        if (
            $request->first_name == null ||
            strlen($request->first_name) < 2
        ) {
            return $this->error('First name is missing.');
        }
        //validate all
        if (
            $request->last_name == null ||
            strlen($request->last_name) < 2
        ) {
            return $this->error('Last name is missing.');
        }

        if (
            $request->phone_number_1 == null ||
            strlen($request->phone_number_1) < 5
        ) {
            return $this->error('Phone number is requried.');
        }

        $anotherUser = Administrator::where([
            'phone_number' => $request->phone_number_1
        ])->first();
        if ($anotherUser != null) {
            if ($anotherUser->id != $u->id) {
                return $this->error('Phone number is already taken.');
            }
        }

        $anotherUser = Administrator::where([
            'username' => $request->phone_number_1
        ])->first();
        if ($anotherUser != null) {
            if ($anotherUser->id != $u->id) {
                return $this->error('Phone number is already taken.');
            }
        }

        $anotherUser = Administrator::where([
            'email' => $request->phone_number_1
        ])->first();
        if ($anotherUser != null) {
            if ($anotherUser->id != $u->id) {
                return $this->error('Phone number is already taken.');
            }
        }

        if (
            $request->email != null &&
            strlen($request->email) > 5
        ) {
            $anotherUser = Administrator::where([
                'email' => $request->email
            ])->first();
            if ($anotherUser != null) {
                if ($anotherUser->id != $u->id) {
                    return $this->error('Email is already taken.');
                }
            }
            //check for username as well
            $anotherUser = Administrator::where([
                'username' => $request->email
            ])->first();
            if ($anotherUser != null) {
                if ($anotherUser->id != $u->id) {
                    return $this->error('Email is already taken.');
                }
            }
            //validate email
            if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Invalid email address.');
            }
        }



        $msg = "";
        //first letter to upper case
        $u->first_name = $request->first_name;

        //change first letter to upper case
        $u->first_name = ucfirst($u->first_name);


        $u->last_name = ucfirst($request->last_name);
        $u->phone_number = $request->phone_number_1;
        $u->email = $request->email;
        $u->address = ucfirst($request->address);

        $images = [];
        if (!empty($_FILES)) {
            $images = Utils::upload_images_2($_FILES, false);
        }
        if (!empty($images)) {
            $u->avatar = 'images/' . $images[0];
        }

        $code = 1;
        try {
            $u->save();
            $msg = "Updated successfully.";
            return $this->success($u, $msg, $code);
        } catch (\Throwable $th) {
            $msg = $th->getMessage();
            $code = 0;
            return $this->error($msg);
        }
        return $this->success(null, $msg, $code);
    }

    public function password_change(Request $request)
    {
        $administrator_id = $request->user;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (
            $request->password == null ||
            strlen($request->password) < 2
        ) {
            return $this->error('Password is missing.');
        }

        //check if  current_password 
        if (
            $request->current_password == null ||
            strlen($request->current_password) < 2
        ) {
            return $this->error('Current password is missing.');
        }

        //check if  current_password
        if (
            !(password_verify($request->current_password, $u->password))
        ) {
            return $this->error('Current password is incorrect.');
        }

        $u->password = password_hash($request->password, PASSWORD_DEFAULT);
        $msg = "";
        $code = 1;
        try {
            $u->save();
            $msg = "Password changed successfully.";
            return $this->success($u, $msg, $code);
        } catch (\Throwable $th) {
            $msg = $th->getMessage();
            $code = 0;
            return $this->error($msg);
        }
        return $this->success(null, $msg, $code);
    }

    public function account_verification(Request $request)
    {
        $administrator_id = $request->user;
        $u = User::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        if ($request->task == null) {
            return $this->error('Task is missing.');
        }

        if (
            $request->email == null ||
            strlen($request->email) < 2
        ) {
            return $this->error('Email is missing.');
        }

        $other_user = User::where([
            'email' => $request->email
        ])->first();

        if ($other_user != null) {
            if ($other_user->id != $u->id) {
                return $this->error('Email is already taken.');
            }
        }
        $other_user = User::where([
            'username' => $request->email
        ])->first();
        if ($other_user != null) {
            if ($other_user->id != $u->id) {
                return $this->error('Email is already taken.');
            }
        }

        if ($request->task == 'request_verification_code') {
            try {
                $u->send_verification_code($request->email);
            } catch (\Throwable $th) {
                return $this->error('Failed to send verification code because ' . $th->getMessage() . '.');
            }
            return $this->success($u, 'Verification code sent to your email address ' . $u->email . '.');
        } else if ($request->task == 'verify_code') {
            $code = $request->code;
            if ($code == null || strlen($code) < 3) {
                return $this->error('Code is required.');
            }
            if ($u->intro != $code) {
                return $this->error('Invalid code.');
            }
            $u->complete_profile = 'Yes';
            $u->email = $request->email;
            $u->username = $request->email;
            try {
                $u->save();
            } catch (\Throwable $th) {
                return $this->error('Failed to verify email because ' . $th->getMessage() . '.');
            }
            return $this->success($u, 'Email verified successfully.');
        }
        return $this->error('Task not found.');
    }



    public function upload_media(Request $request)
    {
        $administrator_id = $request->user;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (
            !isset($request->parent_local_id) ||
            $request->parent_local_id == null
        ) {
            return $this->error('Local parent ID is missing.');
        }

        //  strlen($request->parent_local_id) < 6
        if (
            strlen($request->parent_local_id) < 6
        ) {
            return $this->error('Local parent ID is too short.');
        }


        if (
            empty($_FILES)
        ) {
            return $this->error('No files found.');
        }



        $images = Utils::upload_images_2($_FILES, false);
        $_images = [];


        if (empty($images)) {
            return $this->error('Failed to upload files.');
        }

        $msg = "";
        foreach ($images as $src) {

            $img = new Image();
            $img->administrator_id =  $administrator_id;
            $img->src =  $src;
            $img->thumbnail =  null;
            $img->parent_endpoint =  $request->parent_endpoint;
            $img->parent_local_id =  $request->parent_local_id;
            $img->type =  $request->type;
            $img->parent_id =  (int)($request->parent_id);
            $pro = Product::where(['local_id' => $img->parent_local_id])->first();
            $img->product_id =  null;
            if ($pro != null) {
                $img->product_id =  $pro->id;
            }
            $img->size = 0;
            $img->note = '';
            if (
                isset($request->note)
            ) {
                $img->note =  $request->note;
            }
            $img->save();
            $_images[] = $img;
        }

        return $this->success(
            null,
            count($_images) . " Files uploaded successfully."
        );
    }



    public function vendors(Request $r)
    {
        $vendors = Administrator::where([
            'user_type' => 'Vendor'
        ])->get();
        return $this->success($vendors, $message = "Success!", 200);
    }


    public function order(Request $r)
    {

        $order = Order::find($r->id);
        if ($order == null) {
            return $this->error('Order not found.');
        }

        if ($order->stripe_url == null || strlen($order->stripe_url) < 8) {
            /*   $order->create_payment_link();
            $order->save(); */
        }

        return $this->success($order, $message = "Success!", 200);
    }

    //product_get_by_id
    public function product_get_by_id(Request $r)
    {
        try {
            $product = Product::with(['specifications', 'productCategory'])->find($r->id);
            if ($product == null) {
                return $this->error('Product not found.');
            }

            // Add computed attributes for better frontend consumption
            $product->tags_array = $product->tags_array;

            // Safe specifications mapping
            $product->attributes_array = $product->specifications ? $product->specifications->map(function ($attr) {
                return [
                    'name' => $attr->name ?? '',
                    'value' => $attr->value ?? '',
                ];
            })->toArray() : [];

            // Add category information if available
            $product->category_info = [];
            if ($product->productCategory) {
                $product->category_info = [
                    'id' => $product->productCategory->id,
                    'name' => $product->productCategory->name ?? '',
                    'description' => $product->productCategory->description ?? '',
                ];
            }

            return $this->success($product, $message = "Success!", 200);
        } catch (\Exception $e) {
            \Log::error('Error in product_get_by_id: ' . $e->getMessage());
            return $this->error('Unable to retrieve product data. Please try again later.');
        }
    }

    //orders_get_by_id
    public function orders_get_by_id(Request $r)
    {
        $order = Order::find($r->id);
        if ($order == null) {
            return $this->error('Order not found.');
        }
        return $this->success($order, $message = "Success!", 200);
    }


    public function orders_get(Request $r)
    {

        $u = auth('api')->user();
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }

        if ($u == null) {
            return $this->error('User not found.');
        }
        $orders = [];

        foreach (
            Order::where([
                'user' => $u->id
            ])->get() as $order
        ) {
            $items = $order->get_items();
            $order->items = json_encode($items);
            $orders[] = $order;
        }
        return $this->success($orders, $message = "Success!", 200);
    }


    public function orders_cancel(Request $r)
    {

        $u = auth('api')->user();
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }

        if ($u == null) {
            return $this->error('User not found.');
        }

        $order = Order::find($r->id);
        if ($order == null) {
            return $this->error('Order not found.');
        }
        $order->delete();
        return $this->success(null, $message = "Cancelled successfully!", 200);
    }

    /**
     * Check and send pending order emails
     * This endpoint is designed to be called by a cron job every minute
     * to ensure no order emails are missed
     */
    public function check_and_send_pending_emails(Request $request)
    {
        // Set time limit for this operation
        set_time_limit(120); // 2 minutes max

        try {
            Log::info('=== Starting pending email check via API endpoint ===');

            // Track statistics
            $stats = [
                'total_orders_checked' => 0,
                'emails_pending' => 0,
                'emails_sent' => 0,
                'errors' => 0,
                'by_type' => [
                    'pending' => 0,
                    'processing' => 0,
                    'completed' => 0,
                    'canceled' => 0,
                    'failed' => 0
                ],
                'start_time' => now(),
            ];

            // Get orders that might need email notifications
            // Only get orders that have valid email addresses and are from last 7 days
            $orders = Order::where('created_at', '>=', now()->subDays(7))
                ->whereNotNull('mail')
                ->where('mail', '!=', '')
                ->where('mail', 'LIKE', '%@%') // Basic email validation
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get();

            Log::info("Found {$orders->count()} orders to check for pending emails");
            $stats['total_orders_checked'] = $orders->count();

            foreach ($orders as $order) {
                try {
                    // Use the SAME logic as Order model to determine what email type should be sent
                    $emailType = $this->getEmailTypeToSend($order);

                    if ($emailType) {
                        $stats['emails_pending']++;
                        Log::info("Order {$order->id}: Needs {$emailType} email (state: {$order->order_state})");

                        // Use the Order model's send_mails method which already has proper tracking
                        Order::send_mails($order);

                        $stats['emails_sent']++;
                        $stats['by_type'][$emailType]++;

                        // Small delay to prevent overwhelming the mail server
                        usleep(100000); // 0.1 seconds
                    } else {
                        Log::debug("Order {$order->id}: No email needed (state: {$order->order_state})");
                    }
                } catch (\Throwable $e) {
                    Log::error("Error processing order {$order->id}: " . $e->getMessage());
                    $stats['errors']++;
                }
            }

            $stats['end_time'] = now();
            $stats['execution_time_seconds'] = $stats['end_time']->diffInSeconds($stats['start_time']);

            Log::info('=== Email check completed ===', $stats);

            return $this->success([
                'message' => 'Email check completed successfully',
                'statistics' => $stats
            ], 'Email check completed', 200);
        } catch (\Throwable $e) {
            Log::error('Critical error in check_and_send_pending_emails: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return $this->error('Failed to check pending emails: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper method to determine what type of email needs to be sent
     * This MUST match exactly the logic in Order::getEmailTypeToSend() to prevent duplicate emails
     */
    private function getEmailTypeToSend($order)
    {
        $stateMap = [
            0 => 'pending',
            1 => 'processing',
            2 => 'completed',
            3 => 'canceled',
            4 => 'failed'
        ];

        $state = (int)$order->order_state;

        if (!isset($stateMap[$state])) {
            Log::warning("Unknown order state {$state} for order {$order->id}");
            return null;
        }

        $emailType = $stateMap[$state];
        $sentField = $emailType . '_mail_sent';

        // Check if this email type has already been sent - CRITICAL: This prevents duplicate emails
        $alreadySent = $order->{$sentField} === 'Yes';

        // Additional logic: For pending orders, check if order is older than 5 minutes
        // This prevents sending emails immediately after order creation
        if ($emailType === 'pending') {
            $orderAge = $order->created_at->diffInMinutes(now());
            if ($orderAge < 5) {
                Log::info("Order {$order->id} is too new ({$orderAge} minutes), skipping pending email");
                return null;
            }
        }

        Log::debug("Order {$order->id} - State: {$state} ({$emailType}), {$sentField}: " . ($order->{$sentField} ?? 'NULL') . ", Already sent: " . ($alreadySent ? 'Yes' : 'No'));

        // Return null if email was already sent, otherwise return the email type
        return $alreadySent ? null : $emailType;
    }

    public function my_profile(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }

        if ($u == null) {
            return $this->error('User not found.');
        }
        $data[] = $u;
        return $this->success($data, $message = "Success!", 200);
    }

    /**
     * Get all FFS groups for registration dropdown
     * Returns active groups with essential information
     */
    public function ffs_groups(Request $r)
    {
        try {
            $groups = \App\Models\FfsGroup::where('status', 'Active')
                ->select([
                    'id',
                    'name',
                    'code',
                    'type',
                    'district_id',
                    'village',
                    'total_members',
                    'facilitator_id'
                ])
                ->orderBy('name', 'asc')
                ->get();
            
            // Add computed fields
            $groups->each(function($group) {
                $group->type_text = $group->type_text ?? $group->type;
                $group->district_name = $group->district_name ?? '';
                $group->facilitator_name = $group->facilitator_name ?? '';
            });
            
            return $this->success($groups, 'Groups retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve groups: ' . $e->getMessage());
        }
    }

    /**
     * Get single FFS group details by ID
     */
    public function ffs_group_show(Request $r, $id)
    {
        try {
            $group = \App\Models\FfsGroup::find($id);
            
            if (!$group) {
                return $this->error('Group not found.');
            }
            
            return $this->success($group, 'Group details retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve group: ' . $e->getMessage());
        }
    }

    public function orders_create(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }


        if ($u == null) {
            return $this->error('User not found.');
        }

        $items = [];
        try {
            $items = json_decode($r->items);
        } catch (\Throwable $th) {
            $items = [];
        }
        foreach ($items as $key => $value) {
            $p = Product::find($value->product_id);
            if ($p == null) {
                return $this->error("Product #" . $value->product_id . " not found.");
            }
        }


        $delivery = null;
        try {
            $delivery = json_decode($r->delivery);
        } catch (\Throwable $th) {
            $delivery = null;
        }

        if ($delivery == null) {
            return $this->error('Delivery information is missing.');
        }
        if ($delivery->customer_phone_number_1 == null) {
            $delivery->customer_phone_number_1 = $u->phone_number;
        }

        $order = new Order();
        $order->user = $u->id;
        $order->order_state = 0;
        $order->temporary_id = 0;
        $order->amount = 0;
        $order->order_total = 0;
        $order->payment_confirmation = '';
        $order->payment_gateway = 'manual'; // Default payment gateway for existing method
        $order->payment_status = 'PENDING_PAYMENT';
        $order->description = '';
        $order->mail = $u->email;

        // Handle pay on delivery option
        $order->pay_on_delivery = $r->pay_on_delivery === 'true' || $r->pay_on_delivery === true || $r->pay_on_delivery === 1;

        // If pay on delivery is selected, update payment status and confirmation
        if ($order->pay_on_delivery) {
            $order->payment_status = 'PAY_ON_DELIVERY';
            $order->payment_gateway = 'cash_on_delivery';
        }

        $delivery_amount = 0;
        if ($delivery != null) {
            try {

                $order->order_details = json_encode($delivery);

                $del_loc = DeliveryAddress::find($delivery->delivery_district);
                if ($del_loc != null) {


                    $delivery_amount = (int)($del_loc->shipping_cost);

                    $order->date_created = $delivery->date_created;
                    $order->date_updated = $delivery->date_updated;
                    $order->mail = $delivery->mail;
                    $order->delivery_district = $delivery->delivery_district;
                    $order->description = $delivery->description;
                    $order->customer_name = $delivery->customer_name;
                    $order->customer_phone_number_1 = $delivery->customer_phone_number_1;
                    $order->customer_phone_number_2 = $delivery->customer_phone_number_2;
                    $order->customer_address = $delivery->customer_address;
                }
            } catch (\Throwable $th) {
            }
        }

        $order->save();


        $order_total = 0;
        foreach ($items as $key => $item) {
            $product = Product::find($item->product_id);
            if ($product == null) {
                return $this->error("Product #" . $item->product_id . " not found.");
            }
            $oi = new OrderedItem();
            $oi->order = $order->id;
            $oi->product = $item->product_id;
            $oi->qty = $item->product_quantity;
            $oi->amount = $product->price_1;
            $oi->color = $item->color;
            $oi->size = $item->size;
            $order_total += ($product->price_1 * $oi->qty);
            $oi->save();
        }
        $order->amount = $order_total + $delivery_amount;
        $order->order_total = $order->amount;


        $order->save();
        $order = Order::find($order->id);

        // Send response first
        $response = $this->success($order, "Submitted successfully!");

        // After response is prepared, trigger email in background
        register_shutdown_function(function () use ($order) {
            try {
                \App\Models\Order::send_mails($order);
            } catch (\Throwable $th) {
                Log::error('Background email error for order ' . $order->id . ': ' . $th->getMessage());
            }
        });

        return $response;
    }



    public function orders_submit(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }

        $items = [];
        try {
            $items = json_decode($r->items);
        } catch (\Throwable $th) {
            $items = [];
        }
        foreach ($items as $key => $value) {
            $p = Product::find($value->product_id);
            if ($p == null) {
                return $this->error("Product #" . $value->product_id . " not found.");
            }
        }

        if ($u == null) {
            return $this->error('User not found.');
        }

        $delivery = null;
        try {
            $delivery = json_decode($r->delivery);
        } catch (\Throwable $th) {
            $delivery = null;
        }

        if ($delivery == null) {
            return $this->error('Delivery information is missing.');
        }
        if ($delivery->phone_number == null) {
            return $this->error('Phone number is missing.');
        }

        $order = new Order();
        $order->user = $u->id;
        $order->order_state = 0;
        $order->temporary_id = 0;
        $order->amount = 0;
        $order->order_total = 0;
        $order->payment_confirmation = '';
        $order->payment_gateway = 'manual'; // Default payment gateway for existing method
        $order->payment_status = 'PENDING_PAYMENT';
        $order->description = '';
        $order->mail = $u->email;
        $order->date_created = Carbon::now();
        $order->date_updated = Carbon::now();

        // Handle pay on delivery option
        $order->pay_on_delivery = $r->pay_on_delivery === 'true' || $r->pay_on_delivery === true || $r->pay_on_delivery === 1;

        // If pay on delivery is selected, update payment status
        if ($order->pay_on_delivery) {
            $order->payment_status = 'PAY_ON_DELIVERY';
            $order->payment_gateway = 'cash_on_delivery';
        }
        if ($delivery != null) {
            try {
                $order->customer_phone_number_1 = $delivery->phone_number;
                $order->customer_phone_number_2 = $delivery->phone_number_2;
                $order->customer_name = $delivery->first_name . " " . $delivery->last_name;
                $order->customer_address = $delivery->current_address;
                $order->delivery_district = $delivery->current_address;
                $order->order_details = json_encode($delivery);
            } catch (\Throwable $th) {
            }
        }

        $order->save();


        $order_total = 0;
        foreach ($items as $key => $item) {
            $product = Product::find($item->product_id);
            if ($product == null) {
                return $this->error("Product #" . $item->product_id . " not found.");
            }
            $oi = new OrderedItem();
            $oi->order = $order->id;
            $oi->product = $item->product_id;
            $oi->qty = $item->product_quantity;
            $oi->amount = $product->price_1;
            $oi->color = $item->color;
            $oi->size = $item->size;
            $order_total += ($product->price_1 * $oi->qty);
            $oi->save();
        }
        $order->order_total = $order_total;
        $order->amount = $order_total;
        $order->save();

        /* if ($order->stripe_url == null || strlen($order->stripe_url) < 6) {
            $order->create_payment_link();
            $order->save();
        } */
        $order = Order::find($order->id);

        // Send response first
        $response = $this->success($order, "Submitted successfully!");

        // After response is prepared, trigger email in background
        register_shutdown_function(function () use ($order) {
            try {
                \App\Models\Order::send_mails($order);
            } catch (\Throwable $th) {
                Log::error('Background email error for order ' . $order->id . ': ' . $th->getMessage());
            }
        });

        return $response;
    }

    /**
     * Create order with Pesapal payment integration
     * POST /api/orders-with-payment
     */
    public function orders_with_payment(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            $administrator_id = Utils::get_user_id($r);
            $u = Administrator::find($administrator_id);
        }

        if ($u == null) {
            return $this->error('User not found.');
        }

        $items = [];
        try {
            $items = json_decode($r->items);
        } catch (\Throwable $th) {
            $items = [];
        }

        if (empty($items)) {
            return $this->error('Order items are required.');
        }

        foreach ($items as $key => $value) {
            $p = Product::find($value->product_id);
            if ($p == null) {
                return $this->error("Product #" . $value->product_id . " not found.");
            }
        }

        $delivery = null;
        try {
            $delivery = json_decode($r->delivery);
        } catch (\Throwable $th) {
            $delivery = null;
        }

        if ($delivery == null) {
            return $this->error('Delivery information is missing.');
        }
        if ($delivery->phone_number == null) {
            return $this->error('Phone number is missing.');
        }

        // Validate payment gateway selection
        $paymentGateway = $r->payment_gateway ?? 'pesapal';
        if (!in_array($paymentGateway, ['pesapal', 'stripe', 'manual'])) {
            return $this->error('Invalid payment gateway. Supported: pesapal, stripe, manual.');
        }

        // Create the order
        $order = new Order();
        $order->user = $u->id;
        $order->order_state = 0;
        $order->temporary_id = 0;
        $order->amount = 0;
        $order->order_total = 0;
        $order->payment_confirmation = '';
        $order->payment_gateway = $paymentGateway;
        $order->payment_status = 'PENDING_PAYMENT';
        $order->description = '';
        $order->mail = $u->email;
        $order->date_created = Carbon::now();
        $order->date_updated = Carbon::now();

        // Handle pay on delivery option
        $order->pay_on_delivery = $r->pay_on_delivery === 'true' || $r->pay_on_delivery === true || $r->pay_on_delivery === 1;

        // If pay on delivery is selected, override payment settings
        if ($order->pay_on_delivery) {
            $order->payment_status = 'PAY_ON_DELIVERY';
            $order->payment_gateway = 'cash_on_delivery';
        }

        if ($delivery != null) {
            try {
                $order->customer_phone_number_1 = $delivery->phone_number;
                $order->customer_phone_number_2 = $delivery->phone_number_2 ?? '';
                $order->customer_name = $delivery->first_name . " " . $delivery->last_name;
                $order->customer_address = $delivery->current_address;
                $order->delivery_district = $delivery->current_address;
                $order->order_details = json_encode($delivery);
            } catch (\Throwable $th) {
                Log::error('Error processing delivery details: ' . $th->getMessage());
            }
        }

        $order->save();

        // Add order items and calculate total
        $order_total = 0;
        foreach ($items as $key => $item) {
            $product = Product::find($item->product_id);
            if ($product == null) {
                return $this->error("Product #" . $item->product_id . " not found.");
            }
            $oi = new OrderedItem();
            $oi->order = $order->id;
            $oi->product = $item->product_id;
            $oi->qty = $item->product_quantity;
            $oi->amount = $product->price_1;
            $oi->color = $item->color ?? '';
            $oi->size = $item->size ?? '';
            $order_total += ($product->price_1 * $oi->qty);
            $oi->save();
        }

        // Add delivery cost if applicable
        $delivery_amount = 0;
        if (isset($delivery->delivery_district)) {
            $del_loc = DeliveryAddress::find($delivery->delivery_district);
            if ($del_loc != null) {
                $delivery_amount = (int)($del_loc->shipping_cost);
            }
        }

        $order->order_total = $order_total + $delivery_amount;
        $order->amount = $order->order_total;
        $order->save();

        // Initialize payment based on gateway selection
        $paymentData = null;
        try {
            if ($paymentGateway === 'pesapal') {
                $pesapalService = app(PesapalService::class);
                $callbackUrl = $r->callback_url ?? url("/api/pesapal/callback");

                // Get or register IPN URL
                $notificationId = $r->notification_id;
                if (!$notificationId) {
                    $ipnResponse = $pesapalService->registerIpnUrl();
                    $notificationId = $ipnResponse['ipn_id'] ?? null;

                    if (!$notificationId) {
                        throw new \Exception('Failed to register IPN URL with Pesapal');
                    }
                }

                // Submit order to Pesapal
                $pesapalResponse = $pesapalService->submitOrderRequest(
                    $order,
                    $notificationId,
                    $callbackUrl
                );

                $paymentData = [
                    'payment_gateway' => 'pesapal',
                    'order_tracking_id' => $pesapalResponse['order_tracking_id'],
                    'merchant_reference' => $pesapalResponse['merchant_reference'],
                    'redirect_url' => $pesapalResponse['redirect_url'],
                    'status' => $pesapalResponse['status'] ?? '200'
                ];
            } elseif ($paymentGateway === 'stripe') {
                // Future: Implement Stripe integration
                $paymentData = [
                    'payment_gateway' => 'stripe',
                    'message' => 'Stripe integration not yet implemented',
                    'status' => 'pending'
                ];
            } else {
                // Manual payment
                $paymentData = [
                    'payment_gateway' => 'manual',
                    'message' => 'Manual payment selected. Please contact support for payment instructions.',
                    'status' => 'pending'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Payment initialization failed for order ' . $order->id, [
                'error' => $e->getMessage(),
                'payment_gateway' => $paymentGateway
            ]);

            return $this->error('Payment initialization failed: ' . $e->getMessage());
        }

        // Refresh order data
        $order = Order::find($order->id);

        // Prepare response
        $responseData = [
            'order' => $order,
            'payment' => $paymentData
        ];

        // Send response first
        $response = $this->success($responseData, "Order created and payment initialized successfully!");

        // After response is prepared, trigger email in background
        register_shutdown_function(function () use ($order) {
            try {
                \App\Models\Order::send_mails($order);
            } catch (\Throwable $th) {
                Log::error('Background email error for order ' . $order->id . ': ' . $th->getMessage());
            }
        });

        return $response;
    }


    public function product_create(Request $r)
    {

        $user_id = $r->user;
        $u = User::find($user_id);
        if ($u == null) {
            return $this->error('User not found.');
        }

        //local_id is required
        if (
            !isset($r->local_id) ||
            $r->local_id == null ||
            strlen($r->local_id) < 6
        ) {
            return $this->error('Local ID is missing.');
        }


        $isEdit = false;
        if (
            isset($r->is_edit) && $r->is_edit == 'Yes' && $r->id != null
            && $r->id > 0
        ) {
            $pro = Product::find($r->id);
            if ($pro == null) {
                $pro = new Product();
                $isEdit = false;
            } else {
                $isEdit = true;
            }
        } else {
            $pro = new Product();
        }

        if (!$isEdit) {
            $pro->feature_photo = 'no_image.jpg';
            $pro->user = $u->id;
            $pro->supplier = $u->id;
            $pro->in_stock = 1;
            $pro->rates = 1;
        }


        if ($r->p_type == 'Yes') {
            if ($r->keywords ==  null) {
                return $this->error('Prices are missing.');
            }
            $my_prices = null;
            try {
                $my_prices = json_decode($r->keywords);
            } catch (\Throwable $th) {
                $my_prices = null;
            }
            //if not array
            if ($my_prices == null || !is_array($my_prices)) {
                return $this->error('Prices not found.');
            }
            //$my_prices if empty
            if (count($my_prices) < 1) {
                return $this->error('Prices not found.');
            }
            $prices = [];
            $min_price = 0;
            $max_price = 0;


            foreach ($my_prices as $key => $value) {
                if ($value->price == null || strlen($value->price) < 1) {
                    return $this->error('Price is missing.');
                }
                if ($value->min_qty == null || strlen($value->min_qty) < 1) {
                    return $this->error('Minimum quantity is missing.');
                }
                if ($value->max_qty == null || strlen($value->max_qty) < 1) {
                    return $this->error('Maximum quantity is missing.');
                }
                $my_min = (int)($value->min_qty);
                $my_max = (int)($value->max_qty);
                $price = (int)($value->price);
                if ($min_price < $my_min) {
                    $min_price = $my_min;
                }
                if ($max_price < $my_max) {
                    $max_price = $my_max;
                }
                $prices[] = $value;
            }

            $pro->price_1 = $min_price;
            $pro->price_2 = $max_price;
            $pro->keywords = $r->keywords;
        } else if ($r->p_type == 'No') {
            if ($r->price_1 == null || strlen($r->price_1) < 1) {
                return $this->error('Price is missing.');
            }
            if ($r->price_2 == null || strlen($r->price_2) < 1) {
                return $this->error('Price is missing.');
            }
            $pro->price_1 = $r->price_1;
            $pro->price_2 = $r->price_2;
        } else {
            return $this->error('Product type is missing.');
        }


        $pro->name = $r->name;
        $pro->description = $r->description;
        $pro->local_id = $r->local_id;
        $pro->summary = $r->data;
        $pro->metric = 1;
        $pro->status = 0;
        $pro->currency = 1;
        $pro->url = $u->url;


        $pro->has_sizes = $r->has_sizes;
        $pro->has_colors = $r->has_colors;
        $pro->colors = $r->colors;
        $pro->sizes = $r->sizes;
        $pro->p_type = $r->p_type;

        $cat = ProductCategory::find($r->category);
        if ($cat == null) {
            return $this->error('Category not found.');
        }
        $pro->category = $cat->id;

        $pro->date_added = Carbon::now();
        $pro->date_updated = Carbon::now();
        $imgs = Image::where([
            'parent_local_id' => $pro->local_id
        ])->get();
        if ($imgs->count() > 0) {
            $pro->feature_photo = $imgs[0]->src;
        }
        if ($pro->save()) {
            foreach ($imgs as $key => $img) {
                $img->product_id = $pro->id;
                $img->save();
            }
            if ($isEdit) {
                return $this->success(null, $message = "Updated successfully!", 200);
            }
            return $this->success(null, $message = "Submitted successfully!", 200);
        } else {
            return $this->error('Failed to upload product.');
        }
    }



    public function locations(Request $r)
    {
        $items = Location::all();
        return $this->success(
            $items,
            $message = "Sussesfully",
            1
        );
    }

    public function crops(Request $r)
    {
        $items = [];

        foreach (Crop::all() as $key => $crop) {


            $protocols = CropProtocol::where([
                'crop_id' => $crop->id
            ])->get();
            $crop->protocols = json_encode($protocols);

            $items[] = $crop;
        }

        return $this->success(
            $items,
            $message = "Sussesfully",
            200
        );
    }

    public function garden_activities(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }

        $gardens = [];
        if ($u->isRole('agent')) {
            $gardens = GardenActivity::where([])
                ->limit(1000)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $gardens = GardenActivity::where(['user_id' => $u->id])
                ->limit(1000)
                ->orderBy('id', 'desc')
                ->get();
        }

        return $this->success(
            $gardens,
            $message = "Sussesfully",
            200
        );
    }

    public function gardens(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }

        $gardens = [];
        if ($u->isRole('agent')) {
            $gardens = Garden::where([])
                ->limit(1000)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $gardens = Garden::where(['user_id' => $u->id])
                ->limit(1000)
                ->orderBy('id', 'desc')
                ->get();
        }

        return $this->success(
            $gardens,
            $message = "Sussesfully",
            200
        );
    }



    public function people(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }

        return $this->success(
            Person::where(['administrator_id' => $u->id])
                ->limit(100)
                ->orderBy('id', 'desc')
                ->get(),
            $message = "Sussesfully",
            200
        );
    }
    public function jobs(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }

        return $this->success(
            Job::where([])
                ->orderBy('id', 'desc')
                ->limit(100)
                ->get(),
            $message = "Sussesfully",
        );
    }

    public function garden_create(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }
        if (
            $r->name == null ||
            $r->planting_date == null ||
            $r->crop_id == null
        ) {
            return $this->error('Some Information is still missing. Fill the missing information and try again.');
        }


        $image = "";
        if (!empty($_FILES)) {
            try {
                $image = Utils::upload_images_2($_FILES, true);
            } catch (Throwable $t) {
                $image = "no_image.jpg";
            }
        }

        $obj = new Garden();
        $obj->name = $r->name;
        $obj->user_id = $u->id;
        $obj->status = $r->status;
        $obj->production_scale = $r->production_scale;
        $obj->planting_date = Carbon::parse($r->planting_date);
        $obj->land_occupied = $r->planting_date;
        $obj->crop_id = $r->crop_id;
        $obj->details = $r->details;
        $obj->photo = $image;
        $obj->save();


        return $this->success(null, $message = "Sussesfully created!", 200);
    }

    public function images_delete(Request $r)
    {
        $pro = Image::find($r->id);
        if ($pro == null) {
            return $this->error('Image not found.');
        }
        try {
            $pro->delete();
            return $this->success(null, $message = "Sussesfully deleted!", 200);
        } catch (\Throwable $th) {
            return $this->error('Failed to delete image because ' . $th->getMessage());
        }
    }
    public function products_delete(Request $r)
    {
        $pro = Product::find($r->id);
        if ($pro == null) {
            return $this->error('Product not found.');
        }
        try {
            $pro->delete();
            return $this->success(null, $message = "Sussesfully deleted!", 200);
        } catch (\Throwable $th) {
            return $this->error('Failed to delete product.');
        }
    }
    public function person_create(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }
        if (
            $r->name == null ||
            $r->sex == null ||
            $r->subcounty_id == null
        ) {
            return $this->error('Some Information is still missing. Fill the missing information and try again.');
        }

        $image = "";
        if (!empty($_FILES)) {
            try {
                $image = Utils::upload_images_2($_FILES, true);
            } catch (Throwable $t) {
                $image = "no_image.jpg";
            }
        }

        $obj = new Person();
        $obj->id = $r->id;
        $obj->created_at = $r->created_at;
        $obj->association_id = $r->association_id;
        $obj->administrator_id = $u->id;
        $obj->group_id = $r->group_id;
        $obj->name = $r->name;
        $obj->address = $r->address;
        $obj->parish = $r->parish;
        $obj->village = $r->village;
        $obj->phone_number = $r->phone_number;
        $obj->email = $r->email;
        $obj->district_id = $r->district_id;
        $obj->subcounty_id = $r->subcounty_id;
        $obj->disability_id = $r->disability_id;
        $obj->phone_number_2 = $r->phone_number_2;
        $obj->dob = $r->dob;
        $obj->sex = $r->sex;
        $obj->education_level = $r->education_level;
        $obj->employment_status = $r->employment_status;
        $obj->has_caregiver = $r->has_caregiver;
        $obj->caregiver_name = $r->caregiver_name;
        $obj->caregiver_sex = $r->caregiver_sex;
        $obj->caregiver_phone_number = $r->caregiver_phone_number;
        $obj->caregiver_age = $r->caregiver_age;
        $obj->caregiver_relationship = $r->caregiver_relationship;
        $obj->photo = $image;
        $obj->save();


        return $this->success(null, $message = "Sussesfully registered!", 200);
    }

    public function groups()
    {
        return $this->success(Group::get_groups(), 'Success');
    }


    public function associations()
    {
        return $this->success(Association::where([])->orderby('id', 'desc')->get(), 'Success');
    }

    public function institutions()
    {
        return $this->success(Institution::where([])->orderby('id', 'desc')->get(), 'Success');
    }
    public function service_providers()
    {
        return $this->success(ServiceProvider::where([])->orderby('id', 'desc')->get(), 'Success');
    }
    public function counselling_centres()
    {
        return $this->success(CounsellingCentre::where([])->orderby('id', 'desc')->get(), 'Success');
    }


    public function products_1(Request $request)
    {
        //latest 1000 products without pagination
        $products = Product::where([])->limit(1000)->get();
        return $this->success($products, 'Success');
    }
    public function products(Request $request)
    {
        // Start building the query on active products
        $query = Product::with(['specifications']);

        $searchTerm = null;
        $productIds = [];

        // Filter by search keyword (in the name, description, or tags)
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');

            // Use enhanced search with tag prioritization
            $query->enhancedSearch($searchTerm);
        }
        if ($request->filled('name')) {
            $name = $request->input('name');
            $query->where(function ($q) use ($name) {
                $q->where('name', 'LIKE', "%{$name}%")
                    ->orWhere('description', 'LIKE', "%{$name}%")
                    ->orWhere('tags', 'LIKE', "%{$name}%");
            });
        }

        // Filter by tags
        if ($request->filled('tags')) {
            $tags = $request->input('tags');
            if (is_string($tags)) {
                $tags = explode(',', $tags);
            }
            if (is_array($tags)) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhere('tags', 'LIKE', '%' . trim($tag) . '%');
                    }
                });
            }
        }

        // Filter by specifications
        if ($request->filled('attributes')) {
            $attributes = $request->input('attributes');
            if (is_string($attributes)) {
                $attributes = json_decode($attributes, true);
            }
            if (is_array($attributes)) {
                foreach ($attributes as $attrName => $attrValue) {
                    $query->whereHas('specifications', function ($q) use ($attrName, $attrValue) {
                        $q->where('name', $attrName)->where('value', 'LIKE', "%{$attrValue}%");
                    });
                }
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price_1', '>=', $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price_2', '<=', $request->input('max_price'));
        }

        // Filter by availability
        if ($request->filled('availability')) {
            $query->where('in_stock', $request->input('availability'));
        }

        // Filter by home sections (Flash Sales, Super Buyer, Top Products)
        if ($request->filled('home_section_1')) {
            $query->where('home_section_1', $request->input('home_section_1'));
        }
        if ($request->filled('home_section_2')) {
            $query->where('home_section_2', $request->input('home_section_2'));
        }
        if ($request->filled('home_section_3')) {
            $query->where('home_section_3', $request->input('home_section_3'));
        }

        // Sorting logic based on 'sort' parameter
        if ($request->filled('sort')) {
            $sort = $request->input('sort');
            if ($sort === "Newest") {
                $query->orderBy('created_at', 'DESC');
            } elseif ($sort === "Oldest") {
                $query->orderBy('created_at', 'ASC');
            } elseif ($sort === "High Price") {
                $query->orderBy('price_2', 'DESC');
            } elseif ($sort === "Low Price") {
                $query->orderBy('price_1', 'ASC');
            } else {
                // Fallback ordering
                $query->orderBy('id', 'DESC');
            }
        } else {
            // Default ordering
            $query->orderBy('id', 'DESC');
        }

        // Paginate results (default 16 per page)
        $perPage = $request->input('per_page', 28);
        $products = $query->paginate($perPage);

        // Add computed attributes for each product
        foreach ($products as $product) {
            $product->tags_array = $product->tags_array;
            $product->attributes_array = $product->specifications->map(function ($attr) {
                return [
                    'name' => $attr->name,
                    'value' => $attr->value,
                ];
            })->toArray();
        }

        // Record search history if there was a search term
        if ($searchTerm && !empty(trim($searchTerm))) {
            $productIds = $products->items();
            $productIds = collect($productIds)->pluck('id')->toArray();
            $resultsCount = $products->total();

            // Get user ID if authenticated
            $userId = null;
            $user = auth('api')->user();
            if (!$user && $request->filled('user')) {
                $userId = $request->input('user');
            } elseif ($user) {
                $userId = $user->id;
            }

            // Get session ID for guest users
            $sessionId = $request->input('session_id', $request->header('X-Session-ID', session()->getId()));

            // Record the search
            SearchHistory::recordSearch($searchTerm, $productIds, $resultsCount, $userId, $sessionId);
        }

        return $this->success($products, 'Success');
    }

    /**
     * Live search endpoint for real-time product search with suggestions
     */
    public function live_search(Request $request)
    {
        $searchTerm = $request->input('q', '');
        $limit = $request->input('limit', 10);

        if (empty(trim($searchTerm)) || strlen(trim($searchTerm)) < 2) {
            return $this->success([
                'products' => [],
                'suggestions' => [],
                'total' => 0
            ], 'Search term too short');
        }

        // Search products with enhanced tag search
        $products = Product::where(function ($query) use ($searchTerm) {
            // Primary search with tag prioritization
            $query->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('tags', 'LIKE', "%{$searchTerm}%")
                ->orWhere('description', 'LIKE', "%{$searchTerm}%");

            // Additional search for individual words in tags
            $searchWords = explode(' ', trim($searchTerm));
            if (count($searchWords) > 1) {
                foreach ($searchWords as $word) {
                    if (strlen(trim($word)) > 2) {
                        $query->orWhere('tags', 'LIKE', "%{$word}%");
                    }
                }
            }
        })
            ->limit($limit)
            ->get(['id', 'name', 'price_1', 'price_2', 'feature_photo', 'category', 'tags']);

        // Get search suggestions based on product names and tags
        $nameSuggestions = Product::where('name', 'LIKE', "%{$searchTerm}%")
            ->distinct()
            ->limit(3)
            ->pluck('name')
            ->map(function ($name) use ($searchTerm) {
                // Extract relevant keywords from product names
                $words = explode(' ', strtolower($name));
                return array_filter($words, function ($word) use ($searchTerm) {
                    return strlen($word) > 2 && stripos($word, strtolower($searchTerm)) !== false;
                });
            })
            ->flatten()
            ->unique()
            ->take(3);

        // Get tag-based suggestions
        $tagSuggestions = Product::where('tags', 'LIKE', "%{$searchTerm}%")
            ->whereNotNull('tags')
            ->where('tags', '!=', '')
            ->limit(5)
            ->get(['tags'])
            ->flatMap(function ($product) use ($searchTerm) {
                $tags = array_map('trim', explode(',', $product->tags));
                return array_filter($tags, function ($tag) use ($searchTerm) {
                    return stripos($tag, $searchTerm) !== false && strlen($tag) > 2;
                });
            })
            ->unique()
            ->take(4);

        // Combine suggestions
        $suggestions = $nameSuggestions->concat($tagSuggestions)
            ->unique()
            ->take(5)
            ->values();

        // Record the search for search history
        $userId = null;
        $user = auth('api')->user();

        if (!$user && $request->filled('user')) {
            $userId = $request->input('user');
        } elseif ($user) {
            $userId = $user->id;
        }

        $sessionId = $request->input('session_id', $request->header('X-Session-ID', session()->getId()));
        $productIds = $products->pluck('id')->toArray();
        $resultsCount = $products->count();

        // Record the search
        SearchHistory::recordSearch($searchTerm, $productIds, $resultsCount, $userId, $sessionId);

        return $this->success([
            'products' => $products,
            'suggestions' => $suggestions,
            'total' => $products->count(),
            'search_term' => $searchTerm
        ], 'Live search results');
    }

    /**
     * Get user's recent search history
     */
    public function search_history(Request $request)
    {
        $userId = null;
        $user = auth('api')->user();

        if (!$user && $request->filled('user')) {
            $userId = $request->input('user');
        } elseif ($user) {
            $userId = $user->id;
        }

        // Get session ID for guest users
        $sessionId = $request->input('session_id', $request->header('X-Session-ID', session()->getId()));

        $limit = $request->input('limit', 10);

        $recentSearches = SearchHistory::getRecentSearches($userId, $sessionId, $limit);

        return $this->success([
            'recent_searches' => $recentSearches,
            'total' => count($recentSearches)
        ], 'Search history retrieved');
    }

    /**
     * Clear user's search history
     */
    public function clear_search_history(Request $request)
    {
        $userId = null;
        $user = auth('api')->user();

        if (!$user && $request->filled('user')) {
            $userId = $request->input('user');
        } elseif ($user) {
            $userId = $user->id;
        }

        // Get session ID for guest users
        $sessionId = $request->input('session_id', $request->header('X-Session-ID', session()->getId()));

        if ($userId) {
            SearchHistory::where('user_id', $userId)->delete();
        } elseif ($sessionId) {
            SearchHistory::where('session_id', $sessionId)->delete();
        }

        return $this->success([], 'Search history cleared');
    }

    public function index(Request $r, $model)
    {
        // Convert hyphenated model name to proper class name
        // e.g., "insurance-subscriptions" -> "InsuranceSubscription"
        $modelClassName = str_replace(' ', '', ucwords(str_replace('-', ' ', rtrim($model, 's'))));
        $className = "App\Models\\" . $modelClassName;

        // Check if class exists
        if (!class_exists($className)) {
            return Utils::response([
                'status' => 0,
                'message' => "Model not found: " . $modelClassName,
            ]);
        }

        $obj = new $className;

        if (isset($_POST['_method'])) {
            unset($_POST['_method']);
        }
        if (isset($_GET['_method'])) {
            unset($_GET['_method']);
        }

        $conditions = [];
        foreach ($_GET as $k => $v) {
            if (substr($k, 0, 2) == 'q_') {
                $conditions[substr($k, 2, strlen($k))] = trim($v);
            }
        }
        $is_private = true;
        if (isset($_GET['is_not_private'])) {
            $is_not_private = ((int)($_GET['is_not_private']));
            if ($is_not_private == 1) {
                $is_private = false;
            }
        }
        if ($is_private) {

            $u = Utils::get_user($r);

            if ($u == null) {
                return $this->error('User not found.');
            }

            $administrator_id = $u->id;
            $conditions['administrator_id'] = $administrator_id;
        }

        $items = [];
        $msg = "";

        try {
            $items = $className::where($conditions)->get();
            $msg = "Success";
            $success = true;
        } catch (Exception $e) {
            $success = false;
            $msg = $e->getMessage();
        }

        if ($success) {
            return $this->success($items, 'Success');
        } else {
            return $this->error($msg);
        }
    }





    public function delete(Request $r, $model)
    {
        $administrator_id = Utils::get_user_id($r);
        $u = Administrator::find($administrator_id);

        if ($u == null) {
            return Utils::response([
                'code' => 0,
                'message' => "User not found.",
            ]);
        }


        $className = "App\Models\\" . $model;
        $id = ((int)($r->online_id));
        $obj = $className::find($id);


        if ($obj == null) {
            return Utils::response([
                'code' => 0,
                'message' => "Item already deleted.",
            ]);
        }


        try {
            $obj->delete();
            $msg = "Deleted successfully.";
            $success = true;
        } catch (Exception $e) {
            $success = false;
            $msg = $e->getMessage();
        }


        if ($success) {
            return Utils::response([
                'code' => 1,
                'data' => $obj,
                'message' => $msg
            ]);
        } else {
            return Utils::response([
                'code' => 0,
                'data' => null,
                'message' => $msg
            ]);
        }
    }


    public function update(Request $r, $model, $id = null)
    {
        $administrator_id = Utils::get_user_id($r);
        $u = Administrator::find($administrator_id);


        if ($u == null) {
            return Utils::response([
                'code' => 0,
                'message' => "User not found.",
            ]);
        }

        // Convert hyphenated model name to proper class name
        // e.g., "insurance-subscriptions" -> "InsuranceSubscription"
        $modelClassName = str_replace(' ', '', ucwords(str_replace('-', ' ', rtrim($model, 's'))));
        $className = "App\Models\\" . $modelClassName;

        // Check if class exists
        if (!class_exists($className)) {
            return Utils::response([
                'code' => 0,
                'message' => "Model not found: " . $modelClassName,
            ]);
        }

        // Get ID from URL parameter or from request body
        $isUpdate = false;
        if ($id !== null) {
            $id = (int)$id;
            $isUpdate = true;
        } else if (isset($r->online_id) && $r->online_id > 0) {
            $id = ((int)($r->online_id));
            $isUpdate = true;
        } else if (isset($r->id) && $r->id > 0) {
            $id = ((int)($r->id));
            $isUpdate = true;
        }

        if ($isUpdate) {
            $obj = $className::find($id);
            if ($obj == null) {
                return Utils::response([
                    'code' => 0,
                    'message' => "Item not found.",
                ]);
            }
        } else {
            // Create new object
            $obj = new $className();
        }

        unset($_POST['_method']);
        if (isset($_POST['online_id'])) {
            unset($_POST['online_id']);
        }

        // Remove fields that are added by client but don't exist in database
        $fieldsToExclude = ['user', 'User-Id', 'user_id', 'id'];
        foreach ($fieldsToExclude as $field) {
            if (isset($_POST[$field])) {
                unset($_POST[$field]);
            }
        }

        // Handle datetime fields - convert empty strings to NULL
        $dateTimeFields = ['dob', 'created_at', 'updated_at', 'reg_date', 'last_seen'];
        foreach ($dateTimeFields as $field) {
            if (isset($_POST[$field]) && trim($_POST[$field]) === '') {
                $_POST[$field] = null;
            }
        }

        foreach ($_POST as $key => $value) {
            $obj->$key = $value;
        }


        $success = false;
        $msg = "";
        try {
            $obj->save();
            $msg = $isUpdate ? "Updated successfully." : "Created successfully.";
            $success = true;
        } catch (Exception $e) {
            $success = false;
            $msg = $e->getMessage();
        }


        if ($success) {
            return Utils::response([
                'code' => 1,
                'data' => $obj,
                'message' => $msg
            ]);
        } else {
            return Utils::response([
                'code' => 0,
                'data' => null,
                'message' => $msg
            ]);
        }
    }

    //delivery_addresses
    public function delivery_addresses(Request $r)
    {
        return $this->success(
            DeliveryAddress::where([])
                ->limit(100)
                ->orderBy('id', 'desc')
                ->get(),
            $message = "Sussesfully",
            200
        );
    }

    // ===== WISHLIST METHODS =====

    /**
     * Get user's wishlist
     */
    public function wishlist_get(Request $request)
    {
        try {
            $user_id = $request->user;

            if (!$user_id) {
                return $this->error('User authentication required.', 401);
            }

            $wishlists = \App\Models\Wishlist::where('user_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success($wishlists, 'Wishlist retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve wishlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add product to wishlist
     */
    public function wishlist_add(Request $request)
    {
        try {
            $user_id = $request->user;
            $product_id = $request->product_id;

            if (!$user_id) {
                return $this->error('User authentication required.', 401);
            }

            if (!$product_id) {
                return $this->error('Product ID is required.', 400);
            }

            // Check if product exists
            $product = Product::find($product_id);
            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            // Check if already in wishlist
            $existing = \App\Models\Wishlist::where([
                'user_id' => $user_id,
                'product_id' => $product_id
            ])->first();

            if ($existing) {
                return $this->error('Product already in wishlist.', 409);
            }

            // Add to wishlist
            $wishlist = \App\Models\Wishlist::create([
                'user_id' => $user_id,
                'product_id' => $product_id,
                'product_name' => $product->name,
                'product_price' => $product->price_1,
                'product_sale_price' => $product->price_2,
                'product_photo' => $product->feature_photo,
            ]);

            return $this->success($wishlist, 'Product added to wishlist successfully.', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to add to wishlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove product from wishlist
     */
    public function wishlist_remove(Request $request)
    {
        try {
            $user_id = $request->user;
            $product_id = $request->product_id;

            if (!$user_id) {
                return $this->error('User authentication required.', 401);
            }

            if (!$product_id) {
                return $this->error('Product ID is required.', 400);
            }

            $wishlist = \App\Models\Wishlist::where([
                'user_id' => $user_id,
                'product_id' => $product_id
            ])->first();

            if (!$wishlist) {
                return $this->error('Product not found in wishlist.', 404);
            }

            $wishlist->delete();

            return $this->success(null, 'Product removed from wishlist successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to remove from wishlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check if product is in user's wishlist
     */
    public function wishlist_check(Request $request)
    {
        try {
            $user_id = $request->user;
            $product_id = $request->product_id;

            if (!$user_id) {
                return $this->error('User authentication required.', 401);
            }

            if (!$product_id) {
                return $this->error('Product ID is required.', 400);
            }

            $exists = \App\Models\Wishlist::where([
                'user_id' => $user_id,
                'product_id' => $product_id
            ])->exists();

            return $this->success(['in_wishlist' => $exists], 'Wishlist status checked.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to check wishlist status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get application manifest with essential data and counts
     * This endpoint provides centralized data to avoid multiple API calls
     * Returns different data based on user authentication status
     */
    public function manifest(Request $request)
    {
        try {
            $user_id = $request->user;
            $user = null;

            // Check if user is authenticated
            if ($user_id) {
                $user = Administrator::find($user_id);
            }

            // Base manifest data for all users (authenticated and guests)
            $manifest = [
                'app_info' => [
                    'name' => 'BlitXpress',
                    'version' => '1.0.0',
                    'api_version' => '1.0',
                    'maintenance_mode' => false,
                ],
                'categories' => $this->getProductCategories(),
                'delivery_locations' => $this->getDeliveryLocations(),
                'settings' => [
                    'currency' => 'UGX',
                    'currency_symbol' => 'UGX',
                    'tax_rate' => 0, // No tax for delivery-only
                    'delivery_fee_varies' => true,
                    'min_order_amount' => 0,
                ],
                'features' => [
                    'wishlist_enabled' => true,
                    'reviews_enabled' => true,
                    'chat_enabled' => true,
                    'promotions_enabled' => true,
                ],
                'counts' => [
                    'total_products' => Product::count(),
                    'total_categories' => ProductCategory::count(),
                    'total_orders' => Order::count(),
                    'total_users' => Administrator::where('user_type', 'customer')->count(),
                    'total_vendors' => Administrator::where('user_type', 'Vendor')->count(),
                    'active_vendors' => Administrator::where('user_type', 'Vendor')->count(),
                    'total_delivery_locations' => DeliveryAddress::count(),
                    'active_promotions' => 0, // You can add this if you have promotions table
                    'wishlist_count' => 0,
                    'cart_count' => 0,
                    'notifications_count' => 0,
                    'unread_messages_count' => 0,
                    'pending_orders' => Order::where('order_state', 0)->count(),
                    'completed_orders' => Order::where('order_state', 2)->count(),
                    'cancelled_orders' => Order::where('order_state', 3)->count(),
                    'processing_orders' => Order::where('order_state', 1)->count(),
                    'recent_orders_this_week' => Order::where('created_at', '>=', now()->subWeek())->count(),
                    'orders_today' => Order::whereDate('created_at', today())->count(),
                    'orders_this_month' => Order::whereMonth('created_at', now()->month)->count(),
                    'new_users_this_week' => Administrator::where('created_at', '>=', now()->subWeek())->count(),
                    'new_users_today' => Administrator::whereDate('created_at', today())->count(),
                    'products_out_of_stock' => Product::where('in_stock', '<=', 0)->count(),
                    'low_stock_products' => Product::where('in_stock', '>', 0)->where('in_stock', '<=', 10)->count(),
                    'featured_products_count' => Product::where('rates', '>', 4)->count(),
                    'total_revenue' => Order::where('order_state', 2)->sum('order_total'),
                    'revenue_this_month' => Order::where('order_state', 2)
                        ->whereMonth('created_at', now()->month)
                        ->sum('order_total'),
                    'average_order_value' => Order::where('order_state', 2)->avg('order_total') ?: 0,
                ],
                'user' => null,
                'is_authenticated' => false,
            ];

            // If user is authenticated, add user-specific data
            if ($user) {
                $manifest['is_authenticated'] = true;
                $manifest['user'] = [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                    'avatar' => $user->avatar,
                    'user_type' => $user->user_type,
                    'status' => $user->status,
                    'complete_profile' => $user->complete_profile,
                ];

                // User-specific counts
                $manifest['counts']['total_orders'] = Order::where('user', $user->id)->count();
                $manifest['counts']['wishlist_count'] = \App\Models\Wishlist::where('user_id', $user->id)->count();

                // Include full wishlist data to avoid separate API call
                $manifest['wishlist'] = \App\Models\Wishlist::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Unread messages count
                $manifest['counts']['unread_messages_count'] = ChatMessage::where('receiver_id', $user->id)
                    ->where('status', '!=', 'read')->count();

                // Recent orders (last 5)
                $manifest['recent_orders'] = Order::where('user', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'order_total', 'order_state', 'created_at']);
            } else {
                // For guest users, provide empty arrays
                $manifest['wishlist'] = [];
                $manifest['recent_orders'] = [];
            }

            // Get recent search suggestions for the user (authenticated or guest)
            $userId = $user ? $user->id : null;
            $sessionId = $request->input('session_id', $request->header('X-Session-ID', session()->getId()));
            $manifest['recent_search_suggestions'] = SearchHistory::getRecentSearches($userId, $sessionId, 10);

            // Load products for homepage sections (50 each) and merge them
            $homeSectionProducts = $this->getHomeSectionProducts();
            $manifest['products'] = $homeSectionProducts;

            // Keep backward compatibility - Popular/featured products for quick access
            $manifest['featured_products'] = Product::where('rates', '>', 4)
                ->orderBy('rates', 'desc')
                ->limit(8)
                ->get(['id', 'name', 'price_1', 'price_2', 'feature_photo', 'category', 'home_section_1', 'home_section_2', 'home_section_3']);

            // Recent products
            $manifest['recent_products'] = Product::orderBy('created_at', 'desc')
                ->limit(8)
                ->get(['id', 'name', 'price_1', 'price_2', 'feature_photo', 'category', 'home_section_1', 'home_section_2', 'home_section_3']);

            return $this->success($manifest, 'Manifest loaded successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to load manifest: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper method to get product categories for manifest
     */
    private function getProductCategories()
    {
        return ProductCategory::with('specifications')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category' => $category->category,
                    'name' => $category->category, // Use name as name
                    'category_text' => $category->display_count, // Use display count instead of category name
                    'product_count' => $category->product_count, // Actual product count
                    'display_count' => $category->display_count, // Display count with +10
                    'parent_id' => $category->parent_id,
                    'image' => $category->image,
                    'banner_image' => $category->banner_image,
                    'show_in_banner' => $category->show_in_banner ?? 'No',
                    'show_in_categories' => $category->show_in_categories ?? 'Yes',
                    'is_parent' => $category->is_parent ?? 'No',
                    'icon' => $category->icon,
                    'specifications' => $category->specifications ? $category->specifications->map(function ($specification) {
                        return [
                            'id' => $specification->id,
                            'name' => $specification->name,
                            'is_required' => $specification->is_required,
                        ];
                    }) : [],
                ];
            });
    }

    /**
     * Helper method to get delivery locations for manifest
     */
    private function getDeliveryLocations()
    {
        return DeliveryAddress::orderBy('address', 'asc')
            ->get(['id', 'address', 'shipping_cost',])
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->address,
                    'shipping_cost' => $location->shipping_cost,
                ];
            });
    }

    /**
     * Helper method to get products for homepage sections
     * Loads 50 products each for home_section_1, home_section_2, home_section_3
     * Then merges them and returns unique products with home section attributes
     */
    private function getHomeSectionProducts()
    {
        // Get products for each home section (50 each)
        $homeSectionFields = [
            'id',
            'name',
            'description',
            'summary',
            'price_1',
            'price_2',
            'feature_photo',
            'category',
            'sub_category',
            'rates',
            'in_stock',
            'keywords',
            'tags',
            'has_colors',
            'colors',
            'has_sizes',
            'sizes',
            'created_at',
            'updated_at',
            'home_section_1',
            'home_section_2',
            'home_section_3'
        ];

        // Load products for Flash Sales (home_section_1 = 'Yes')
        $flashSalesProducts = Product::where('home_section_1', 'Yes')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get($homeSectionFields);

        // Load products for Super Buyer (home_section_2 = 'Yes') 
        $superBuyerProducts = Product::where('home_section_2', 'Yes')
            ->orderBy('rates', 'desc')
            ->limit(50)
            ->get($homeSectionFields);

        // Load products for Top Products (home_section_3 = 'Yes')
        $topProducts = Product::where('home_section_3', 'Yes')
            ->orderBy('rates', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get($homeSectionFields);

        // Merge all collections and remove duplicates by product ID
        $allProducts = $flashSalesProducts
            ->concat($superBuyerProducts)
            ->concat($topProducts)
            ->unique('id')
            ->values(); // Reset array keys

        // Transform products to include all necessary attributes including home sections
        return $allProducts->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'summary' => $product->summary,
                'price_1' => (float) $product->price_1, // Selling price
                'price_2' => (float) $product->price_2, // Original price  
                'feature_photo' => $product->feature_photo,
                'category' => $product->category,
                'sub_category' => $product->sub_category,
                'rates' => (float) $product->rates,
                'in_stock' => (int) $product->in_stock,
                'keywords' => $product->keywords,
                'tags' => $product->tags,
                'has_colors' => $product->has_colors,
                'colors' => $product->colors,
                'has_sizes' => $product->has_sizes,
                'sizes' => $product->sizes,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                // Home section attributes - IMPORTANT for frontend filtering
                'home_section_1' => $product->home_section_1 ?? 'No',
                'home_section_2' => $product->home_section_2 ?? 'No',
                'home_section_3' => $product->home_section_3 ?? 'No',
            ];
        });
    }

    /**
     * Get all product categories
     */
    public function categories(Request $request)
    {
        try {
            $categories = ProductCategory::all()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'category' => $category->category,
                    'show_in_banner' => $category->show_in_banner ?? 'No',
                    'details' => $category->details ?? '',
                    'parent_id' => $category->parent_id ?? null,
                    'image' => $category->image ?? '',
                    'image_origin' => $category->image_origin ?? '',
                    'banner_image' => $category->banner_image ?? '',
                    'show_in_categories' => $category->show_in_categories ?? 'Yes',
                    'attributes' => $category->attributes ?? '',
                    'category_text' => $category->category_text ?? $category->category,
                ];
            });
            /* 

            */

            return $this->success($categories, 'Categories retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve categories: ' . $e->getMessage());
        }
    }

    /**
     * Get popular tags from products
     */
    public function popular_tags(Request $request)
    {
        $limit = $request->input('limit', 20);

        // Get all products with tags
        $products = Product::whereNotNull('tags')
            ->where('tags', '!=', '')
            ->get(['tags']);

        // Extract and count all tags
        $tagCounts = [];
        foreach ($products as $product) {
            $tags = array_map('trim', explode(',', $product->tags));
            foreach ($tags as $tag) {
                if (!empty($tag)) {
                    $tag = strtolower($tag);
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }

        // Sort by popularity and return top tags
        arsort($tagCounts);
        $popularTags = array_slice(array_keys($tagCounts), 0, $limit);

        return $this->success([
            'tags' => $popularTags,
            'total_tags' => count($tagCounts),
            'total_products_with_tags' => $products->count()
        ], 'Popular tags retrieved successfully');
    }

    /**
     * Search products by specific tags only
     */
    public function search_by_tags(Request $request)
    {
        $tags = $request->input('tags', '');
        $perPage = $request->input('per_page', 16);

        if (empty($tags)) {
            return $this->error('Tags parameter is required');
        }

        // Convert tags to array
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        // Search products with any of the specified tags
        $query = Product::with(['specifications']);

        $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                if (!empty(trim($tag))) {
                    $q->orWhere('tags', 'LIKE', '%' . trim($tag) . '%');
                }
            }
        });

        // Order by relevance (products with more matching tags first)
        $query->orderByRaw("
            CASE 
                WHEN tags LIKE '%" . implode("%' AND tags LIKE '%", $tags) . "%' THEN 1
                ELSE 2
            END
        ");

        $products = $query->paginate($perPage);

        // Add computed attributes for each product
        foreach ($products as $product) {
            $product->tags_array = $product->tags_array;
            $product->attributes_array = $product->specifications->map(function ($attr) {
                return [
                    'name' => $attr->name,
                    'value' => $attr->value,
                ];
            })->toArray();
        }

        return $this->success($products, 'Search by tags completed successfully');
    }

    /**
     * Get tag suggestions based on partial input
     */
    public function tag_suggestions(Request $request)
    {
        $partial = $request->input('q', '');
        $limit = $request->input('limit', 10);

        if (strlen($partial) < 2) {
            return $this->success([], 'Query too short');
        }

        // Get products with tags containing the partial string
        $products = Product::whereNotNull('tags')
            ->where('tags', '!=', '')
            ->where('tags', 'LIKE', "%{$partial}%")
            ->get(['tags']);

        // Extract matching tags
        $suggestions = collect();
        foreach ($products as $product) {
            $tags = array_map('trim', explode(',', $product->tags));
            foreach ($tags as $tag) {
                if (stripos($tag, $partial) !== false && strlen($tag) > 1) {
                    $suggestions->push(strtolower($tag));
                }
            }
        }

        // Remove duplicates and limit results
        $suggestions = $suggestions->unique()->take($limit)->values();

        return $this->success([
            'suggestions' => $suggestions,
            'query' => $partial
        ], 'Tag suggestions retrieved');
    }

    /**
     * Get insurance users (system users with user_type = 'Customer' OR NULL/empty user_type)
     * GET /api/insurance-users
     * 
     * Note: During the InsuranceUser model elimination, users were migrated to the User model.
     * Most insurance users have NULL or empty user_type, so we include those as well.
     * We exclude only users with explicit user_type of 'Admin' or 'Vendor'.
     */
    public function insurance_users(Request $request)
    {
        try {
            // Query users - include those with user_type = 'Customer' or NULL/empty user_type
            // Exclude only Admin and Vendor users
            $query = User::whereNotIn('user_type', ['Admin', 'Vendor'])
                ->orWhereNull('user_type')
                ->orWhere('user_type', '');

            // Filter by is_not_private parameter
            if ($request->has('is_not_private') && $request->is_not_private == 1) {
                // Show all customers (already filtered above)
            }

            // Add search capability if needed
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('phone_number', 'LIKE', "%{$search}%");
                });
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Get users - only select columns that exist in the users table
            $users = $query->get([
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'phone_number',
                'avatar',
                'sex',
                'dob',
                'address',
                'status',
                'user_type',
                'created_at',
                'updated_at'
            ]);

            // Transform data to match expected format
            $users = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'fullName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'avatar' => $user->avatar,
                    'sex' => $user->sex,
                    'dob' => $user->dob,
                    'address' => $user->address,
                    'status' => $user->status,
                    'user_type' => $user->user_type ?: 'Customer',
                    'country' => $user->country,
                    'tribe' => $user->tribe,
                    'father_name' => $user->father_name,
                    'mother_name' => $user->mother_name,
                    'child_1' => $user->child_1,
                    'child_2' => $user->child_2,
                    'child_3' => $user->child_3,
                    'child_4' => $user->child_4,
                    'sponsor_id' => $user->sponsor_id,
                    // Map to mobile app field names for backward compatibility
                    'swimming' => $user->tribe,
                    'transportation' => $user->child_1,
                    'residential_type' => $user->child_2,
                    'school_pay_account_id' => $user->child_3,
                    'phd_university_year_graduated' => $user->child_4,
                    'phd_university_name' => $user->sponsor_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

            return $this->success($users, 'Insurance users retrieved successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching insurance users: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to retrieve insurance users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single insurance user by ID
     * GET /api/insurance-users/{id}
     */
    public function insurance_user_show($id)
    {
        try {
            // Find user by ID
            $user = User::find($id);

            if (!$user) {
                return $this->error('Insurance user not found', 404);
            }

            // Transform data to match expected format
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'fullName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'avatar' => $user->avatar,
                'sex' => $user->sex,
                'dob' => $user->dob,
                'address' => $user->address,
                'status' => $user->status,
                'user_type' => $user->user_type ?: 'Customer',
                'country' => $user->country,
                'tribe' => $user->tribe,
                'father_name' => $user->father_name,
                'mother_name' => $user->mother_name,
                'child_1' => $user->child_1,
                'child_2' => $user->child_2,
                'child_3' => $user->child_3,
                'child_4' => $user->child_4,
                'sponsor_id' => $user->sponsor_id,
                // Map to mobile app field names for backward compatibility
                'swimming' => $user->tribe,
                'transportation' => $user->child_1,
                'residential_type' => $user->child_2,
                'school_pay_account_id' => $user->child_3,
                'phd_university_year_graduated' => $user->child_4,
                'phd_university_name' => $user->sponsor_id,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            return $this->success($userData, 'Insurance user retrieved successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching insurance user: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to retrieve insurance user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update insurance user by ID
     * PUT/PATCH /api/insurance-users/{id}
     */
    public function insurance_user_update(Request $request, $id)
    {
        try {
            // Find user by ID
            $user = User::find($id);

            if (!$user) {
                return $this->error('Insurance user not found', 404);
            }

            // Map old mobile app field names to new database columns
            $fieldMapping = [
                'swimming' => 'tribe',
                'transportation' => 'child_1',
                'residential_type' => 'child_2',
                'school_pay_account_id' => 'child_3',
                'phd_university_year_graduated' => 'child_4',
                'phd_university_name' => 'sponsor_id',
            ];
            
            // Update basic fields
            $basicFields = [
                'name', 'first_name', 'last_name', 'email', 'phone_number',
                'avatar', 'sex', 'dob', 'address', 'status', 'user_type', 'country'
            ];
            
            foreach ($basicFields as $field) {
                if ($request->has($field)) {
                    $user->$field = $request->input($field);
                }
            }
            
            // Update insurance user specific fields (handle both old and new names)
            $insuranceFields = [
                'tribe', 'father_name', 'mother_name', 
                'child_1', 'child_2', 'child_3', 'child_4', 'sponsor_id'
            ];
            
            foreach ($insuranceFields as $field) {
                if ($request->has($field)) {
                    $user->$field = $request->input($field);
                }
            }
            
            // Handle old mobile app field names
            foreach ($fieldMapping as $oldName => $newName) {
                if ($request->has($oldName)) {
                    $user->$newName = $request->input($oldName);
                }
            }

            // Handle password update if provided
            if ($request->has('password') && !empty($request->password)) {
                $user->password = bcrypt($request->password);
            }

            $user->save();

            // Transform data to match expected format
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'fullName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'avatar' => $user->avatar,
                'sex' => $user->sex,
                'dob' => $user->dob,
                'address' => $user->address,
                'status' => $user->status,
                'user_type' => $user->user_type ?: 'Customer',
                'country' => $user->country,
                'tribe' => $user->tribe,
                'father_name' => $user->father_name,
                'mother_name' => $user->mother_name,
                'child_1' => $user->child_1,
                'child_2' => $user->child_2,
                'child_3' => $user->child_3,
                'child_4' => $user->child_4,
                'sponsor_id' => $user->sponsor_id,
                // Map to mobile app field names for backward compatibility
                'swimming' => $user->tribe,
                'transportation' => $user->child_1,
                'residential_type' => $user->child_2,
                'school_pay_account_id' => $user->child_3,
                'phd_university_year_graduated' => $user->child_4,
                'phd_university_name' => $user->sponsor_id,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            return $this->success($userData, 'Insurance user updated successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Error updating insurance user: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to update insurance user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete insurance user by ID
     * DELETE /api/insurance-users/{id}
     */
    public function insurance_user_delete($id)
    {
        try {
            // Find user by ID
            $user = User::find($id);

            if (!$user) {
                return $this->error('Insurance user not found', 404);
            }

            // Soft delete the user
            $user->delete();

            return $this->success(null, 'Insurance user deleted successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Error deleting insurance user: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to delete insurance user: ' . $e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBERSHIP PAYMENT METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Create/Initiate membership payment
     * POST /api/membership-payment
     */
    public function membership_payment_create(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->userModel ?? $request->user();
            
            if (!$user) {
                $user_id = $request->input('user_id');
                if (!$user_id) {
                    return $this->error('User authentication required', 401);
                }
                $user = User::find($user_id);
                if (!$user) {
                    return $this->error('User not found', 404);
                }
            }

            // Check if user already has confirmed membership
            $existingMembership = MembershipPayment::where('user_id', $user->id)
                ->where('status', MembershipPayment::STATUS_CONFIRMED)
                ->first();

            if ($existingMembership && !$existingMembership->isExpired()) {
                return $this->error('User already has an active membership', 400, [
                    'membership' => $existingMembership
                ]);
            }

            // Create membership payment
            $membershipPayment = new MembershipPayment();
            $membershipPayment->user_id = $user->id;
            $membershipPayment->amount = $request->input('amount', MembershipPayment::DEFAULT_AMOUNT);
            $membershipPayment->payment_method = $request->input('payment_method', 'CASH');
            $membershipPayment->payment_phone_number = $request->input('payment_phone_number');
            $membershipPayment->payment_account_number = $request->input('payment_account_number');
            $membershipPayment->membership_type = $request->input('membership_type', MembershipPayment::MEMBERSHIP_TYPE_LIFE);
            $membershipPayment->notes = $request->input('notes');
            $membershipPayment->save();

            return $this->success($membershipPayment, 'Membership payment initiated successfully. Awaiting confirmation.', 201);

        } catch (\Exception $e) {
            \Log::error('Error creating membership payment: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to create membership payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get membership status for authenticated user
     * GET /api/membership-status
     */
    public function membership_status(Request $request)
    {
        try {
            // Try multiple ways to get the user
            $user = $request->userModel ?? $request->user();
            
            if (!$user) {
                // Try from User-Id header
                $user_id = $request->header('User-Id');
                
                // Try from user_id parameter
                if (!$user_id) {
                    $user_id = $request->input('user_id');
                }
                
                // Try from user parameter
                if (!$user_id) {
                    $user_id = $request->input('user');
                }
                
                if (!$user_id) {
                    return $this->error('User authentication required', 401);
                }
                
                $user = User::find($user_id);
                if (!$user) {
                    return $this->error('User not found', 404);
                }
            }

            // Check if user is admin (admins always have access)
            $isAdmin = $user->isAdmin();

            // Get active membership
            $activeMembership = MembershipPayment::getUserActiveMembership($user->id);

            // Get all membership payments
            $allPayments = MembershipPayment::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $response = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_type' => $user->user_type,
                'is_admin' => $isAdmin,
                'has_valid_membership' => $user->hasValidMembership(),
                'is_membership_paid' => $user->is_membership_paid,
                'membership_paid_at' => $user->membership_paid_at,
                'membership_amount' => $user->membership_amount,
                'membership_type' => $user->membership_type,
                'membership_expiry_date' => $user->membership_expiry_date,
                'active_membership' => $activeMembership,
                'all_payments' => $allPayments,
                'requires_payment' => !$user->hasValidMembership(),
            ];

            return $this->success($response, 'Membership status retrieved successfully', 200);

        } catch (\Exception $e) {
            \Log::error('Error getting membership status: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to get membership status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Safe membership check (READ-ONLY, no billing risk)
     * This endpoint is specifically designed to check membership status after payment
     * without any risk of double billing or payment processing
     * 
     * GET /api/membership-check
     */
    public function membership_check(Request $request)
    {
        try {
            // Try multiple ways to get the user
            $user = $request->userModel ?? $request->user();
            
            if (!$user) {
                // Try from User-Id header
                $user_id = $request->header('User-Id');
                
                // Try from user_id parameter
                if (!$user_id) {
                    $user_id = $request->input('user_id');
                }
                
                // Try from user parameter
                if (!$user_id) {
                    $user_id = $request->input('user');
                }
                
                if (!$user_id) {
                    return $this->error('User authentication required', 401);
                }
                
                $user = User::find($user_id);
                if (!$user) {
                    return $this->error('User not found', 404);
                }
            }

            // Force refresh from database to get latest data
            $user->refresh();

            // Check if user is admin (admins always have access)
            $isAdmin = $user->isAdmin();

            // Check if user has valid membership
            $hasValidMembership = $user->hasValidMembership();

            // Simple response focused on access control
            $response = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'is_admin' => $isAdmin,
                'has_valid_membership' => $hasValidMembership,
                'is_membership_paid' => $user->is_membership_paid,
                'membership_type' => $user->membership_type,
                'membership_paid_at' => $user->membership_paid_at,
                'membership_expiry_date' => $user->membership_expiry_date,
                'requires_payment' => !$hasValidMembership && !$isAdmin,
                'can_access_app' => $hasValidMembership || $isAdmin,
                'checked_at' => now()->toISOString(),
            ];

            \Log::info("Membership check for user {$user->id}: " . json_encode($response));

            return $this->success($response, 'Membership check completed', 200);

        } catch (\Exception $e) {
            \Log::error('Error in membership check: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to check membership: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm membership payment (admin or payment callback)
     * POST /api/membership-payment/confirm
     */
    public function membership_payment_confirm(Request $request)
    {
        try {
            $paymentReference = $request->input('payment_reference');
            $paymentId = $request->input('payment_id');

            if (!$paymentReference && !$paymentId) {
                return $this->error('Payment reference or payment ID required', 400);
            }

            // Find membership payment
            $membershipPayment = null;
            if ($paymentReference) {
                $membershipPayment = MembershipPayment::where('payment_reference', $paymentReference)->first();
            } elseif ($paymentId) {
                $membershipPayment = MembershipPayment::find($paymentId);
            }

            if (!$membershipPayment) {
                return $this->error('Membership payment not found', 404);
            }

            // Check if already confirmed
            if ($membershipPayment->status == MembershipPayment::STATUS_CONFIRMED) {
                return $this->success($membershipPayment, 'Membership payment already confirmed', 200);
            }

            // Confirm the payment
            $confirmedBy = $request->user() ? $request->user()->id : null;
            $membershipPayment->confirm($confirmedBy);

            // Get updated user
            $user = User::find($membershipPayment->user_id);

            return $this->success([
                'membership_payment' => $membershipPayment,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_membership_paid' => $user->is_membership_paid,
                    'membership_paid_at' => $user->membership_paid_at,
                    'membership_amount' => $user->membership_amount,
                    'membership_type' => $user->membership_type,
                    'membership_expiry_date' => $user->membership_expiry_date,
                ]
            ], 'Membership payment confirmed successfully', 200);

        } catch (\Exception $e) {
            \Log::error('Error confirming membership payment: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to confirm membership payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get membership benefits information
     * GET /api/membership-benefits
     */
    public function membership_benefits()
    {
        $benefits = [
            'membership_fee' => MembershipPayment::DEFAULT_AMOUNT,
            'currency' => 'UGX',
            'membership_type' => 'LIFE',
            'benefits' => [
                'Access to all insurance programs',
                'Ability to subscribe to health insurance coverage',
                'Access to medical service requests',
                'Participate in community savings and investments',
                'Access to project investments and dividends',
                'Financial account management',
                'Access to transaction history',
                'Priority customer support',
                'Lifetime membership (one-time payment)',
                'No renewal fees',
            ],
            'payment_methods' => [
                [
                    'code' => 'MOBILE_MONEY',
                    'name' => 'Mobile Money',
                    'description' => 'Pay via MTN Mobile Money or Airtel Money',
                    'requires_phone' => true,
                ],
                [
                    'code' => 'BANK_TRANSFER',
                    'name' => 'Bank Transfer',
                    'description' => 'Transfer to our bank account',
                    'requires_account' => true,
                ],
                [
                    'code' => 'CASH',
                    'name' => 'Cash Payment',
                    'description' => 'Pay cash at our office',
                    'requires_photo' => true,
                ],
                [
                    'code' => 'PESAPAL',
                    'name' => 'Online Payment',
                    'description' => 'Pay securely with card or mobile money via Pesapal',
                    'requires_nothing' => true,
                ],
            ],
        ];

        return $this->success($benefits, 'Membership benefits retrieved successfully', 200);
    }

    /**
     * List user's membership payments
     * GET /api/membership-payments
     */
    public function membership_payments_list(Request $request)
    {
        try {
            // Try multiple ways to get the user
            $user = $request->userModel ?? $request->user();
            
            if (!$user) {
                // Try from User-Id header
                $user_id = $request->header('User-Id');
                
                // Try from user_id parameter
                if (!$user_id) {
                    $user_id = $request->input('user_id');
                }
                
                // Try from user parameter
                if (!$user_id) {
                    $user_id = $request->input('user');
                }
                
                if (!$user_id) {
                    return $this->error('User authentication required', 401);
                }
                
                $user = User::find($user_id);
                if (!$user) {
                    return $this->error('User not found', 404);
                }
            }

            $payments = MembershipPayment::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success($payments, 'Membership payments retrieved successfully', 200);

        } catch (\Exception $e) {
            \Log::error('Error getting membership payments: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->error('Failed to get membership payments: ' . $e->getMessage(), 500);
        }
    }
}

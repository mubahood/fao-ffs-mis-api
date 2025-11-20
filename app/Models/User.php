<?php

namespace App\Models;
 
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Form\Field\BelongsToMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as RelationsBelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;



class User extends Administrator implements JWTSubject
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    /**
     * Boot method to handle model events - Simplified for FAO FFS-MIS
     */
    public static function boot() 
    {
        parent::boot();

        // Handle member creation
        static::creating(function ($user) {
            self::sanitizeData($user);
            self::handleNameSplitting($user);
            self::validateUniqueFields($user);
            self::generateMemberCode($user);
        });

        // Handle member updates
        static::updating(function ($user) {
            self::sanitizeData($user);
            self::handleNameSplitting($user);
            self::validateUniqueFields($user, true);
        });
        
        // Prevent deletion of members
        static::deleting(function ($user) {
            if ($user->user_type == 'Customer') {
                throw new \Exception('Members cannot be deleted. Please set status to Inactive instead.');
            }
        });
    }
    
    /**
     * Generate unique member code for FAO FFS-MIS
     */
    protected static function generateMemberCode($user)
    {
        if (!empty($user->member_code)) {
            return;
        }
        
        // Format: XXX-MEM-YY-NNNN
        // XXX = District code (first 3 letters)
        // MEM = Member
        // YY = Year (25 for 2025)
        // NNNN = Sequential number
        
        $districtCode = 'XXX';
        if ($user->district_id) {
            $district = \App\Models\Location::find($user->district_id);
            if ($district) {
                $districtCode = strtoupper(substr($district->name, 0, 3));
            }
        }
        
        $year = date('y');
        
        // Get the last member code for this district and year
        $lastMember = self::where('member_code', 'like', "$districtCode-MEM-$year-%")
            ->orderBy('member_code', 'desc')
            ->first();
        
        if ($lastMember && preg_match('/-(\d{4})$/', $lastMember->member_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        $user->member_code = sprintf('%s-MEM-%s-%04d', $districtCode, $year, $nextNumber);
    }

    /**
     * Sanitize user data - trim strings, clean up whitespace
     */
    protected static function sanitizeData($user)
    {
        // Trim phone number if not empty
        if (!empty($user->phone_number)) {
            $user->phone_number = trim($user->phone_number);
        }
        
        // Trim email if not empty
        if (!empty($user->email)) {
            $user->email = trim($user->email);
        }
        
        // Trim name if not empty
        if (!empty($user->name)) {
            $user->name = trim($user->name);
        }
        
        // Trim first_name if not empty
        if (!empty($user->first_name)) {
            $user->first_name = trim($user->first_name);
        }
        
        // Trim last_name if not empty
        if (!empty($user->last_name)) {
            $user->last_name = trim($user->last_name);
        }
        
        // Trim address if not empty
        if (!empty($user->address)) {
            $user->address = trim($user->address);
        }
    }

    /**
     * Handle name splitting - split full name into first_name and last_name
     */
    protected static function handleNameSplitting($user)
    {
        // If name is provided but first_name or last_name is empty, split the name
        if (!empty($user->name) && (empty($user->first_name) || empty($user->last_name))) {
            $nameParts = self::splitFullName($user->name);
            
            if (empty($user->first_name)) {
                $user->first_name = $nameParts['first_name'];
            }
            
            if (empty($user->last_name)) {
                $user->last_name = $nameParts['last_name'];
            }
        }
        
        // If first_name and last_name are provided but name is empty, combine them
        if (!empty($user->first_name) && !empty($user->last_name) && empty($user->name)) {
            $user->name = trim($user->first_name . ' ' . $user->last_name);
        }
        
        // If only name is provided during update, always split it
        if (!empty($user->name) && $user->isDirty('name')) {
            $nameParts = self::splitFullName($user->name);
            $user->first_name = $nameParts['first_name'];
            $user->last_name = $nameParts['last_name'];
        }
    }

    /**
     * Split full name into first name and last name intelligently
     */
    protected static function splitFullName($fullName)
    {
        // Trim and remove extra spaces
        $fullName = preg_replace('/\s+/', ' ', trim($fullName));
        
        // Split by space
        $parts = explode(' ', $fullName);
        
        if (count($parts) == 1) {
            // Only one name provided - use it for both
            return [
                'first_name' => $parts[0],
                'last_name' => $parts[0]
            ];
        } elseif (count($parts) == 2) {
            // Two names - first and last
            return [
                'first_name' => $parts[0],
                'last_name' => $parts[1]
            ];
        } else {
            // Three or more names - first name is first part, last name is everything else
            $firstName = array_shift($parts);
            $lastName = implode(' ', $parts);
            
            return [
                'first_name' => $firstName,
                'last_name' => $lastName
            ];
        }
    }

    /**
     * Validate unique fields (email and phone_number)
     */
    protected static function validateUniqueFields($user, $isUpdate = false)
    {
        // Validate email uniqueness (if provided and not null)
        if (!empty($user->email) && $user->email !== null) {
            $emailQuery = self::where('email', $user->email);
            
            // Exclude current user ID when updating
            if ($isUpdate && $user->id) {
                $emailQuery->where('id', '!=', $user->id);
            }
            
            if ($emailQuery->exists()) {
                throw new \Exception("The email '{$user->email}' is already registered. Please use a different email address.");
            }
        }

        // Validate phone_number uniqueness (if provided and not null)
        if (!empty($user->phone_number) && $user->phone_number !== null) {
            $phoneQuery = self::where('phone_number', $user->phone_number);
            
            // Exclude current user ID when updating
            if ($isUpdate && $user->id) {
                $phoneQuery->where('id', '!=', $user->id);
            }
            
            if ($phoneQuery->exists()) {
                throw new \Exception("The phone number '{$user->phone_number}' is already registered. Please use a different phone number.");
            }
        }
    }

    /**
     * Generate DIP ID for user (format: DIP0001, DIP0002, etc.)
     * The ID has constant length of 7 characters (DIP + 4 digits with leading zeros)
     */
    protected static function generateDipId($user)
    {
        // Only generate if business_name (DIP ID) is not already set
        if (!empty($user->business_name)) {
            return;
        }

        try {
            // Get the highest existing DIP ID number
            $lastUser = self::whereNotNull('business_name')
                ->where('business_name', 'LIKE', 'DIP%')
                ->orderBy('business_name', 'DESC')
                ->first();

            $nextNumber = 1;

            if ($lastUser && !empty($lastUser->business_name)) {
                // Extract the number from the last DIP ID (e.g., "DIP0045" -> 45)
                $lastNumber = intval(substr($lastUser->business_name, 3));
                $nextNumber = $lastNumber + 1;
            }

            // Format with leading zeros to maintain constant length (4 digits)
            // DIP0001, DIP0010, DIP0100, DIP1000, DIP9999
            $dipId = 'DIP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Ensure uniqueness (in rare case of race conditions)
            $attempts = 0;
            while (self::where('business_name', $dipId)->exists() && $attempts < 10) {
                $nextNumber++;
                $dipId = 'DIP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                $attempts++;
            }

            $user->business_name = $dipId;

        } catch (\Exception $e) {
            // If generation fails, log the error but don't block user creation
            \Log::error('DIP ID generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate DTEHM Member ID (format: DTEHM20250001)
     * Only generates when is_dtehm_member = 'Yes'
     */
    protected static function generateDtehmMemberId($user)
    {
        // Only generate if user is a DTEHM member and ID not already set
        if ($user->is_dtehm_member !== 'Yes' || !empty($user->dtehm_member_id)) {
            return;
        }

        try {
            $year = date('Y');
            $prefix = 'DTEHM' . $year;

            // Get the highest existing DTEHM ID for this year
            $lastMember = self::whereNotNull('dtehm_member_id')
                ->where('dtehm_member_id', 'LIKE', $prefix . '%')
                ->orderBy('dtehm_member_id', 'DESC')
                ->first();

            $nextNumber = 1;

            if ($lastMember && !empty($lastMember->dtehm_member_id)) {
                // Extract the number from the last ID (e.g., "DTEHM20250045" -> 45)
                $lastNumber = intval(substr($lastMember->dtehm_member_id, strlen($prefix)));
                $nextNumber = $lastNumber + 1;
            }

            // Format with leading zeros (4 digits)
            $dtehmId = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Ensure uniqueness
            $attempts = 0;
            while (self::where('dtehm_member_id', $dtehmId)->exists() && $attempts < 10) {
                $nextNumber++;
                $dtehmId = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                $attempts++;
            }

            $user->dtehm_member_id = $dtehmId;

            // Set membership date if not already set
            if (empty($user->dtehm_member_membership_date)) {
                $user->dtehm_member_membership_date = now();
            }

        } catch (\Exception $e) {
            \Log::error('DTEHM Member ID generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Populate parent hierarchy (parent_1 to parent_10) based on sponsor chain
     * This runs AFTER user is created so we have a user ID
     */
    protected static function populateParentHierarchy($user)
    {
        try {
            // Skip if no sponsor_id
            if (empty($user->sponsor_id)) {
                return;
            }

            // Find the sponsor (parent_1) - try DIP ID first, then DTEHM ID
            $currentParent = self::where('business_name', $user->sponsor_id)->first();
            
            // If not found by DIP ID, try DTEHM Member ID
            if (!$currentParent) {
                $currentParent = self::where('dtehm_member_id', $user->sponsor_id)->first();
            }
            
            if (!$currentParent) {
                return; // Sponsor not found
            }

            // Array to store parent IDs
            $parents = [];
            $visited = [$user->id]; // Track visited users to prevent infinite loops
            $level = 1;

            // Traverse up the hierarchy for 10 levels
            while ($currentParent && $level <= 10) {
                // Prevent infinite loops - check if we've seen this user before
                if (in_array($currentParent->id, $visited)) {
                    \Log::warning("Circular reference detected in user hierarchy for user ID: {$user->id} at level {$level}");
                    break;
                }

                // Prevent self-reference
                if ($currentParent->id === $user->id) {
                    \Log::warning("Self-reference detected in user hierarchy for user ID: {$user->id}");
                    break;
                }

                // Store this parent
                $parents["parent_{$level}"] = $currentParent->id;
                $visited[] = $currentParent->id;

                // Move to next level - get the sponsor of current parent
                if (!empty($currentParent->sponsor_id)) {
                    $currentParent = self::where('business_name', $currentParent->sponsor_id)->first();
                } else {
                    // No more parents in the chain
                    break;
                }

                $level++;
            }

            // Update the user with all parent IDs at once
            if (!empty($parents)) {
                // Use update query to avoid triggering events again
                self::where('id', $user->id)->update($parents);
                
                // Refresh the model instance to reflect changes
                $user->refresh();
            }

        } catch (\Exception $e) {
            \Log::error("Parent hierarchy population failed for user ID {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Get the sponsor user by DIP ID or DTEHM Member ID
     * 
     * @return User|null
     */
    public function sponsor()
    {
        if (empty($this->sponsor_id)) {
            return null;
        }

        // Try to find by DIP ID (business_name) first
        $sponsor = self::where('business_name', $this->sponsor_id)->first();
        
        // If not found, try DTEHM Member ID
        if (!$sponsor) {
            $sponsor = self::where('dtehm_member_id', $this->sponsor_id)->first();
        }

        return $sponsor;
    }

    /**
     * Get all users sponsored by this user (using DIP ID or DTEHM ID)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function sponsoredUsers()
    {
        $users = collect([]);
        
        // Get users by DIP ID
        if (!empty($this->business_name)) {
            $users = $users->merge(
                self::where('sponsor_id', $this->business_name)->get()
            );
        }
        
        // Get users by DTEHM Member ID
        if (!empty($this->dtehm_member_id)) {
            $users = $users->merge(
                self::where('sponsor_id', $this->dtehm_member_id)->get()
            );
        }

        return $users->unique('id');
    }

    /**
     * Get count of users sponsored by this user
     * 
     * @return int
     */
    public function sponsoredUsersCount()
    {
        if (empty($this->business_name)) {
            return 0;
        }

        return self::where('sponsor_id', $this->business_name)->count();
    }

    /**
     * Get all users at a specific generation level (downline)
     * 
     * @param int $generation (1-10)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getGenerationUsers($generation)
    {
        if ($generation < 1 || $generation > 10) {
            return collect([]);
        }

        $parentField = "parent_{$generation}";
        return self::where($parentField, $this->id)->get();
    }

    /**
     * Get count of users at a specific generation level
     * 
     * @param int $generation (1-10)
     * @return int
     */
    public function getGenerationCount($generation)
    {
        if ($generation < 1 || $generation > 10) {
            return 0;
        }

        $parentField = "parent_{$generation}";
        return self::where($parentField, $this->id)->count();
    }

    /**
     * Get all downline users across all 10 generations
     * 
     * @return array Array with keys gen_1 to gen_10
     */
    public function getAllGenerations()
    {
        $generations = [];
        
        for ($i = 1; $i <= 10; $i++) {
            $generations["gen_{$i}"] = $this->getGenerationUsers($i);
        }

        return $generations;
    }

    /**
     * Get total count of all downline users
     * 
     * @return int
     */
    public function getTotalDownlineCount()
    {
        $total = 0;
        
        for ($i = 1; $i <= 10; $i++) {
            $total += $this->getGenerationCount($i);
        }

        return $total;
    }

    /**
     * Get parent user at a specific level
     * 
     * @param int $level (1-10)
     * @return User|null
     */
    public function getParentAtLevel($level)
    {
        if ($level < 1 || $level > 10) {
            return null;
        }

        $parentField = "parent_{$level}";
        $parentId = $this->$parentField;

        if (empty($parentId)) {
            return null;
        }

        return self::find($parentId);
    }

    /**
     * Get all parents up the hierarchy
     * 
     * @return array Array with keys parent_1 to parent_10
     */
    public function getAllParents()
    {
        $parents = [];
        
        for ($i = 1; $i <= 10; $i++) {
            $parent = $this->getParentAtLevel($i);
            if ($parent) {
                $parents["parent_{$i}"] = $parent;
            }
        }

        return $parents;
    }

    /**
     * Alternatively, you can use fillable to explicitly allow specific fields:
     * Uncomment and modify if you prefer explicit fillable over guarded
     */
    /*
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'phone_number_2',
        'user_type',
        'is_membership_paid',
        'membership_paid_at',
        'membership_amount',
        'membership_payment_id',
        'membership_type',
        'membership_expiry_date',
        // Add other fields as needed
    ];
    */

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function send_password_reset()
    {
        $u = $this;
        $u->intro = rand(100000, 999999);
        $u->save();
        $data['email'] = $u->email;
        if ($u->email == null || $u->email == "") {
            $data['email'] = $u->username;
        }
        $data['name'] = $u->name;
        $data['subject'] = env('APP_NAME') . " - Password Reset";
        $data['body'] = "<br>Dear " . $u->name . ",<br>";
        $data['body'] .= "<br>Please use the code below to reset your password.<br><br>";
        $data['body'] .= "CODE: <b>" . $u->intro . "</b><br>";
        $data['body'] .= "<br>Thank you.<br><br>";
        $data['body'] .= "<br><small>This is an automated message, please do not reply.</small><br>";
        $data['view'] = 'mail-1';
        $data['data'] = $data['body'];
        try {
            Utils::mail_sender($data);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function send_verification_code($email)
    {
        $u = $this;
        $u->intro = rand(100000, 999999);
        $u->save(); 
        $data['email'] = $email;
        if ($email == null || $email == "") {
            throw new \Exception("Email is required.");
        }

        $data['name'] = $u->name;
        $data['subject'] = env('APP_NAME') . " - Email Verification";
        $data['body'] = "<br>Dear " . $u->name . ",<br>";
        $data['body'] .= "<br>Please use the CODE below to verify your email address.<br><br>";
        $data['body'] .= "CODE: <b>" . $u->intro . "</b><br>";
        $data['body'] .= "<br>Thank you.<br><br>";
        $data['body'] .= "<br><small>This is an automated message, please do not reply.</small><br>";
        $data['view'] = 'mail-1';
        $data['data'] = $data['body'];
        try {
            Utils::mail_sender($data);
        } catch (\Throwable $th) {
            throw $th;
        }
    }



    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function programs()
    {
        return $this->hasMany(UserHasProgram::class, 'user_id');
    }
    
    /**
     * Get the group this member belongs to
     */
    public function group()
    {
        return $this->belongsTo(\App\Models\FfsGroup::class, 'group_id');
    }
    
    /**
     * Get the district
     */
    public function district()
    {
        return $this->belongsTo(\App\Models\Location::class, 'district_id');
    }
    
    /**
     * Get the subcounty
     */
    public function subcounty()
    {
        return $this->belongsTo(\App\Models\Location::class, 'subcounty_id');
    }
    
    /**
     * Get the parish
     */
    public function parish()
    {
        return $this->belongsTo(\App\Models\Location::class, 'parish_id');
    }

    /**
     * Get the user's wishlist items.
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the user's wishlist products.
     */
    public function wishlistProducts()
    {
        return $this->hasManyThrough(Product::class, Wishlist::class, 'user_id', 'id', 'id', 'product_id');
    }

    /**
     * Get all reviews written by this user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get recent reviews by this user.
     */
    public function recentReviews($limit = 5)
    {
        return $this->hasMany(Review::class)->latest()->limit($limit);
    }

    /**
     * Check if user has reviewed a specific product.
     */
    public function hasReviewedProduct($productId)
    {
        return $this->reviews()->where('product_id', $productId)->exists();
    }

    /**
     * Get user's review for a specific product.
     */
    public function getProductReview($productId)
    {
        return $this->reviews()->where('product_id', $productId)->first();
    }

    /**
     * Get user's account transactions
     */
    public function accountTransactions()
    {
        return $this->hasMany(AccountTransaction::class);
    }

    /**
     * Get user's withdraw requests
     */
    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    /**
     * Get user's project shares (investments)
     */
    public function projectShares()
    {
        return $this->hasMany(ProjectShare::class, 'investor_id');
    }

    /**
     * Get user's account balance (computed from account transactions)
     */
    public function getAccountBalanceAttribute()
    {
        return $this->accountTransactions()->sum('amount');
    }

    /**
     * Calculate and return account balance
     */
    public function calculateAccountBalance()
    {
        return $this->accountTransactions()->sum('amount');
    }

    /**
     * Get user's membership payment
     */
    public function membershipPayment()
    {
        return $this->belongsTo(MembershipPayment::class, 'membership_payment_id');
    }

    /**
     * Get all membership payments for this user
     */
    public function membershipPayments()
    {
        return $this->hasMany(MembershipPayment::class, 'user_id');
    }

    /**
     * Get user's DTEHM membership payment
     */
    public function dtehmMembershipPayment()
    {
        return $this->belongsTo(DtehmMembership::class, 'dtehm_membership_payment_id');
    }

    /**
     * Get all DTEHM membership payments for this user
     */
    public function dtehmMembershipPayments()
    {
        return $this->hasMany(DtehmMembership::class, 'user_id');
    }

    /**
     * Get the admin who registered this user
     */
    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by_id');
    }

    /**
     * Get users registered by this admin
     */
    public function registeredUsers()
    {
        return $this->hasMany(User::class, 'registered_by_id');
    }

    /**
     * Check if user has valid membership
     * For FAO FFS-MIS, all registered users have access (no membership subscription required)
     */
    public function hasValidMembership()
    {
        // All users have access - no membership subscription required
        return true;
    }

    /**
     * Check if user is admin (case-insensitive check)
     */
    public function isAdmin()
    {
        if (!$this->user_type) {
            return false;
        }
        return strtolower($this->user_type) === 'admin';
    }

    /**
     * Reset user password and send credentials via SMS
     * 
     * @return object Response object with success status and message
     */
    public function resetPasswordAndSendSMS()
    {
        $response = (object)[
            'success' => false,
            'message' => '',
            'password' => null,
            'sms_sent' => false,
            'sms_response' => null
        ];

        try {
            // Validate phone number
            if (empty($this->phone_number)) {
                $response->message = 'User has no phone number registered';
                return $response;
            }

            if (!Utils::phone_number_is_valid($this->phone_number)) {
                $response->message = 'User has invalid phone number: ' . $this->phone_number;
                return $response;
            }

            // Generate 6-digit random password
            $newPassword = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $response->password = $newPassword;

            // Hash and save password
            $this->password = password_hash($newPassword, PASSWORD_DEFAULT);
            
            try {
                $this->save();
            } catch (\Exception $e) {
                $response->message = 'Failed to save new password: ' . $e->getMessage();
                return $response;
            }

            // Prepare welcome message with credentials
            $appName = env('APP_NAME', 'DTEHM Insurance');
            $userName = $this->name ?? $this->first_name ?? 'User';
            
            $message = "Welcome to {$appName}! Your login credentials:\n"
                     . "Email: {$this->email}\n"
                     . "Password: {$newPassword}\n"
                     . "Download our app to get started!";

            // Send SMS
            $smsResponse = Utils::sendSMS($this->phone_number, $message);
            $response->sms_response = $smsResponse;
            $response->sms_sent = $smsResponse->success;

            if ($smsResponse->success) {
                $response->success = true;
                $response->message = 'Password reset successfully and SMS sent to ' . $this->phone_number;
            } else {
                $response->success = false;
                $response->message = 'Password reset but SMS failed: ' . $smsResponse->message;
            }

            return $response;

        } catch (\Exception $e) {
            $response->message = 'Error during password reset: ' . $e->getMessage();
            return $response;
        } catch (\Throwable $e) {
            $response->message = 'Critical error: ' . $e->getMessage();
            return $response;
        }
    }

    /**
     * Send welcome SMS with custom message
     * 
     * @param string|null $customMessage Custom message to send (optional)
     * @return object Response object
     */
    public function sendWelcomeSMS($customMessage = null)
    {
        $response = (object)[
            'success' => false,
            'message' => '',
            'sms_response' => null
        ];

        try {
            // Validate phone number
            if (empty($this->phone_number)) {
                $response->message = 'User has no phone number registered';
                return $response;
            }

            if (!Utils::phone_number_is_valid($this->phone_number)) {
                $response->message = 'Invalid phone number: ' . $this->phone_number;
                return $response;
            }

            // Prepare message
            $appName = env('APP_NAME', 'DTEHM Insurance');
            $userName = $this->name ?? $this->first_name ?? 'User';

            if ($customMessage) {
                $message = $customMessage;
            } else {
                $message = "Hello {$userName}! Welcome to {$appName}. "
                         . "Get comprehensive insurance coverage at your fingertips. "
                         . "Download our app today and secure your future!";
            }

            // Send SMS
            $smsResponse = Utils::sendSMS($this->phone_number, $message);
            $response->sms_response = $smsResponse;

            if ($smsResponse->success) {
                $response->success = true;
                $response->message = 'Welcome SMS sent successfully to ' . $this->phone_number;
            } else {
                $response->message = 'Failed to send SMS: ' . $smsResponse->message;
            }

            return $response;

        } catch (\Exception $e) {
            $response->message = 'Error sending welcome SMS: ' . $e->getMessage();
            return $response;
        } catch (\Throwable $e) {
            $response->message = 'Critical error: ' . $e->getMessage();
            return $response;
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiAuthController extends Controller
{

    use ApiResponser;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {

        /* $token = auth('api')->attempt([
            'username' => 'admin',
            'password' => 'admin',
        ]);
        die($token); */
        $this->middleware('auth:api', ['except' => [
            'manifest',
            'login',
            'register'
        ]]);
    }


    public function manifest()
    {

        $carbon = new Carbon();
        $TOP_PRODUCTS = Product::where([])->orderBy('id', 'desc')->limit(1000)->get();
        $TOP_PRODUCTS = $TOP_PRODUCTS->shuffle();
        $TOP_4_PRODUCTS = $TOP_PRODUCTS->take(4);

        $SECTION_1_PRODUCTS = [];
        //TAKE NEXT 20 without the first 4
        if (count($TOP_PRODUCTS) > 4) {
            $SECTION_1_PRODUCTS = $TOP_PRODUCTS->slice(4, 30);
        } else {
            $SECTION_1_PRODUCTS = $TOP_PRODUCTS;
        }

        $SECTION_2_PRODUCTS = [];
        //TAKE NEXT 20 without the first 24
        if (count($TOP_PRODUCTS) > 24) {
            $SECTION_2_PRODUCTS = $TOP_PRODUCTS->slice(34, 30);
        } else {
            $SECTION_2_PRODUCTS = $TOP_PRODUCTS;
        }

        $manifest = [
            'FIRST_BANNER' => ProductCategory::where([
                'is_first_banner' => 'Yes'
            ])->first(),
            'SLIDER_CATEGORIES' => ProductCategory::where([
                'show_in_banner' => 'Yes'
            ])->get(),
            'TOP_4_PRODUCTS' => $TOP_4_PRODUCTS,
            'CATEGORIES' => ProductCategory::where([
                'show_in_categories' => 'Yes'
            ])->get(),
            'SECTION_1_PRODUCTS' => $SECTION_1_PRODUCTS,
            'SECTION_2_PRODUCTS' => $SECTION_2_PRODUCTS,
        ];

        return $this->success($manifest, 'Success');
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $query = auth('api')->user();
        return $this->success($query, $message = "Profile details", 200);
    }





    public function login(Request $r)
    {
        if ($r->username == null) {
            return $this->error('Username is required.');
        }

        if (isset($r->task)) {
            if ($r->task == 'reset_password') {
                $u = User::where('email', $r->email)->first();
                if ($u == null) {
                    return $this->error('User account with email ' . $r->email . ' not found.');
                }

                $code = $r->code;
                if ($code == null || strlen($code) < 3) {
                    return $this->error('Code is required.');
                }
                if ($u->intro != $code) {
                    return $this->error('Invalid code.');
                }
                $password = $r->password;
                if ($password == null || strlen($password) < 3) {
                    return $this->error('Password is required.');
                }
                $u->password = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $u->save();
                } catch (\Throwable $th) {
                    return $this->error('Failed to reset password because ' . $th->getMessage() . '.');
                }
                return $this->success($u, 'Password reset successfully.');
            } else if ($r->task == 'request_password_reset') {
                $u = User::where('email', $r->username)->first();
                if ($u == null) {
                    return $this->error('User account not found.');
                }
                try {
                    $u->send_password_reset();
                } catch (\Throwable $th) {
                    return $this->error('Failed to send password reset email because ' . $th->getMessage() . '.');
                }
                return $this->success($u, 'Password reset CODE sent to your email address ' . $u->email . '.');
            }
            return $this->error('Invalid task.');
        }

        if ($r->password == null) {
            return $this->error('Password is required.');
        }

        $username = $r->get('username');

        $u = User::where('phone_number', $username)
            ->orWhere('username', $username) 
            ->orWhere('email', $username)
            ->first();



        $phone_number = null;
        if ($u == null) {
            
            $phone_number = Utils::prepare_phone_number($r->username);
            if (Utils::phone_number_is_valid($phone_number)) {
                $phone_number = $r->phone_number;

                $u = User::where('phone_number', $phone_number)
                    ->orWhere('username', $phone_number)
                    ->orWhere('email', $phone_number)
                    ->first();
            }
        }

        if ($u == null) {
            return $this->error('User account not found. username: ' . $username . ' phone: ' . $phone_number);
        }

        if ($u->status == 'Deleted') {
            return $this->error('User account not found. Contact us for help.');
        }

        JWTAuth::factory()->setTTL(60 * 24 * 30 * 365);

        $token = auth('api')->attempt([
            'id' => $u->id,
            'password' => trim($r->password),
        ]);


        if ($token == null) {
            return $this->error('Wrong credentials.');
        }



        $u->token = $token;
        $u->remember_token = $token;

        return $this->success($u, 'Logged in successfully.');
    }

    public function register(Request $r)
    {
        if ($r->phone_number == null || empty(trim($r->phone_number))) {
            return $this->error('Phone number is required.');
        }

        $phone_number = trim($r->phone_number);
        
        // Validate phone number length
        if (strlen($phone_number) < 10) {
            return $this->error('Phone number must be at least 10 digits.');
        }

        if ($r->password == null) {
            return $this->error('Password is required.');
        }

        if ($r->name == null) {
            return $this->error('Name is required.');
        }

        // Check for existing user with same phone number
        $existingUser = Administrator::where('phone_number', $phone_number)
            ->orWhere('username', $phone_number);
        
        $u = $existingUser->first();

        if ($u != null) {
            if ($u->status == 'Deleted') {
                return $this->error('This phone number is associated with a deleted account. Contact us for help.');
            }

            return $this->error('User with this phone number already exists.');
        }

        $user = new Administrator();

        $name = trim($r->name);

        // Split name into first_name and last_name
        // Remove extra spaces and split
        $nameParts = preg_split('/\s+/', $name);
        
        if (count($nameParts) == 1) {
            // Only one name - use for both
            $user->first_name = $nameParts[0];
            $user->last_name = $nameParts[0];
        } elseif (count($nameParts) == 2) {
            // Two names - first and last
            $user->first_name = $nameParts[0];
            $user->last_name = $nameParts[1];
        } else {
            // Three or more names - first is first, rest is last
            $user->first_name = $nameParts[0];
            array_shift($nameParts);
            $user->last_name = implode(' ', $nameParts);
        }
        
        $user->name = $name;
        $user->username = $phone_number;
        $user->email = $phone_number . '@faoffsmis.org'; // Generate email from phone
        $user->reg_number = $phone_number;
        $user->phone_number = $phone_number;
        
        // Set address from request if provided
        $user->address = $r->address != null ? trim($r->address) : '';
        
        // Set group_id from request if provided
        if ($r->group_id != null && !empty(trim($r->group_id))) {
            $groupId = intval(trim($r->group_id));
            // Verify that group exists and is active
            $group = \App\Models\FfsGroup::where('id', $groupId)
                ->where('status', 'Active')
                ->first();
            if ($group) {
                $user->group_id = $groupId;
            }
            // If group doesn't exist or isn't active, we'll just ignore it (don't block registration)
        }
        
        // Set optional fields with empty defaults
        $user->profile_photo_large = '';
        $user->location_lat = '';
        $user->location_long = '';
        $user->facebook = '';
        $user->twitter = '';
        $user->linkedin = '';
        $user->website = '';
        $user->other_link = '';
        $user->cv = '';
        $user->language = '';
        $user->about = '';
        $user->country = '';
        $user->occupation = '';
        
        $user->password = password_hash(trim($r->password), PASSWORD_DEFAULT);
        
        try {
            if (!$user->save()) {
                return $this->error('Failed to create account. Please try again.');
            }
        } catch (\Exception $e) {
            // Catch validation errors from boot() method
            return $this->error('Registration failed: ' . $e->getMessage());
        }

        $new_user = Administrator::find($user->id);
        if ($new_user == null) {
            return $this->error('Account created successfully but failed to log you in.');
        }
        Config::set('jwt.ttl', 60 * 24 * 30 * 365);

        $token = auth('api')->attempt([
            'username' => $phone_number,
            'password' => trim($r->password),
        ]);

        $new_user->token = $token;
        $new_user->remember_token = $token;
        return $this->success($new_user, 'Account created successfully.');
    }
}

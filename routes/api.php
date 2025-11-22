<?php

use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\ApiResurceController;
use App\Http\Controllers\PesapalController;
use App\Http\Controllers\PesapalAdminController;
// InsuranceUserController removed - using ApiResurceController instead
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\AccountTransactionController;
use App\Http\Controllers\UserAccountController;
use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post("account-verification", [ApiResurceController::class, 'account_verification']);
Route::post("password-change", [ApiResurceController::class, 'password_change']);
Route::post("update-profile", [ApiResurceController::class, 'update_profile'])->middleware(EnsureTokenIsValid::class);
Route::post("profile-update-comprehensive", [ApiResurceController::class, 'profile_update_comprehensive'])->middleware(EnsureTokenIsValid::class);
Route::post("delete-account", [ApiResurceController::class, 'delete_profile']);
Route::post("become-vendor", [ApiResurceController::class, 'become_vendor']);
Route::post("post-media-upload", [ApiResurceController::class, 'upload_media']);
Route::post("cancel-order", [ApiResurceController::class, "orders_cancel"]);
Route::post("orders", [ApiResurceController::class, "orders_submit"]);
Route::post("orders-create", [ApiResurceController::class, "orders_create"]);
Route::post("orders-with-payment", [ApiResurceController::class, "orders_with_payment"]);
Route::post("product-create", [ApiResurceController::class, "product_create"]);
Route::get("orders", [ApiResurceController::class, "orders_get"]);
Route::get('orders/check-pending-emails', [ApiResurceController::class, 'check_and_send_pending_emails']);
Route::get("orders/{id}", [ApiResurceController::class, "orders_get_by_id"]);
Route::get("products/{id}", [ApiResurceController::class, "product_get_by_id"]);
Route::get("order", [ApiResurceController::class, "order"]);
Route::get("vendors", [ApiResurceController::class, "vendors"]);
Route::get("delivery-addresses", [ApiResurceController::class, "delivery_addresses"]);
Route::get("locations", [ApiResurceController::class, "locations"]);
Route::get("categories", [ApiResurceController::class, "categories"]);
Route::get('products', [ApiResurceController::class, 'products']);
Route::get('products-1', [ApiResurceController::class, 'products_1']);
Route::post('products-delete', [ApiResurceController::class, 'products_delete']);
Route::post('images-delete', [ApiResurceController::class, 'images_delete']);
Route::post('chat-start', [ApiResurceController::class, 'chat_start']);
Route::post('chat-send', [ApiResurceController::class, 'chat_send']);
Route::post('chat-mark-as-read', [ApiResurceController::class, 'chat_mark_as_read']);
Route::get('chat-heads', [ApiResurceController::class, 'chat_heads']);
Route::get('chat-messages', [ApiResurceController::class, 'chat_messages']);
Route::get("users/me", [ApiResurceController::class, "my_profile"])->middleware(EnsureTokenIsValid::class);
Route::get("manifest", [ApiResurceController::class, "manifest"]);
Route::get("live-search", [ApiResurceController::class, "live_search"]);
Route::get("search-history", [ApiResurceController::class, "search_history"]);
Route::post("search-history/clear", [ApiResurceController::class, "clear_search_history"]);
Route::POST("users/login", [ApiAuthController::class, "login"]);
Route::POST("users/register", [ApiAuthController::class, "register"]);

// ========================================
// VSLA ONBOARDING ROUTES
// ========================================
use App\Http\Controllers\VslaOnboardingController;

Route::prefix('vsla-onboarding')->group(function () {
    // Public routes (no authentication required)
    Route::get('/config', [VslaOnboardingController::class, 'getOnboardingConfig']);
    Route::post('/register-admin', [VslaOnboardingController::class, 'registerGroupAdmin']);
    
    // Protected routes (authentication required)
    Route::middleware(EnsureTokenIsValid::class)->group(function () {
        Route::get('/status', [VslaOnboardingController::class, 'getOnboardingStatus']);
        Route::post('/create-group', [VslaOnboardingController::class, 'createVslaGroup']);
        Route::post('/register-main-members', [VslaOnboardingController::class, 'registerMainMembers']);
        Route::post('/create-cycle', [VslaOnboardingController::class, 'createSavingsCycle']);
        Route::post('/complete', [VslaOnboardingController::class, 'completeOnboarding']);
    });
});

// ========================================
// VSLA TRANSACTION ROUTES (Double-Entry Accounting System)
// ========================================
use App\Http\Controllers\Api\VslaTransactionController;

Route::prefix('vsla/transactions')->middleware(EnsureTokenIsValid::class)->group(function () {
    // Transaction Creation Endpoints
    Route::post('/saving', [VslaTransactionController::class, 'recordSaving']);
    Route::post('/loan-disbursement', [VslaTransactionController::class, 'disburseLoan']);
    Route::post('/loan-repayment', [VslaTransactionController::class, 'recordLoanRepayment']);
    Route::post('/fine', [VslaTransactionController::class, 'recordFine']);
    
    // Balance & Statement Endpoints
    Route::get('/member-balance/{user_id}', [VslaTransactionController::class, 'getMemberBalance']);
    Route::get('/group-balance/{group_id}', [VslaTransactionController::class, 'getGroupBalance']);
    Route::get('/member-statement', [VslaTransactionController::class, 'getMemberStatement']);
    Route::get('/group-statement', [VslaTransactionController::class, 'getGroupStatement']);
    
    // Dashboard & Reporting Endpoints
    Route::get('/recent', [VslaTransactionController::class, 'getRecentTransactions']);
    Route::get('/dashboard-summary', [VslaTransactionController::class, 'getDashboardSummary']);
});

// FFS Groups Management
use App\Http\Controllers\FfsGroupController;

Route::prefix('ffs-groups')->group(function () {
    Route::get('/', [FfsGroupController::class, 'index']); // List all groups with filtering
    Route::get('/form-options', [FfsGroupController::class, 'getFormOptions']); // Get dropdown options
    Route::get('/subcounties/{districtId}', [FfsGroupController::class, 'getSubcounties']); // Get subcounties
    Route::get('/parishes/{subcountyId}', [FfsGroupController::class, 'getParishes']); // Get parishes
    Route::get('/{id}', [FfsGroupController::class, 'show']); // Get single group
    Route::post('/', [FfsGroupController::class, 'store'])->middleware(EnsureTokenIsValid::class); // Create new group
});

// Members Management
use App\Http\Controllers\MemberController;

Route::prefix('members')->group(function () {
    Route::get('/', [MemberController::class, 'index']); // List all members with filtering
    Route::get('/{id}', [MemberController::class, 'show']); // Get single member
    Route::post('/', [MemberController::class, 'store'])->middleware(EnsureTokenIsValid::class); // Register new member
    Route::post('/{id}/send-credentials', [MemberController::class, 'sendCredentials'])->middleware(EnsureTokenIsValid::class); // Send credentials SMS
    Route::post('/{id}/send-welcome', [MemberController::class, 'sendWelcomeMessage'])->middleware(EnsureTokenIsValid::class); // Send welcome SMS
});

// Locations (Districts, Subcounties, Parishes)
use App\Http\Controllers\LocationController;

Route::prefix('locations')->group(function () {
    Route::get('/districts', [LocationController::class, 'getDistricts']); // Get all districts
    Route::get('/subcounties/{districtId}', [LocationController::class, 'getSubcounties']); // Get subcounties by district
    Route::get('/parishes/{subcountyId}', [LocationController::class, 'getParishes']); // Get parishes by subcounty
});

// Wishlist routes
Route::get('wishlist_get', [ApiResurceController::class, 'wishlist_get']);
Route::post('wishlist_add', [ApiResurceController::class, 'wishlist_add']);
Route::post('wishlist_remove', [ApiResurceController::class, 'wishlist_remove']);
Route::post('wishlist_check', [ApiResurceController::class, 'wishlist_check']);

// Review routes
use App\Http\Controllers\Api\ReviewController;

Route::prefix('reviews')->group(function () {
    Route::get('/', [ReviewController::class, 'index']); // Get reviews for a product
    Route::get('/stats', [ReviewController::class, 'stats']); // Get review statistics
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ReviewController::class, 'store']); // Create review
        Route::get('/user-review', [ReviewController::class, 'userReview']); // Get user's review
        Route::get('/{review}', [ReviewController::class, 'show']); // Get specific review
        Route::put('/{review}', [ReviewController::class, 'update']); // Update review
        Route::delete('/{review}', [ReviewController::class, 'destroy']); // Delete review
    });
});

// Pesapal Payment Gateway Routes
Route::prefix('pesapal')->group(function () {
    Route::post('/initialize', [PesapalController::class, 'initialize']);
    Route::get('/callback', [PesapalController::class, 'callback']);
    Route::post('/callback', [PesapalController::class, 'callback']); // Support both GET and POST callbacks
    Route::post('/ipn', [PesapalController::class, 'ipn'])->middleware('verify.pesapal.webhook');
    Route::get('/status/{orderId}', [PesapalController::class, 'status']);
    Route::get('/check-pesapal-status/{orderId}', [PesapalController::class, 'status']);
    Route::post('/register-ipn', [PesapalController::class, 'registerIpn']);
    Route::get('/config', [PesapalController::class, 'config']); // New: Configuration endpoint
    Route::post('/test', [PesapalController::class, 'test']); // New: Test connectivity
});

// Pesapal Admin Routes (require authentication)
Route::prefix('admin/pesapal')->middleware(EnsureTokenIsValid::class)->group(function () {
    Route::get('/analytics', [PesapalAdminController::class, 'analytics']);
    Route::get('/transaction/{id}', [PesapalAdminController::class, 'transactionDetails']);
    Route::get('/failed-transactions', [PesapalAdminController::class, 'failedTransactions']);
    Route::post('/retry/{id}', [PesapalAdminController::class, 'retryTransaction']);
    Route::get('/export', [PesapalAdminController::class, 'exportTransactions']);
});

// ========================================
// UNIVERSAL PAYMENT SYSTEM ROUTES
// ========================================
use App\Http\Controllers\UniversalPaymentController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentTransactionController;

Route::prefix('universal-payments')->group(function () {
    // Initialize payment with gateway (creates payment + initializes Pesapal/Stripe etc)
    Route::post('/initialize', [UniversalPaymentController::class, 'initialize']);

    // Payment callbacks (from Pesapal, Stripe, etc)
    Route::get('/callback', [UniversalPaymentController::class, 'handleCallback']);
    Route::post('/callback', [UniversalPaymentController::class, 'handleCallback']);

    // IPN webhook (Instant Payment Notification)
    Route::post('/ipn', [UniversalPaymentController::class, 'handleIPN']);

    // Check payment status
    Route::get('/status/{id}', [UniversalPaymentController::class, 'checkStatus']);

    // Get all payments (with filters)
    Route::get('/', [UniversalPaymentController::class, 'index']);

    // Get single payment
    Route::get('/{id}', [UniversalPaymentController::class, 'show']);

    // Manually process payment items (for admin/testing)
    Route::post('/{id}/process', [UniversalPaymentController::class, 'processItems']);
});

// ========================================
// Investment Management Routes
// ========================================
Route::prefix('investments')->group(function () {
    // Dashboard with summary, portfolio breakdown, and performance metrics
    Route::get('/dashboard', [InvestmentController::class, 'getDashboard']);

    // User's project shares
    Route::get('/shares', [InvestmentController::class, 'getMyShares']);

    // Share details
    Route::get('/shares/{id}', [InvestmentController::class, 'getShareDetails']);

    // User's project transactions
    Route::get('/transactions', [InvestmentController::class, 'getMyTransactions']);
});

// ========================================
// Investment Transaction CRUD Routes (Admin)
// ========================================
Route::prefix('investment-transactions')->group(function () {
    // Get list with filtering, sorting, pagination
    Route::get('/', [InvestmentTransactionController::class, 'index']);

    // Get projects dropdown
    Route::get('/projects', [InvestmentTransactionController::class, 'getProjects']);

    // Get single transaction
    Route::get('/{id}', [InvestmentTransactionController::class, 'show']);

    // Create new transaction (Profit or Expense)
    Route::post('/', [InvestmentTransactionController::class, 'store']);

    // Update transaction
    Route::put('/{id}', [InvestmentTransactionController::class, 'update']);

    // Delete transaction
    Route::delete('/{id}', [InvestmentTransactionController::class, 'destroy']);
});

// ========================================
// Disbursement CRUD Routes (Admin)
// ========================================
Route::prefix('disbursements')->group(function () {
    // Get list with filtering, sorting, pagination
    Route::get('/', [DisbursementController::class, 'index']);

    // Get projects dropdown (MUST be before /{id} route)
    Route::get('/projects', [DisbursementController::class, 'getProjects']);

    // Get single disbursement with investor details
    Route::get('/{id}', [DisbursementController::class, 'show']);

    // Create new disbursement (auto-distributes to investors)
    Route::post('/', [DisbursementController::class, 'store']);

    // Update disbursement (limited fields)
    Route::put('/{id}', [DisbursementController::class, 'update']);

    // Delete disbursement
    Route::delete('/{id}', [DisbursementController::class, 'destroy']);
});

// ========================================
// Account Transaction CRUD Routes
// ========================================
Route::prefix('account-transactions')->group(function () {
    // Get list with filtering, sorting, pagination
    Route::get('/', [AccountTransactionController::class, 'index']);

    // Get single account transaction
    Route::get('/{id}', [AccountTransactionController::class, 'show']);

    // Create new account transaction (withdrawal/deposit)
    Route::post('/', [AccountTransactionController::class, 'store']);

    // Delete account transaction (manual only)
    Route::delete('/{id}', [AccountTransactionController::class, 'destroy']);
});

// ========================================
// Withdraw Request Routes
// ========================================
use App\Http\Controllers\WithdrawRequestController;

Route::prefix('withdraw-requests')->group(function () {
    // Get user's balance and withdrawal summary
    Route::get('/balance', [WithdrawRequestController::class, 'getBalance']);

    // Get list of user's withdraw requests
    Route::get('/', [WithdrawRequestController::class, 'index']);

    // Get single withdraw request
    Route::get('/{id}', [WithdrawRequestController::class, 'show']);

    // Create new withdraw request
    Route::post('/', [WithdrawRequestController::class, 'store']);

    // Cancel pending withdraw request
    Route::delete('/{id}', [WithdrawRequestController::class, 'cancel']);
});

// ========================================
// User Account Dashboard Routes
// ========================================
Route::prefix('user-accounts')->group(function () {
    // Get logged-in user's account dashboard
    Route::get('/dashboard', [UserAccountController::class, 'getUserAccountDashboard']);

    // Get all users with balances (admin only)
    Route::get('/users-list', [UserAccountController::class, 'getAllUsersWithBalances']);

    // Get specific user's dashboard (admin viewing)
    Route::get('/user-dashboard/{userId}', [UserAccountController::class, 'getUserDashboard']);
});

// ========================================
// Main Dashboard Routes
// ========================================
use App\Http\Controllers\DashboardController;

Route::prefix('dashboard')->group(function () {
    // Get main dashboard data (home tab)
    Route::get('/main', [DashboardController::class, 'getMainDashboard']);

    // Get pending insurance transactions
    Route::get('/pending-insurance-transactions', [DashboardController::class, 'getPendingInsuranceTransactions']);

    // Get insurance overview (insurance tab)
    Route::get('/insurance-overview', [DashboardController::class, 'getInsuranceOverview']);

    // Get investments overview (investments tab)
    Route::get('/investments-overview', [DashboardController::class, 'getInvestmentsOverview']);

    // Get my account overview (my account tab)
    Route::get('/my-account-overview', [DashboardController::class, 'getMyAccountOverview']);
    
    // Debug endpoint for projects
    Route::get('/debug-projects', function() {
        $projects = App\Models\Project::all(['id', 'title', 'status', 'total_shares', 'shares_sold']);
        return response()->json([
            'total' => $projects->count(),
            'projects' => $projects
        ]);
    });
    
    // Fix project shares endpoint (one-time use)
    Route::post('/fix-project-shares', function() {
        $updates = [
            'Medicine Distribution Partnership' => 20,
            'Farm-to-Profit Initiative' => 20,
            'Property Wealth Builder' => 50000,
            'Motorcycle Taxi Fleet' => 200,
        ];
        
        $results = [];
        DB::beginTransaction();
        
        try {
            foreach ($updates as $title => $totalShares) {
                $project = App\Models\Project::where('title', $title)->first();
                
                if ($project) {
                    $oldShares = $project->total_shares;
                    $project->total_shares = $totalShares;
                    $project->save();
                    
                    $results[] = [
                        'title' => $project->title,
                        'old_shares' => $oldShares,
                        'new_shares' => $totalShares,
                        'status' => 'updated'
                    ];
                } else {
                    $results[] = [
                        'title' => $title,
                        'status' => 'not_found'
                    ];
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Projects updated successfully',
                'results' => $results,
                'all_projects' => App\Models\Project::all(['id', 'title', 'status', 'total_shares', 'shares_sold'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

// ========================================
// INSURANCE MODULE ROUTES
// ========================================

// Insurance Users (System Users with user_type = 'Customer')
// Using ApiResurceController since InsuranceUserController was eliminated
Route::get('insurance-users', [ApiResurceController::class, 'insurance_users']); // List all insurance users (customers)
Route::get('insurance-users/{id}', [ApiResurceController::class, 'insurance_user_show']); // Get single insurance user by ID
Route::put('insurance-users/{id}', [ApiResurceController::class, 'insurance_user_update']); // Update insurance user
Route::patch('insurance-users/{id}', [ApiResurceController::class, 'insurance_user_update']); // Update insurance user (alternative)
Route::post('insurance-users/{id}', [ApiResurceController::class, 'insurance_user_update']); // Update via POST with _method
Route::delete('insurance-users/{id}', [ApiResurceController::class, 'insurance_user_delete']); // Delete insurance user

// Membership Payment Routes
Route::post('membership-payment', [ApiResurceController::class, 'membership_payment_create']); // Initiate membership payment
Route::get('membership-status', [ApiResurceController::class, 'membership_status']); // Check user's membership status
Route::get('membership-check', [ApiResurceController::class, 'membership_check']); // Safe check without billing (read-only)
Route::post('membership-payment/confirm', [ApiResurceController::class, 'membership_payment_confirm']); // Confirm payment
Route::get('membership-benefits', [ApiResurceController::class, 'membership_benefits']); // Get membership benefits
Route::get('membership-payments', [ApiResurceController::class, 'membership_payments_list']); // List user's membership payments

// Transactions (Savings/Withdrawals) CRUD Routes
Route::prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']); // List all transactions
    Route::get('/stats', [TransactionController::class, 'stats']); // Get statistics
    Route::get('/user/{userId}/balance', [TransactionController::class, 'getUserBalance']); // Get user balance
    Route::get('/{id}', [TransactionController::class, 'show']); // Get single transaction
    Route::post('/', [TransactionController::class, 'store']); // Create new transaction
    Route::put('/{id}', [TransactionController::class, 'update']); // Update transaction
    Route::patch('/{id}', [TransactionController::class, 'update']); // Update transaction (alternative)
    Route::delete('/{id}', [TransactionController::class, 'destroy']); // Delete transaction
    Route::post('/{id}', [TransactionController::class, 'update']); // Update via POST with _method
});

// Insurance Programs CRUD Routes
Route::prefix('insurance-programs')->group(function () {
    Route::get('/', [App\Http\Controllers\InsuranceProgramController::class, 'index']); // List all insurance programs
    Route::get('/stats', [App\Http\Controllers\InsuranceProgramController::class, 'stats']); // Get statistics
    Route::get('/{id}', [App\Http\Controllers\InsuranceProgramController::class, 'show']); // Get single insurance program
    Route::post('/', [App\Http\Controllers\InsuranceProgramController::class, 'store']); // Create new insurance program
    Route::put('/{id}', [App\Http\Controllers\InsuranceProgramController::class, 'update']); // Update insurance program
    Route::patch('/{id}', [App\Http\Controllers\InsuranceProgramController::class, 'update']); // Update insurance program (alternative)
    Route::delete('/{id}', [App\Http\Controllers\InsuranceProgramController::class, 'destroy']); // Delete insurance program
    Route::post('/{id}', [App\Http\Controllers\InsuranceProgramController::class, 'update']); // Update via POST with _method
});

// Insurance Subscriptions CRUD Routes
Route::prefix('insurance-subscriptions')->group(function () {
    Route::get('/', [App\Http\Controllers\InsuranceSubscriptionController::class, 'index']); // List all insurance subscriptions
    Route::get('/user/{userId}', [App\Http\Controllers\InsuranceSubscriptionController::class, 'getUserSubscription']); // Get user's active subscription
    Route::get('/{id}', [App\Http\Controllers\InsuranceSubscriptionController::class, 'show']); // Get single insurance subscription
    Route::post('/', [App\Http\Controllers\InsuranceSubscriptionController::class, 'store']); // Create new insurance subscription
    Route::put('/{id}', [App\Http\Controllers\InsuranceSubscriptionController::class, 'update']); // Update insurance subscription
    Route::patch('/{id}', [App\Http\Controllers\InsuranceSubscriptionController::class, 'update']); // Update insurance subscription (alternative)
    Route::post('/{id}/suspend', [App\Http\Controllers\InsuranceSubscriptionController::class, 'suspend']); // Suspend subscription
    Route::post('/{id}/activate', [App\Http\Controllers\InsuranceSubscriptionController::class, 'activate']); // Activate suspended subscription
    Route::post('/{id}/cancel', [App\Http\Controllers\InsuranceSubscriptionController::class, 'cancel']); // Cancel subscription
    Route::delete('/{id}', [App\Http\Controllers\InsuranceSubscriptionController::class, 'destroy']); // Delete insurance subscription
    Route::post('/{id}', [App\Http\Controllers\InsuranceSubscriptionController::class, 'update']); // Update via POST with _method
});

// Insurance Subscription Payments CRUD Routes
Route::prefix('insurance-subscription-payments')->group(function () {
    Route::get('/', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'index']); // List all insurance subscription payments
    Route::get('/stats', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'stats']); // Get statistics
    Route::get('/overdue', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'getOverdue']); // Get overdue payments
    Route::get('/user/{userId}', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'getUserPayments']); // Get user payments
    Route::get('/{id}', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'show']); // Get single insurance subscription payment
    Route::put('/{id}', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'update']); // Update insurance subscription payment
    Route::patch('/{id}', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'update']); // Update insurance subscription payment (alternative)
    Route::post('/{id}/pay', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'markAsPaid']); // Mark payment as paid
    Route::post('/{id}', [App\Http\Controllers\InsuranceSubscriptionPaymentController::class, 'update']); // Update via POST with _method
});

// ========================================
// MEDICAL SERVICE REQUEST ROUTES
// ========================================
use App\Http\Controllers\MedicalServiceRequestController;

Route::prefix('medical-service-requests')->group(function () {
    // List all requests (with filters)
    Route::get('/', [MedicalServiceRequestController::class, 'index']);

    // Get statistics
    Route::get('/stats', [MedicalServiceRequestController::class, 'stats']);

    // Get user's requests
    Route::get('/user/{userId}', [MedicalServiceRequestController::class, 'getUserRequests']);

    // Get by reference number
    Route::get('/reference/{reference}', [MedicalServiceRequestController::class, 'getByReference']);

    // Get single request
    Route::get('/{id}', [MedicalServiceRequestController::class, 'show']);

    // Create new request
    Route::post('/', [MedicalServiceRequestController::class, 'store']);

    // Update request (user - only if pending)
    Route::put('/{id}', [MedicalServiceRequestController::class, 'update']);
    Route::patch('/{id}', [MedicalServiceRequestController::class, 'update']);

    // Review/Approve/Reject request (admin)
    Route::post('/{id}/review', [MedicalServiceRequestController::class, 'review']);

    // Cancel request (user)
    Route::post('/{id}/cancel', [MedicalServiceRequestController::class, 'cancel']);

    // Delete request
    Route::delete('/{id}', [MedicalServiceRequestController::class, 'destroy']);
});


// Route::get('manifest', [ApiAuthController::class, 'manifest']); // Commented out - using ApiResurceController instead


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('ajax', function (Request $r) {

    $_model = trim($r->get('model'));
    $conditions = [];
    foreach ($_GET as $key => $v) {
        if (substr($key, 0, 6) != 'query_') {
            continue;
        }
        $_key = str_replace('query_', "", $key);
        $conditions[$_key] = $v;
    }

    if (strlen($_model) < 2) {
        return [
            'data' => []
        ];
    }

    $model = "App\Models\\" . $_model;
    $search_by_1 = trim($r->get('search_by_1'));
    $search_by_2 = trim($r->get('search_by_2'));

    $q = trim($r->get('q'));

    $res_1 = $model::where(
        $search_by_1,
        'like',
        "%$q%"
    )
        ->where($conditions)
        ->limit(20)->get();
    $res_2 = [];

    if ((count($res_1) < 20) && (strlen($search_by_2) > 1)) {
        $res_2 = $model::where(
            $search_by_2,
            'like',
            "%$q%"
        )
            ->where($conditions)
            ->limit(20)->get();
    }

    $data = [];
    foreach ($res_1 as $key => $v) {
        $name = "";
        if (isset($v->name)) {
            $name = " - " . $v->name;
        }
        $data[] = [
            'id' => $v->id,
            'text' => "#$v->id" . $name
        ];
    }
    foreach ($res_2 as $key => $v) {
        $name = "";
        if (isset($v->name)) {
            $name = " - " . $v->name;
        }
        $data[] = [
            'id' => $v->id,
            'text' => "#$v->id" . $name
        ];
    }

    return [
        'data' => $data
    ];
});

// Projects Management API Routes
Route::prefix('projects')->middleware(EnsureTokenIsValid::class)->group(function () {
    // Project CRUD
    Route::get('/', [App\Http\Controllers\ProjectController::class, 'index']);
    Route::post('/', [App\Http\Controllers\ProjectController::class, 'store']);
    Route::get('/{id}', [App\Http\Controllers\ProjectController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\ProjectController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\ProjectController::class, 'destroy']);
    Route::get('/{id}/details', [App\Http\Controllers\ProjectController::class, 'getDetails']);

    // Project Transactions
    Route::get('/transactions', [App\Http\Controllers\ProjectTransactionController::class, 'index']);
    Route::post('/transactions', [App\Http\Controllers\ProjectTransactionController::class, 'store']);
    Route::get('/transactions/{id}', [App\Http\Controllers\ProjectTransactionController::class, 'show']);
    Route::put('/transactions/{id}', [App\Http\Controllers\ProjectTransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [App\Http\Controllers\ProjectTransactionController::class, 'destroy']);

    // Project Shares
    Route::get('/shares/my-shares', [App\Http\Controllers\ProjectShareController::class, 'getUserShares']);
    Route::post('/shares/initiate-purchase', [App\Http\Controllers\ProjectShareController::class, 'initiatePurchase']);
    Route::get('/shares/{id}', [App\Http\Controllers\ProjectShareController::class, 'show']);
});


Route::get('api/{model}', [ApiResurceController::class, 'index']);
Route::post('api/{model}', [ApiResurceController::class, 'update']);

// Ajax endpoints for Laravel Admin select widgets
Route::get('ajax/products', function (Request $request) {
    $q = $request->get('q');
    
    return \App\Models\Product::where('name', 'like', "%$q%")
        ->orWhere('local_id', 'like', "%$q%")
        ->select('id', 'name', 'price_1', 'local_id')
        ->limit(20)
        ->get()
        ->map(function ($product) {
            $price = number_format($product->price_1, 0);
            return [
                'id' => $product->id,
                'text' => "{$product->name} (UGX {$price})"
            ];
        });
});

Route::get('ajax/orders', function (Request $request) {
    $q = $request->get('q');
    
    return \App\Models\Order::where('receipt_number', 'like', "%$q%")
        ->orWhere('id', 'like', "%$q%")
        ->select('id', 'receipt_number', 'customer_name')
        ->limit(20)
        ->get()
        ->map(function ($order) {
            $receipt = $order->receipt_number ?? "Order #{$order->id}";
            $customer = $order->customer_name ? " - {$order->customer_name}" : "";
            return [
                'id' => $order->id,
                'text' => "{$receipt}{$customer}"
            ];
        });
});

Route::get('ajax/product-details/{id}', function ($id) {
    $product = \App\Models\Product::find($id);
    
    if (!$product) {
        return response()->json(['error' => 'Product not found'], 404);
    }
    
    return response()->json([
        'id' => $product->id,
        'name' => $product->name,
        'price_1' => $product->price_1,
        'local_id' => $product->local_id,
        'colors' => $product->colors,
        'sizes' => $product->sizes,
    ]);
});

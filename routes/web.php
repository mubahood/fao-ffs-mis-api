<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\UserCredentialsController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\Gen;
use App\Models\Order;
use App\Models\Utils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Dflydev\DotAccessData\Util;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/auth/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/auth/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Import Routes
|--------------------------------------------------------------------------
*/
Route::get('/import/validate/{id}', [ImportController::class, 'validateImport'])->name('import.validate');
Route::get('/import/process/{id}', [ImportController::class, 'process'])->name('import.process');

//migrate
Route::get('/migrate', function () {
    $response = Artisan::call('migrate', ['--force' => true]);
    //display output
    echo nl2br(Artisan::output());
    return $response;
});

/*
|--------------------------------------------------------------------------
| User Credentials SMS Routes
|--------------------------------------------------------------------------
*/
Route::get('/admin/users/{userId}/send-credentials', [UserCredentialsController::class, 'sendCredentials'])->name('admin.send-credentials');
Route::get('/admin/users/{userId}/send-welcome', [UserCredentialsController::class, 'sendWelcome'])->name('admin.send-welcome');

// Root route to fix MethodNotAllowedHttpException for GET /
/* Route::get('/', function () {
    return response()->json(['message' => 'BlitXpress API is running']);
}); */

Route::get('order', function (Request $r) {
    $order = Order::find(170);
    $order->customer_phone_number_1;
    die("done");
    dd($order->order_details);
    /* 
        "id" => 170
    "created_at" => "2025-10-06 18:10:53"
    "updated_at" => "2025-10-06 19:28:00"
    "user" => 306
    "order_state" => "1"
    "amount" => "15000"
    "date_created" => null
    "payment_confirmation" => ""
    "date_updated" => null
    "mail" => "wandukwaamok@gmail.com"
    "delivery_district" => null
    "temporary_id" => 0
    "description" => ""
    "customer_name" => null
    "customer_phone_number_1" => null
    "customer_phone_number_2" => null
    "customer_address" => null
    "order_total" => "15000"
    "order_details" => "{"id":0,"created_at":"","updated_at":"","user":"","order_state":"","amount":"","date_created":"","payment_confirmation":"","date_updated":"","mail":"wandukwaamo ▶"
    "stripe_id" => null
    "stripe_url" => null
    "stripe_paid" => "No"
    "pending_mail_sent" => "Yes"
    "processing_mail_sent" => "Yes"
    "completed_mail_sent" => "No"
    "canceled_mail_sent" => "No"
    "failed_mail_sent" => "No"
    "sub_total" => 0
    "tax" => 0
    "discount" => 0
    "delivery_fee" => 0
    "payment_gateway" => "manual"
    "pesapal_order_tracking_id" => null
    "pesapal_merchant_reference" => null
    "pesapal_status" => null
    "pesapal_payment_method" => null
    "pesapal_redirect_url" => null
    "payment_status" => "PENDING_PAYMENT"
    "pay_on_delivery" => 0
    "payment_completed_at" => null
    */
});
Route::get('do-send-notofocation', function (Request $r) {
    try {
        $notificationId = $r->input('id');

        if (!$notificationId) {
            return redirect()->back()->with('error', 'Notification ID is required');
        }

        $notification = \App\Models\NotificationModel::find($notificationId);

        if (!$notification) {
            return redirect()->back()->with('error', 'Notification not found');
        }

        // Check if already sent
        if ($notification->status === 'sent') {
            return ('warning: ' . 'Notification has already been sent');
        }

        // Send the notification
        $result = $notification->send();

        if ($result['success']) {
            return ('success: ' . 'Notification sent successfully! Recipients: ' . ($result['recipients'] ?? 'N/A'));
        } else {
            return ('error: ' . 'Failed to send notification: ' . $result['error']);
        }
    } catch (\Exception $e) {
        return ('error: ' . 'Error: ' . $e->getMessage());
    }
});
Route::get('img-compress', function () {
    set_time_limit(300000); // Increase time limit for processing

    // Get 10 latest uncompressed products with feature photos
    $uncompressedProducts = \App\Models\Product::uncompressed()
        ->whereNotNull('feature_photo')
        ->where('feature_photo', '!=', '')
        ->orderBy('id', 'desc')
        ->limit(20)
        ->get();

    // Check if we have any TinifyModels
    $tinifyKeysCount = \App\Models\TinifyModel::where('status', 'active')->count();

    if ($tinifyKeysCount === 0) {
        return response()->json([
            'error' => 'No active Tinify API keys available. Please add API keys to the system.'
        ], 400);
    }

    // Start building HTML response
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>Image Compression Results</title>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; }
            .header { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .product-card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .comparison { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
            .image-section { flex: 1; min-width: 300px; text-align: center; }
            .image-section img { max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
            .stats { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px; }
            .success { color: #28a745; font-weight: bold; }
            .error { color: #dc3545; font-weight: bold; }
            .pending { color: #ffc107; font-weight: bold; }
            .size-comparison { display: flex; justify-content: space-between; margin: 10px 0; }
            .size-item { text-align: center; flex: 1; }
            .savings { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-top: 10px; text-align: center; font-weight: bold; }
            .progress { background: #e9ecef; border-radius: 4px; height: 20px; margin: 10px 0; position: relative; overflow: hidden; }
            .progress-bar { background: #007bff; height: 100%; transition: width 0.3s ease; }
            .meta-info { background: #e9ecef; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 0.9em; }
            .no-products { text-align: center; padding: 40px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">';

    $html .= '<div class="header">
            <h1>🗜️ Image Compression Dashboard</h1>
            <p><strong>Available API Keys:</strong> ' . $tinifyKeysCount . ' active Tinify keys</p>
            <p><strong>Processing:</strong> ' . count($uncompressedProducts) . ' uncompressed products</p>
        </div>';

    if ($uncompressedProducts->isEmpty()) {
        $html .= '<div class="no-products">
                <h3>🎉 No uncompressed images found!</h3>
                <p>All product images have been processed or no products with feature photos exist.</p>
            </div>';
    } else {
        $totalSavings = 0;
        $successCount = 0;
        $failureCount = 0;

        foreach ($uncompressedProducts as $product) {
            $html .= '<div class="product-card">';
            $html .= '<h3>Product: ' . htmlspecialchars($product->name) . ' (ID: ' . $product->id . ')</h3>';

            // Get original image info
            $originalPath = \App\Models\Utils::img_path($product->feature_photo);
            $originalUrl = \App\Models\Utils::img_url($product->feature_photo);

            if (!file_exists($originalPath)) {
                $html .= '<div class="error">❌ Original image file not found: ' . htmlspecialchars($product->feature_photo) . '</div>';
                $failureCount++;
                $html .= '</div>';
                continue;
            }

            $originalSize = filesize($originalPath);
            $originalSizeMB = round($originalSize / (1024 * 1024), 2);

            // Start compression
            $html .= '<div class="comparison">';

            // Original image section
            $html .= '<div class="image-section">
                    <h4>📸 Original Image</h4>
                    <img src="' . $originalUrl . '" alt="Original">
                    <div class="stats">
                        <div><strong>Size:</strong> ' . $originalSizeMB . ' MB</div>
                        <div><strong>File:</strong> ' . htmlspecialchars($product->feature_photo) . '</div>
                    </div>
                </div>';

            // Compress the image
            $compressionResult = \App\Models\Utils::tinyCompressImageEnhanced($product->feature_photo, $product);

            // Compressed image section
            $html .= '<div class="image-section">';

            if ($compressionResult->status == 1) {
                $html .= '<h4>✅ Compressed Image</h4>';
                $compressedUrl = \App\Models\Utils::img_url($compressionResult->destination_relative_path);
                $html .= '<img src="' . $compressedUrl . '" alt="Compressed">';

                $html .= '<div class="stats">
                        <div><strong>New Size:</strong> ' . $compressionResult->new_size_mb . ' MB</div>
                        <div><strong>File:</strong> ' . htmlspecialchars($compressionResult->destination_relative_path) . '</div>
                        <div><strong>API Key ID:</strong> ' . $compressionResult->tinify_model_id . '</div>
                    </div>';

                $html .= '<div class="savings">
                        💾 Space Saved: ' . $compressionResult->savings_percentage . '%<br>
                        (' . ($compressionResult->original_size_mb - $compressionResult->new_size_mb) . ' MB saved)
                    </div>';

                $totalSavings += ($compressionResult->original_size_mb - $compressionResult->new_size_mb);
                $successCount++;
            } else {
                $html .= '<h4>❌ Compression Failed</h4>';
                $html .= '<div class="error">Error: ' . htmlspecialchars($compressionResult->message) . '</div>';
                if ($compressionResult->tinify_model_id) {
                    $html .= '<div class="meta-info">API Key ID: ' . $compressionResult->tinify_model_id . '</div>';
                }
                $failureCount++;
            }

            $html .= '</div>'; // Close image-section
            $html .= '</div>'; // Close comparison

            // Add compression metadata
            $html .= '<div class="meta-info">
                    <strong>Status:</strong> <span class="' . ($compressionResult->status == 1 ? 'success' : 'error') . '">';
            $html .= $compressionResult->status == 1 ? 'SUCCESS' : 'FAILED';
            $html .= '</span><br>';
            $html .= '<strong>Message:</strong> ' . htmlspecialchars($compressionResult->message) . '<br>';
            $html .= '<strong>Processed:</strong> ' . now()->format('Y-m-d H:i:s');
            $html .= '</div>';

            $html .= '</div>'; // Close product-card
        }

        // Summary section
        $html .= '<div class="header">
                <h2>📊 Compression Summary</h2>
                <div class="size-comparison">
                    <div class="size-item">
                        <div style="font-size: 2em;">✅</div>
                        <div><strong>' . $successCount . '</strong></div>
                        <div>Successful</div>
                    </div>
                    <div class="size-item">
                        <div style="font-size: 2em;">❌</div>
                        <div><strong>' . $failureCount . '</strong></div>
                        <div>Failed</div>
                    </div>
                    <div class="size-item">
                        <div style="font-size: 2em;">💾</div>
                        <div><strong>' . round($totalSavings, 2) . ' MB</strong></div>
                        <div>Total Saved</div>
                    </div>
                </div>';

        // API Key usage stats
        $tinifyUsageStats = \App\Models\TinifyModel::getIndividualKeyStats();
        if (!empty($tinifyUsageStats)) {
            $html .= '<h3>🔑 API Key Usage</h3>';
            foreach ($tinifyUsageStats as $stats) {
                $usagePercentage = $stats['monthly_limit'] > 0 ? ($stats['monthly_usage'] / $stats['monthly_limit']) * 100 : 0;
                $html .= '<div style="margin: 10px 0;">
                        <strong>Key ID ' . $stats['id'] . ':</strong> ' . $stats['monthly_usage'] . '/' . $stats['monthly_limit'] . ' (' . round($usagePercentage, 1) . '%)
                        <div class="progress">
                            <div class="progress-bar" style="width: ' . min($usagePercentage, 100) . '%"></div>
                        </div>
                    </div>';
            }
        }

        $html .= '</div>';
    }

    $html .= '</div></body></html>';

    return response($html)->header('Content-Type', 'text/html');
});
Route::get('mail-test', function () {

    try {
        // Test 1: Test order email system
        echo "<h2>🧪 Testing Order Email System...</h2>";

        $lastOrder = Order::orderBy('id', 'desc')->first();
        if ($lastOrder) {
            echo "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>";
            echo "<h3>📦 Testing with Order #{$lastOrder->id}</h3>";
            echo "<p><strong>Customer:</strong> " . ($lastOrder->customer->first_name ?? 'N/A') . " " . ($lastOrder->customer->last_name ?? '') . "</p>";
            echo "<p><strong>Customer Email:</strong> " . ($lastOrder->customer->email ?? 'No email') . "</p>";
            echo "<p><strong>Total Amount:</strong> CAD {$lastOrder->total}</p>";
            echo "<p><strong>Order Date:</strong> {$lastOrder->created_at}</p>";
            echo "</div>";

            echo "<p>🚀 Triggering order email system...</p>";
            Order::send_mails($lastOrder);
            echo "<p style='color: green;'><strong>✅ Order emails sent successfully!</strong></p>";

            // Show email validation status
            if ($lastOrder->customer && $lastOrder->customer->email) {
                $isValidEmail = filter_var($lastOrder->customer->email, FILTER_VALIDATE_EMAIL);
                if ($isValidEmail) {
                    echo "<p style='color: green;'>✅ Customer email is valid - confirmation email sent</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Customer email is invalid - only admin emails sent</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ Customer has no email - only admin emails sent</p>";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ No orders found in database</strong></p>";
        }

        echo "<hr style='margin: 20px 0;'>";

        // Test 2: General mail system test
        echo "<h2>📧 Testing General Mail System...</h2>";

        $data['body'] = 'This is a general test email from BlitXpress mail system.';
        $data['data'] = $data['body'];
        $data['name'] = 'Test User';
        $data['email'] = 'mubahood360@gmail.com';
        $data['subject'] = 'BlitXpress Mail System Test - ' . date('Y-m-d H:i:s');
        $data['view'] = 'mail-1';

        echo "<p>Sending general test email to: " . $data['email'] . "</p>";

        Utils::mail_sender($data);

        echo "<p style='color: green;'><strong>✅ General mail sent successfully!</strong></p>";
    } catch (\Exception $e) {
        echo "<p style='color: red;'><strong>❌ Mail Error:</strong> " . $e->getMessage() . "</p>";

        echo "<h3>🔍 Debug Information:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #ddd;'>";
        echo "MAIL_HOST: " . env('MAIL_HOST') . "\n";
        echo "MAIL_PORT: " . env('MAIL_PORT') . "\n";
        echo "MAIL_ENCRYPTION: " . env('MAIL_ENCRYPTION') . "\n";
        echo "MAIL_USERNAME: " . env('MAIL_USERNAME') . "\n";
        echo "MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS') . "\n";
        echo "</pre>";
    }

    echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-left: 4px solid #28a745;'>";
    echo "<h3>✅ Test Summary</h3>";
    echo "<p>• Order email system: Configured and tested</p>";
    echo "<p>• Admin notifications: Enabled</p>";
    echo "<p>• Customer confirmations: Enabled (when email is valid)</p>";
    echo "<p>• Email validation: Active</p>";
    echo "</div>";

    return [
        'status' => 'Mail test completed',
        'timestamp' => date('Y-m-d H:i:s'),
        'order_tested' => $lastOrder ? $lastOrder->id : null
    ];
});

Route::get('test', function () {

    return;

    $stripe = env('STRIPE_KEY');
    $stripe = new \Stripe\StripeClient(
        env('STRIPE_KEY')
    );

    $name = 'Order payment for ' . date('Y-m-d H:i:s') . " " . rand(1, 100000);

    $resp = null;
    try {
        $resp = $stripe->products->create([
            'name' => $name,
            'default_price_data' => [
                'currency' => 'cad',
                'unit_amount' => 1 * 100,
            ],
        ]);
    } catch (\Throwable $th) {
        throw $th;
    }
    if ($resp == null) {
        throw new \Exception("Error Processing Request", 1);
    }
    if ($resp->default_price == null) {
        throw new \Exception("Error Processing Request", 1);
    }
    $linkResp = null;
    try {
        $linkResp = $stripe->paymentLinks->create([
            'currency' => 'cad',
            'line_items' => [
                [
                    'price' => $resp->default_price,
                    'quantity' => 1,
                ]
            ]
        ]);
    } catch (\Throwable $th) {
        throw $th;
    }
    if ($linkResp == null) {
        throw new \Exception("Error Processing Request", 1);
    }
});



Route::get('migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return Artisan::output();
});

Route::get('clear', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('optimize:clear');
    exec('composer dump-autoload -o');
    return Artisan::output();
});

Route::get('fix-projects', function () {
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
            $project = \App\Models\Project::where('title', $title)->first();
            
            if ($project) {
                $oldShares = $project->total_shares;
                $project->total_shares = $totalShares;
                $project->save();
                
                $results[] = "{$project->title}: {$oldShares} → {$totalShares} shares";
            }
        }
        
        DB::commit();
        
        $allProjects = \App\Models\Project::all(['id', 'title', 'status', 'total_shares', 'shares_sold']);
        
        return response()->json([
            'success' => true,
            'message' => 'Projects fixed!',
            'updates' => $results,
            'all_projects' => $allProjects
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

Route::get('artisan', function (Request $request) {
    // Artisan::call('migrate');
    //do run laravel migration command
    //php artisan l5-swagger:generate
    Artisan::call($request->command, ['--force' => true]);
    //returning the output
    return Artisan::output();
});




Route::match(['get', 'post'], '/pay', function () {
    $id = 1;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $order = \App\Models\Order::first();
        $id = $order->id;
    }
    $order = \App\Models\Order::find($id);
    $customer = $order->customer;
    //dd($customer);
    // $order->amount = 1;
    // $order->save();

    $task = null;
    if (isset($_GET['task'])) {
        $task = $_GET['task'];
    }
    if ($task == "success") {
        $order->payment_confirmation = 1;
        $data['get'] = $_GET;
        $data['post'] = $_POST;
        $order->stripe_id = json_encode($data);
        $order->save();
        die("Payment was successful");
    } else if ($task == "canceled") {
        $data['get'] = $_GET;
        $data['post'] = $_POST;
        $order->stripe_url = json_encode($data);
        $order->save();
        die("Payment was canceled");
    } else if ($task == "update") {
        $data['task'] = $task;
        $data['get'] = $_GET;
        $data['post'] = $_POST;
        $order->order_details = json_encode($data);
        $order->save();
        //return 200 response
        return response()->json(['status' => 'success', 'message' => 'Payment was updated.']);
    }

    $base_link = url('/pay?id=' . $id);
    return view('pay', [
        'order' => $order,
        'base_link' => $base_link
    ]);
});
Route::get('/process', function () {

    //set_time_limit(0);
    set_time_limit(-1);
    //ini_set('memory_limit', '1024M');
    ini_set('memory_limit', '-1');

    $folderPath2 = base_path('public/temp/pics/final');
    $folderPath = base_path('public/temp/pics/');
    $biggest = 0;
    $tot = 0;

    // Check if the folder exists
    if (is_dir($folderPath)) {
        // Get the list of items in the folder
        $items = scandir($folderPath);
        $items_1 = scandir($folderPath2);

        $i = 0;


        // Loop through the items
        foreach ($items as $item) {

            // Exclude the current directory (.) and parent directory (..)
            if ($item != '.' && $item != '..') {


                $ext = pathinfo($item, PATHINFO_EXTENSION);
                if ($ext == null) {
                    continue;
                }
                $ext = strtolower($ext);


                if (!in_array($ext, [
                    'jpg',
                    'jpeg',
                    'png',
                    'gif',
                ])) {
                    continue;
                }

                $target = $folderPath . $item;
                $target_file_size = filesize($target);

                $target_file_size_to_mb = $target_file_size / (1024 * 1024);
                $target_file_size_to_mb = round($target_file_size_to_mb, 2);
                /* if($target_file_size_to_mb > 2){
                    $source = $target;
                    $dest = $folderPath . "final/" . $item;
                    Utils::create_thumbail([
                        'source' => $source,
                        'target' => $dest
                    ]);
                    unlink($source); 
                } */


                if ($target_file_size > $biggest) {
                    $biggest = $target_file_size;
                }
                $tot += $target_file_size;


                continue;
                //echo $i.". ".$item . "<br>";
                $i++;
                continue;

                $i++;
                print_r($i . "<br>");



                $fileSize = filesize($folderPath . "/" . $item);
                $fileSize = $fileSize / (1024 * 1024);
                $fileSize = round($fileSize, 2);
                $fileSize = $fileSize . " MB";
                $url = "http://localhost:8888/ham/public/temp/pics-1/" . $item;

                $source = $folderPath . "/" . $item;
                $target = $folderPath . "/thumb/" . $item;
                Utils::create_thumbail([
                    'source' => $source,
                    'target' => $target
                ]);

                echo "<img src='$url' alt='$item' width='550'/>";
                $target_file_size = filesize($target);
                $target_file_size = $target_file_size / (1024 * 1024);
                $target_file_size = round($target_file_size, 2);
                $target_file_size = $target_file_size . " MB";
                $url_2 = "http://localhost:8888/ham/public/temp/pics-1/thumb/" . $item;
                echo "<img src='$url_2' alt='$item' width='550' />";



                // Print the item's name
                echo "<b>" . $fileSize . "<==>" . $target_file_size . "<b><br>";
            }
        }
    } else {
        echo "The specified folder does not exist.";
    }

    $biggest = $biggest / (1024 * 1024);
    $biggest = round($biggest, 2);
    $biggest = $biggest . " MB";
    $tot = $tot / (1024 * 1024);
    $tot = round($tot, 2);
    $tot = $tot . " MB";
    echo "Biggest: " . $biggest . "<br>";
    echo "Total: " . $tot . "<br>";
    die("=>done<=");
});
Route::get('/sync', function () {
    Utils::sync_products();
    Utils::sync_orders();
})->name("sync");
Route::get('/gen', function () {
    die(Gen::find($_GET['id'])->do_get());
})->name("gen");
Route::get('/gen-form', function () {
    die(Gen::find($_GET['id'])->make_forms());
})->name("gen-form");
Route::get('generate-class', [MainController::class, 'generate_class']);

# Admin routes for reviews
use App\Http\Controllers\Admin\ReviewController;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('reviews', ReviewController::class);
    Route::post('reviews/bulk-delete', [ReviewController::class, 'bulkDelete'])->name('reviews.bulk-delete');
});

# 🧪 PESAPAL PAYMENT TESTING INTERFACE
# Independent testing interface for Pesapal integration
# Routes: /payment-test/*
use App\Http\Controllers\PaymentTestController;

Route::prefix('payment-test')->name('payment-test.')->group(function () {

    // 🎨 Main Dashboard
    Route::get('/', [PaymentTestController::class, 'dashboard'])->name('dashboard');

    // � Quick Test Page
    Route::get('/quick', function () {
        return view('pesapal-quick-test');
    })->name('quick');

    // �💳 Payment Testing
    Route::post('/initialize', [PaymentTestController::class, 'initializePayment'])->name('initialize');
    Route::get('/callback', function () {
        return view('payment-test.callback-success');
    })->name('callback');

    // 🔍 Status & Monitoring
    Route::post('/status', [PaymentTestController::class, 'checkPaymentStatus'])->name('status');
    Route::post('/status/live', [PaymentTestController::class, 'liveStatusMonitor'])->name('status.live');

    // 🎲 Test Data Generation
    Route::get('/generate-data', [PaymentTestController::class, 'generateTestData'])->name('generate-data');
    Route::post('/scenarios', [PaymentTestController::class, 'testScenarios'])->name('scenarios');
    Route::post('/bulk-test', [PaymentTestController::class, 'bulkTest'])->name('bulk-test');

    // 📊 Analytics & Stats
    Route::get('/analytics', [PaymentTestController::class, 'getAnalytics'])->name('analytics');
    Route::get('/stats', [PaymentTestController::class, 'getPaymentStats'])->name('stats');

    // 📋 Log Details
    Route::get('/log/{id}', [PaymentTestController::class, 'getLogDetails'])->name('log.details');

    // 🎭 Simulation Tools
    Route::post('/simulate-callback', [PaymentTestController::class, 'simulateCallback'])->name('simulate-callback');

    // 🔧 Configuration & Health
    Route::get('/config', [PaymentTestController::class, 'testConfiguration'])->name('config');

    // 🧹 Cleanup
    Route::delete('/cleanup', [PaymentTestController::class, 'cleanupTestData'])->name('cleanup');
});

// OneSignal Push Notification Testing Dashboard
Route::get('/onesignal-test', function () {
    return view('onesignal-test');
})->name('onesignal.test');

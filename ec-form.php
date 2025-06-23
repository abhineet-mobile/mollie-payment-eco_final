<?php
    /*
    Plugin Name: EcoCheque Payment Form
    Description: A custom payment form that integrates EcoCheque payment via Mollie.
    Version: 1.0
    Author: Abhnav
	Email: avi.program@gmail.com   
    */
    // Start session
    add_action('init', function() {
        if (!session_id()) {
            session_start();
        }
    });
    // Shortcode & scripts
    add_filter('wp_mail_from_name', function($name) {
    return 'Pay with ecoCheques';
		});
    add_shortcode('eco_cheque_form', 'eco_cheque_form_shortcode');
    add_action('wp_enqueue_scripts', 'eco_cheque_form_scripts');
    add_action('wp_ajax_nopriv_ecocheque_create_payment', 'ecocheque_create_payment');
    add_action('wp_ajax_ecocheque_create_payment', 'ecocheque_create_payment');

    function eco_cheque_form_scripts() {
        wp_enqueue_style('eco-cheque-style', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('eco-cheque-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), null, true);
        wp_localize_script('eco-cheque-script', 'ecoChequeData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'fee_type' => get_option('eco_handling_fee_type', 'fixed'),
            'fee_value' => get_option('eco_handling_fee_value', 0)
        ]);
    }

    function eco_cheque_form_shortcode() {
        ob_start(); ?>
        <div id="eco-cheque-form" class="eco-step-form">
            <!-- Step 1 -->
            <div class="step step-1 active">
                <h2>Item</h2>
                <label class="tooltip-container" for="disc-eco">Description <span><i class="fas fa-info-circle"></i></span> 
      <div class="tooltip-text">
        Succinct description of the second-hand item you wish to purchase. This may include weblinks.
      </div></label>
                <textarea id="disc-eco" required></textarea><br>
                <label class="tooltip-container" for="eco-amount">Price (incl. shipping )<span><i class="fas fa-info-circle"></i></span> 
      <div class="tooltip-text">
        Full price you agreed with the seller, which may include shipping & handling costs (min. 25 euro). Pay With Ecocheques will transfer this amount to the seller. Pay with Ecocheques will add an 8% fee to this to calculate the final amount due.
      </div>

    </label>				
                <input type="number" id="eco-amount"  min="25"  placeholder="Enter price (min €25)" required>
<span id="eco-amount-error" style="color:red; display:none;">Minimum price is €25</span>
				
<br>
                <label class="checkbox-rq">
                    Is second-hand & doesn’t include a combustion engine
                    <input type="checkbox" id="checkbox-step1" required>
                </label>
            </div>

            <!-- Step 2 -->
            <div class="step step-2">
                <h2>The Seller</h2>
                <label for="seller_name">Name</label>
                <input type="text" id="seller_name" required><br>
                <label for="email_s">Email address</label>
                <input type="email" id="email_s" required><br>
                <label class="tooltip-container" for="seller_iban">IBAN <span><i class="fas fa-info-circle"></i></span> 
      <div class="tooltip-text">
        This account must be registered in the seller's name.
      </div></label>
                <!--input type="text" id="seller_iban" placeholder="e.g. BE12 3456 7890 1234" value="BE" maxlength="16"  required><br-->
				<div style="display:flex; gap:6px; align-items:center;">
				<span style="font-weight:bold; padding-bottom: 7px;">BE</span>
					<input type="text" id="seller_iban" maxlength="17" placeholder="1234 5678 9012 34" required style="flex:1;"></div>
                <label class="tooltip-container" for="seller_refer">Reference for seller <span><i class="fas fa-info-circle"></i></span> 
      <div class="tooltip-text">
       Optional. This will be included in the payment to the seller.
      </div></label>
                <input type="text" id="seller_refer">
            </div>

            <!-- Step 3 -->
            <div class="step step-3">
                <h2>You</h2>
                <label for="eco-name">Name:</label>
                <input type="text" id="eco-name" required><br>

                <label for="eco-email">Email:</label>
                <input type="email" id="eco-email" required><br>

                <label class="checkbox-rq">
                    <p>Agree to the <a href="https://paywithecocheques.be/terms-conditions/" target="_blank">terms and conditions</a> and that your info is processed in accordance with our <a href="https://paywithecocheques.be/privacy-policy/" target="_blank">privacy policy</a><p>
                    <input type="checkbox" id="checkbox-step3" required>
                </label>
            </div>

            <!-- Navigation Buttons -->
            <div class="navigation-buttons">
                <button type="button" id="prev-step" style="display: none;">Back</button>
                <button type="button" id="next-step">Next</button>
            </div>
        </div>

        <!-- Modal -->
        <div class="order-summerybox" id="eco-modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Order summary</h2>
                    <button class="close">×</button>
                </div>
                <div class="modal-body">
                    <ul class="summary-info">
        <li> Purchased by: <span id="summary-name"></span> - <span id="modal-summary-email"></span> <li>
                        <li> Amount: €<span id="summary-amount"></span><li>
                        <li>Handling Fee: €<span id="summary-fee"></span></li>
                        <li>Total: €<span id="summary-total"></span></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button id="submit-eco-payment" class="modal-button">Pay with EcoCheque</button>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    // AJAX payment handler
    function ecocheque_create_payment() {
        $mollie_api_url = "https://api.mollie.com/v2/payments";
       // $api_key = "test_59Px7DFuEak3yTChVK4btcfnTUtWWP";//
$api_key = defined('MY_PLUGIN_API_KEY') ? MY_PLUGIN_API_KEY : '';	
        $amount = floatval($_POST['amount']);
        $order_number = 'ECO' . strtoupper(uniqid());
        // $name = sanitize_text_field($_POST['name']);
        $name = sanitize_text_field($_POST['eco_name']);
        $email = sanitize_email($_POST['email']);
        //$item_description = sanitize_text_field($_POST['description']);
        $item_description = sanitize_textarea_field($_POST['description']);

        $seller_name = sanitize_text_field($_POST['seller_name']);
        $seller_email = sanitize_email($_POST['seller_email']);
       // $seller_iban = sanitize_text_field($_POST['seller_iban']);
		$seller_iban_raw = sanitize_text_field($_POST['seller_iban']);
$seller_iban = 'BE' . strtoupper(str_replace(' ', '', $seller_iban_raw));

        $seller_reference = sanitize_text_field($_POST['seller_reference']);
        $truncated_description = substr('Second-hand Item: ' . $item_description, 0, 255);


        $handling_fee_type = get_option('eco_handling_fee_type', 'fixed');
        $handling_fee_value = get_option('eco_handling_fee_value', 0);
        $fee = ($handling_fee_type === 'percentage') ? ($amount * ($handling_fee_value / 100)) : $handling_fee_value;
        $total = round($amount + $fee, 2);
$redirectUrl = home_url('/?eco-payment-verify=true&payment_id=TEMP_ID');

$data = [
    'amount' => [
        'currency' => 'EUR',
       'value' => number_format($total, 2, '.', '')
		//'value' => number_format($amount, 2, '.', '')
    ],
	'description' => 'EcoCheque eligible item',
    //'description' => "EcoCheque payment by $name",
    'redirectUrl' => $redirectUrl,
    'cancelUrl' => home_url('/payment-cancelled'),
   // 'method' => 'voucher',
   'lines' => [
    [
        //'description' => 'Second-hand Item: ' . $item_description,
        'description' => $truncated_description,
        'quantity' => 1,
        'unitPrice' => [
            'currency' => 'EUR',
            'value' => number_format($total, 2, '.', '')
        ],
        'totalAmount' => [
            'currency' => 'EUR',
            'value' => number_format($total, 2, '.', '')
        ],
        'vatRate' => '0.00',
        'vatAmount' => [
            'currency' => 'EUR',
            'value' => '0.00'
        ],
		'categories' => ['eco']
    ]
],
   'metadata' => [
       'customer_name' => $name,
       'original_amount' => $amount,
       'handling_fee' => $fee
   ]
];


 $ch = curl_init($mollie_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
curl_close($ch);

        $response_data = json_decode($response, true);
		error_log('Mollie Response Data: ' . print_r($response_data, true));

        if (isset($response_data['_links']['checkout']['href'], $response_data['id'])) {
            $redirectUrl = home_url('/?eco-payment-verify=true&payment_id=' . urlencode($response_data['id']));
    
    // Set redirect and cancel URL dynamically
    $update_data = [
        'redirectUrl' => $redirectUrl,
        'cancelUrl' => home_url('/payment-cancelled')
    ];

    // Update Mollie payment to add redirect/cancel URL
    $update_ch = curl_init("https://api.mollie.com/v2/payments/{$response_data['id']}");
    curl_setopt($update_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($update_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($update_ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($update_ch, CURLOPT_POSTFIELDS, json_encode($update_data));
    curl_exec($update_ch);
    curl_close($update_ch);

            global $wpdb;// Save data to session or transient
        session_start();
        $_SESSION['eco_data'] = [
        'order_number' => $order_number,
        'customer_name' => $name,
        'email' => $email,
        'amount' => number_format($amount, 2),
        'fee' => number_format($fee, 2),
        'total' => number_format($total, 2),
        'description' => $item_description,
        'seller_name' => $seller_name,
        'seller_email' => $seller_email,
        'seller_iban' => $seller_iban,
        'seller_reference' => $seller_reference,
        ];
        $_SESSION['eco_payment_success'] = true;
        wp_send_json_success(['checkout_url' => $response_data['_links']['checkout']['href']]);
            } else {
                wp_send_json_error(['message' => 'Failed to create payment']);
            }
    }


    // Thank You Shortcode to show order data from session
    add_shortcode('eco_thank_you_data', function() {
        if (!isset($_SESSION['eco_data'])) {
            return '<p>No order data found.</p>';
        }
 if (empty($_SESSION['eco_payment_success']) || !isset($_SESSION['eco_data'])) {
        wp_redirect(home_url('/payment-cancelled'));
        exit;
    }

        $data = $_SESSION['eco_data'];
        ob_start(); ?>
        <div class="eco-summary container-summery">
            <h2>Thank You for Your Payment!</h2>
            <h3>A confirmation has been sent to <?= esc_html($data['email']) ?>.</h3> 
            <h3>The seller will receive their funds within 24h.</h3>
            <div class="thankyou-container">
            <h4>Order summary</h4>
            <h4>Order #: <?= esc_html($data['order_number']) ?></h4>
            <div class="wrapper-summery">
                <ul>
                   <li>Description: <?= nl2br(esc_html(stripslashes($data['description']))) ?></li>
                   <li>Purchased by: <?= esc_html($data['email']) ?></li>
                   <li>Sold by: <span><?= esc_html($data['seller_name']) ?></span> - <span><?= esc_html($data['seller_email']) ?></span></li>
                   <li>Funds to be transferred to <?= esc_html($data['seller_iban']) ?> </li>
                   	<?php if (!empty($data['seller_reference'])): ?>
                       <li>Under reference <?= esc_html($data['seller_reference']) ?></li>
                   <?php endif; ?>
                </ul>
                <ul class="left-box-tfc">
                   <li>Price to seller: €<?= esc_html($data['amount']) ?></li>
                   <li>Handling fee: €<?= esc_html($data['fee']) ?></li>
                   <li><strong>Grand total: €<?= esc_html($data['total']) ?></strong></li>
                </ul>
               </div>
            </div>
        </div>
        <?php
        unset($_SESSION['eco_data']); // clear after use
        unset($_SESSION['eco_payment_success']);

        return ob_get_clean();
    });

//Cancel


    // Admin order table
    add_action('admin_menu', function() {
        add_menu_page('EcoCheque Orders', 'EcoCheque Orders', 'manage_options', 'eco-orders', 'eco_orders_page');
    });

   function eco_orders_page() {
    global $wpdb;
    $orders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eco_orders ORDER BY created_at DESC");

    echo "<div class='wrap'><h1>EcoCheque Orders</h1>";

    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
    echo '<input type="hidden" name="action" value="eco_download_selected_csv">';
    echo '<input type="submit" class="button button-primary" value="Download Selected Orders as CSV" style="margin-bottom: 15px;">';

    echo "<table class='widefat'><thead>
        <tr>
            <th><input type='checkbox' id='eco-select-all'></th>
            <th>Order #</th><th>Customer</th><th>Email</th><th>Description</th>
            <th>Seller</th><th>Seller Email</th><th>IBAN</th><th>Ref</th>
            <th>Amount</th><th>Fee</th><th>Total</th><th>Date</th>
        </tr>
    </thead><tbody>";

    foreach ($orders as $o) {
        echo "<tr>
            <td><input type='checkbox' name='eco_order_ids[]' value='{$o->id}'></td>
            <td>{$o->order_number}</td>
            <td>{$o->customer_name}</td>
            <td>{$o->email}</td>
            <td>{$o->description}</td>
            <td>{$o->seller_name}</td>
            <td>{$o->seller_email}</td>
            <td>{$o->seller_iban}</td>
            <td>{$o->seller_reference}</td>
            <td>€{$o->amount}</td>
            <td>€{$o->handling_fee}</td>
            <td>€{$o->total}</td>
            <td>{$o->created_at}</td>
        </tr>";
    }

    echo "</tbody></table></form>";

    // Add JS for "Select All" checkbox
    echo '<script>
        document.getElementById("eco-select-all").addEventListener("change", function(e) {
            const checkboxes = document.querySelectorAll("input[name=\'eco_order_ids[]\']");
            checkboxes.forEach(cb => cb.checked = e.target.checked);
        });
    </script>';

    echo "</div>";
}


    // Create DB table
    register_activation_hook(__FILE__, 'eco_create_orders_table');
    function eco_create_orders_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'eco_orders';
        $charset = $wpdb->get_charset_collate();
        $order_number_column = "order_number VARCHAR(100),";
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            order_number VARCHAR(100),
            customer_name VARCHAR(255),
            email VARCHAR(255),
            amount FLOAT,
            handling_fee FLOAT,
            total FLOAT,
            description TEXT,
            seller_name VARCHAR(255),
            seller_email VARCHAR(255),
            seller_iban VARCHAR(100),
            seller_reference VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Settings page
    add_action('admin_menu', function() {
        add_options_page('EcoCheque Settings', 'EcoCheque Settings', 'manage_options', 'eco-settings', 'eco_settings_page');
    });

    add_action('admin_init', function() {
        register_setting('eco_settings_group', 'eco_handling_fee_type');
        register_setting('eco_settings_group', 'eco_handling_fee_value');
    });

    function eco_settings_page() { ?>
        <div class="wrap">
            <h1>EcoCheque Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('eco_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th>Fee Type</th>
                        <td>
                            <select name="eco_handling_fee_type">
                                <option value="fixed" <?php selected(get_option('eco_handling_fee_type'), 'fixed'); ?>>Fixed</option>
                                <option value="percentage" <?php selected(get_option('eco_handling_fee_type'), 'percentage'); ?>>Percentage</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Fee Value</th>
                        <td>
                            <input type="number" step="0.01" name="eco_handling_fee_value" value="<?php echo esc_attr(get_option('eco_handling_fee_value', 0)); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
			

        </div>




    <?php } ?>
<?php 
add_action('template_redirect', function () {
    if (isset($_GET['eco-payment-verify']) && $_GET['eco-payment-verify'] === 'true' && isset($_GET['payment_id'])) {
        $payment_id = sanitize_text_field($_GET['payment_id']);
        $api_key = defined('MY_PLUGIN_API_KEY') ? MY_PLUGIN_API_KEY : '';
        
        $ch = curl_init("https://api.mollie.com/v2/payments/{$payment_id}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
		error_log('Mollie Create Payment Response: ' . $response);

        curl_close($ch);

        $payment = json_decode($response, true);

       
        if (isset($payment['status']) && $payment['status'] === 'paid') {
    // Save order and send emails
    if (isset($_SESSION['eco_data'])) {
        global $wpdb;
        $data = $_SESSION['eco_data'];
        $wpdb->insert("{$wpdb->prefix}eco_orders", [
            'order_number' => $data['order_number'],
            'customer_name' => $data['customer_name'],
            'email' => $data['email'],
            'amount' => $data['amount'],
            'handling_fee' => $data['fee'],
            'total' => $data['total'],
            'description' => $data['description'],
            'seller_name' => $data['seller_name'],
            'seller_email' => $data['seller_email'],
            'seller_iban' => $data['seller_iban'],
            'seller_reference' => $data['seller_reference'],
            'created_at' => current_time('mysql')
        ]);

        // Admin email
        $admin_subject = "Admin New EcoCheque Payment Received";
        $admin_message = "A new EcoCheque payment has been completed.\n\n"
            . "Order Number: {$data['order_number']}\n"
            . "Name: {$data['customer_name']}\n"
            . "Email: {$data['email']}\n"
           // . "Description: {$data['description']}\n"
            ."Description: " . stripslashes($data['description']) . "\n"

            . "Seller Name: {$data['seller_name']}\n"
            . "Seller Email: {$data['seller_email']}\n"
            . "IBAN: {$data['seller_iban']}\n";
        if (!empty($data['seller_reference'])) {
            $admin_message .= "Reference: {$data['seller_reference']}\n";
        }
        $admin_message .= "Original Amount: €{$data['amount']}\n"
            . "Handling Fee: €{$data['fee']}\n"
            . "Total Amount: €{$data['total']}\n\n"
            . "You can view this in the EcoCheque Orders section.";

        wp_mail(get_option('admin_email'), $admin_subject, $admin_message);

        // Buyer email
        $message = '<div><img src="https://paywithecocheques.be/wp-content/uploads/2025/06/Logo_v4-1-scaled.png" style="max-width:200px; margin-bottom:20px;"></div>';
        $message .= "<h2>EcoCheque Order Details</h2>";
        $message .= "<p><strong>Order Number:</strong> {$data['order_number']}</p>";
        $message .= "<p>This is a summary of your order. Your payment has been successfully completed.</p>";
        $message .= "<p><strong>Name:</strong> {$data['customer_name']}</p>";
        //$message .= "<p><strong>Description:</strong> {$data['description']}</p>";
        $message .= "<p><strong>Description:</strong> " . nl2br(esc_html(stripslashes($data['description']))) . "</p>";
        $message .= "<p><strong>Amount:</strong> €{$data['amount']}</p>";
        $message .= "<p><strong>Handling Fee:</strong> €{$data['fee']}</p>";
        $message .= "<p><strong>Total:</strong> €{$data['total']}</p>";
        $message .= "<h3>Seller Details</h3>";
        $message .= "<p><strong>Seller Name:</strong> {$data['seller_name']}</p>";
        $message .= "<p><strong>Seller Email:</strong> {$data['seller_email']}</p>";
        $message .= "<p><strong>Seller IBAN:</strong> {$data['seller_iban']}</p>";
        if (!empty($data['seller_reference'])) {
            $message .= "<p><strong>Seller Reference:</strong> {$data['seller_reference']}</p>";
        }
        wp_mail($data['email'], "EcoCheque Order Confirmed", $message, ['Content-Type: text/html; charset=UTF-8']);

        // Seller email (reuse message or create new)
        $message = '<div><img src="https://paywithecocheques.be/wp-content/uploads/2025/06/Logo_v4-1-scaled.png" style="max-width:200px; margin-bottom:20px;"></div>';
        $message .= "<h2>EcoCheque Order Details</h2>";
        $message .= "<p><strong>Order Number:</strong> {$data['order_number']}</p>";
        $message .= "<p>This is a summary of the order. The payment has been successfully completed.</p>";
        $message .= "<p><strong>Buyer Name:</strong> {$data['customer_name']}</p>";
        $message .= "<p><strong>Buyer Email:</strong> {$data['email']}</p>";
       // $message .= "<p><strong>Description:</strong> {$data['description']}</p>";
        $message .= "<p><strong>Description:</strong> " . nl2br(esc_html(stripslashes($data['description']))) . "</p>";
        $message .= "<p><strong>Amount:</strong> €{$data['amount']}</p>";
        $message .= "<p><strong>Handling Fee:</strong> €{$data['fee']}</p>";
        $message .= "<p><strong>Total:</strong> €{$data['total']}</p>";
        $message .= "<p><strong>IBAN:</strong> {$data['seller_iban']}</p>";
        if (!empty($data['seller_reference'])) {
            $message .= "<p><strong>Reference:</strong> {$data['seller_reference']}</p>";
        }
        wp_mail($data['seller_email'], "EcoCheque Payment Received", $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    wp_redirect(home_url('/thank-you'));
} else {
    wp_redirect(home_url('/payment-cancelled'));
}
exit;

	}      
    
});

// EcoCheque Payment Redirect Verification
add_action('template_redirect', function () {
    if (isset($_GET['eco-payment-verify'])) {
        error_log('EcoCheque redirect handler triggered');
    }
});


add_action('admin_post_eco_download_selected_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized.');
    }

    if (empty($_POST['eco_order_ids']) || !is_array($_POST['eco_order_ids'])) {
        wp_die('No orders selected.');
    }

    global $wpdb;
    $ids = array_map('intval', $_POST['eco_order_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    $query = "SELECT * FROM {$wpdb->prefix}eco_orders WHERE id IN ($placeholders)";
    $orders = $wpdb->get_results($wpdb->prepare($query, $ids), ARRAY_A);

    if (empty($orders)) {
        wp_die('No matching orders found.');
    }

    $filename = 'eco_orders_selected_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=$filename");
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($orders[0]));

    foreach ($orders as $order) {
        fputcsv($output, $order);
    }

    fclose($output);
    exit;
});

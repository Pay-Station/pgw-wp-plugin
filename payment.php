<?php
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );
$cart_total = WC()->cart->total;
if (isset($_POST["access"])) {
    $data = $_POST["access"];
    $cartTotal = $cart_total;
    $cust_name = $_POST["billing_first_name"];
    $cust_phone = $_POST["billing_phone"];
    $cust_email = $_POST["billing_email"];
    $cust_address = $_POST["billing_address_1"];
    $invoice_number = $_POST["invoice_number"];
    $pay_with_charge = isset($_POST["charge_for_customer"]) ? $_POST["charge_for_customer"] : 0;
    $baseurl = $_POST["baseurl"];
    $returnURL = $_POST["returnURL"];
	$merchantId = $data["merchantId"];
	$password = $data["password"];

	$post_feild = array(
		'invoice_number' => 'WP'.$merchantId.'-'.$invoice_number,
		'currency' => "BDT",
		'payment_amount' => $cartTotal,
		'cust_name' => $cust_name,
		'cust_phone' => $cust_phone,
		'cust_email' => $cust_email,
		'cust_address' => $cust_address,
		'reference' => "WP-Website",
		'callback_url' => $returnURL,
		'checkout_items' => "checkout_items",
		'pay_with_charge' => $pay_with_charge,
		'merchantId' => $merchantId,
		'password' => $password
	);
	
	$url = "https://api.paystation.com.bd/initiate-payment"; // API endpoint URL
	$ch = curl_init(); // Initialize cURL session

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_feild);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

	$responseData = curl_exec($ch);
	curl_close($ch);
	
	$responseArray = json_decode($responseData, true);
	if (array_key_exists("status", $responseArray) && $responseArray["status"] == "success") {
		$rtData["status"] = "success";
		$rtData["statusCode"] = $responseArray["status_code"];
		$rtData["message"] = $responseArray["message"];
		$rtData["payment_url"] = $responseArray["payment_url"];
		echo json_encode($rtData);
	} else {
		$rtData["status"] = "failed";
		// $rtData["statusCode"] = $responseArray["statusCode"];
		$rtData["message"] = $responseArray["message"];
		echo json_encode($rtData);
	}
}


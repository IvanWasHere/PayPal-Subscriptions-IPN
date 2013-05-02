<?php
/*******************************************************************************
 *                      Paypal Subscriptions class
 *******************************************************************************
 *		Author:     Jordi Jolink (Soneritics Webdevelopment)
 *		Email:      info@soneritics.nl
 *		Website:    https://www.soneritics.nl
 *
 *      File:       paypal_subscriptions.php
 *      Version:    1.00
 *      Copyright:  (c) 2013 - Jordi Jolink
 *
 *******************************************************************************
 *
 *      Description here
 * 
 *******************************************************************************
*/
class PaypalSubscriptions
{
	// PayPal URLs for live and test version
	private $paypal_urls = array
	(
		'live' => 'https://www.paypal.com/webscr&cmd=_xclick-subscriptions&useraction=commit&token=',
		'test' => 'https://www.sandbox.paypal.com/webscr&cmd=_xclick-subscriptions&useraction=commit&token='
	);

	private $paypal_expresscheckout_urls = array
	(
		'live' => 'https://www.paypal.com/?cmd=_express-checkout&token=',
		'test' => 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token='
	);

	private $paypal_api_endpoints = array
	(
		'live' => 'https://api-3t.paypal.com/nvp',
		'test' => 'https://api-3t.sandbox.paypal.com/nvp'
	);

	// Private variables for use in this class
	private $test = false,			# Test mode
			$fields = array(),		# Data that will be used in the PayPal call
			$paypal_url, $paypal_expresscheckout_url, $paypal_api_endpoint,	# PayPal URLs
			$api_username, $api_password, $api_signature;					# PayPal authorization data

	/**
	 * Constructor. Initiates the PayPal class and sets the variables.
	 *
	 * @param $test Boolean, but can be omitted when using the live environment.
	 */
	public function PaypalSubscriptions($api_username, $api_password, $api_signature, $test = false)
	{
		@session_start();

		$this->test = $test;
		$this->api_username = $api_username;
		$this->api_password = $api_password;
		$this->api_signature = $api_signature;

		$this->paypal_url = $this->paypal_urls[$test ? 'test' : 'live'];
		$this->paypal_api_endpoint = $this->paypal_api_endpoints[$test ? 'test' : 'live'];
		$this->paypal_expresscheckout_url = $this->paypal_expresscheckout_urls[$test ? 'test' : 'live'];

		$this->fields = array
		(
			'currency_code'			=> 'USD',
			'CALLBACKVERSION'		=> '86',
			'APIVERSION'			=> '86',
			'max_failed_payments'	=> 3
		);
	}

	/**
	 * This function sets variables for use with PayPal Subscriptions.
	 */
	public function set($field, $value)
	{
		$this->fields[$field] = $value;
	}

	/**
	 * This function starts the payment authorization process
	 */
	public function authorize()
	{
		$authorize_fields = array
		(
			'USER'								=> $this->api_username,
			'PWD'								=> $this->api_password,
			'SIGNATURE'							=> $this->api_signature,
			'L_BILLINGTYPE0'					=> 'RecurringPayments',
			'RETURNURL'							=> $this->fields['url_setup'],
			'CANCELURL'							=> $this->fields['url_fail'],
			'CALLBACK'							=> $this->fields['url_callback'],
			'CALLBACKTIMEOUT'					=> '3',
			'CALLBACKVERSION'					=> $this->fields['CALLBACKVERSION'],
			'NOSHIPPING'						=> '1',
			'SHIPPINGOPTIONAMOUNT'				=> '0.00',
			'L_SHIPPINGOPTIONISDEFAULT0' 		=> 'true',
			'L_SHIPPINGOPTIONNAME0' 			=> 'No Shipping',
			'L_SHIPPINGOPTIONAMOUNT0' 			=> '0.00'
		);

		// Product number
		$pid = 0;

		// Create merge array
		$merge = array
		(
			'L_BILLINGAGREEMENTDESCRIPTION0'	=> $this->fields['description'],
			'PAYMENTREQUEST_0_AMT'				=> 0,
			'PAYMENTREQUEST_0_CURRENCYCODE'		=> $this->fields['currency_code'],
			'PAYMENTREQUEST_0_ITEMAMT'			=> 0,
			'PAYMENTREQUEST_0_SHIPPINGAMT'		=> '0.00',
			'PAYMENTREQUEST_0_DESC'				=> $this->fields['description'],
			'PAYMENTREQUEST_0_NOTIFYURL'		=> $this->fields['url_callback'],
			'PAYMENTREQUEST_0_PAYMENTREQUESTID'	=> '1000001'
		);

		// Subscriptions
		if ( isset($this->fields['products_subscriptions']) && count($this->fields['products_subscriptions']) > 0 )
			foreach ( $this->fields['products_subscriptions'] as $subscription )
			{
				$merge['L_PAYMENTREQUEST_0_NAME' . $pid] = $subscription['item_name'];
				$merge['L_PAYMENTREQUEST_0_NUMBER' . $pid] = $subscription['item_number'];
				$merge['L_PAYMENTREQUEST_0_DESC' . $pid] = $subscription['item_name'];
				$merge['L_PAYMENTREQUEST_0_AMT' . $pid] = $subscription['amount'];
				$merge['L_PAYMENTREQUEST_0_QTY' . $pid] = $subscription['quantity'];

				$merge['PAYMENTREQUEST_0_AMT'] += $subscription['amount'] * $subscription['quantity'];
				$pid++;
			}

		// One-time payments
		if ( isset($this->fields['products']) && count($this->fields['products']) > 0 )
			foreach ( $this->fields['products'] as $product )
			{
				$merge['L_PAYMENTREQUEST_0_NAME' . $pid] = $product['item_name'];
				$merge['L_PAYMENTREQUEST_0_NUMBER' . $pid] = $product['item_number'];
				$merge['L_PAYMENTREQUEST_0_DESC' . $pid] = $product['item_name'];
				$merge['L_PAYMENTREQUEST_0_AMT' . $pid] = $product['amount'];
				$merge['L_PAYMENTREQUEST_0_QTY' . $pid] = $product['quantity'];

				$merge['PAYMENTREQUEST_0_AMT'] += $product['amount'] * $product['quantity'];
				$pid++;
			}

		// Round
		$merge['PAYMENTREQUEST_0_AMT'] = (string)number_format(round($merge['PAYMENTREQUEST_0_AMT'], 2), 2);
		$merge['PAYMENTREQUEST_0_ITEMAMT'] = $merge['PAYMENTREQUEST_0_AMT'];

		// Do the merging
		$authorize_fields += $merge;

		// Add maximum amount
		$authorize_fields['MAXAMT'] = (string)number_format($merge['PAYMENTREQUEST_0_AMT'], 2);

		// Make the call
		$nvpstr = '';
		foreach ( $authorize_fields as $k => $v )
			$nvpstr .= '&' . $k . '=' . urlencode($v);

		$resArray = $this->hash_call("SetExpressCheckout", $nvpstr);
		$_SESSION['reshash'] = $resArray;

		if ( strtoupper($resArray["ACK"]) == "SUCCESS" )
		{
			header("Location: " . $this->paypal_expresscheckout_url . urldecode($resArray["TOKEN"]));
			return true;
		}
		else
			return false;
	}

	/**
	 * Starts the payment.
	 */
	public function setup()
	{
		$authorize_fields = array
		(
			'USER'			=> $this->api_username,
			'PWD'			=> $this->api_password,
			'SIGNATURE'		=> $this->api_signature,
			'TOKEN'			=> $_GET['token']
		);

		$nvpstr = '';
		foreach ( $authorize_fields as $k => $v )
			$nvpstr .= '&' . $k . '=' . urlencode($v);

		$resArray = $this->hash_call("GetExpressCheckoutDetails", $nvpstr);

		$_SESSION['reshash'] = $resArray;
		$ack = strtoupper($resArray["ACK"]);
		if ( $ack != "SUCCESS" )
			return false;

		// Calculate the total amount
		$subscription_amount = 0;
		$init_amount = 0;

		// Subscriptions
		if ( isset($this->fields['products_subscriptions']) && count($this->fields['products_subscriptions']) > 0 )
			foreach ( $this->fields['products_subscriptions'] as $subscription )
				$subscription_amount += $subscription['amount'] * $subscription['quantity'];

		// One-time sales
		if ( isset($this->fields['products']) && count($this->fields['products']) > 0 )
			foreach ( $this->fields['products'] as $product )
				$init_amount += $product['amount'] * $product['quantity'];

		// Create the call
		$fields = array
		(
			'USER'				=> $this->api_username,
			'PWD'				=> $this->api_password,
			'SIGNATURE'			=> $this->api_signature,
			'TOKEN'				=> $_GET['token'],
			'PAYERID'			=> $resArray['PAYERID'],
			'PROFILESTARTDATE'	=> $this->fields['startdate'],
			'DESC'				=> $this->fields['description'],
			'BILLINGPERIOD'		=> $this->fields['billingperiod'],
			'BILLINGFREQUENCY'	=> $this->fields['billingfrequency'],
			'AMT'				=> (string)number_format(round($subscription_amount, 2), 2),
			'INITAMT'			=> (string)number_format(round($init_amount, 2), 2),
			'CURRENCYCODE'		=> $this->fields['currency_code'],
			'COUNTRYCODE'		=> $this->fields['country_code'],
			'MAXFAILEDPAYMENTS'	=> $this->fields['max_failed_payments']
		);

		// Do the call
		$nvpstr = '';
		foreach ( $fields as $k => $v )
			$nvpstr .= '&' . $k . '=' . urlencode($v);

		$resArray = $this->hash_call("CreateRecurringPaymentsProfile", $nvpstr);
		$_SESSION['reshash'] = $resArray;

		if ( strtoupper($resArray["ACK"]) == "SUCCESS" )
			return $resArray;
		else
			return false;
	}

	public function cancel_subscription($profile_id)
	{
		$fields = array
		(
			'USER'				=> $this->api_username,
			'PWD'				=> $this->api_password,
			'SIGNATURE'			=> $this->api_signature,
			'PROFILEID'			=> $profile_id,
			'ACTION'			=> 'Cancel'
		);

		$nvpstr = '';
		foreach ( $fields as $k => $v )
			$nvpstr .= '&' . $k . '=' . urlencode($v);

		$resArray = $this->hash_call("ManageRecurringPaymentsProfileStatus", $nvpstr);
	}

	/**
	  * hash_call: Function to perform the API call to PayPal using API signature
	  * @methodName is name of API  method.
	  * @nvpStr is nvp string.
	  * returns an associtive array containing the response from the server.
	*/
	private function hash_call($methodName, $nvpStr)
	{
		// Setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->paypal_api_endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		// Turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// In case of permission APIs send headers as HTTPheders
		$nvpStr = "&VERSION=" . urlencode($this->fields['APIVERSION']) . "&PWD=" . urlencode($this->api_password) . "&USER=" . urlencode($this->api_username) . "&SIGNATURE=" . urlencode($this->api_signature) . $nvpStr;
		$nvpreq = "METHOD=" . urlencode($methodName) . $nvpStr;

		// Setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		// Getting response from server
		$response = curl_exec($ch);

		// Converting NVPResponse to an Associative Array
		$nvpResArray = $this->deformatNVP($response);
		$nvpReqArray = $this->deformatNVP($nvpreq);
		$_SESSION['nvpReqArray'] = $nvpReqArray;

		if ( curl_errno($ch) )
		{
			// Moving to display page to display curl errors
			$_SESSION['curl_url'] = $this->paypal_api_endpoint;
			$_SESSION['curl_error_no'] = curl_errno($ch) ;
			$_SESSION['curl_error_msg'] = curl_error($ch);
			echo '<pre>';
			print_r($_SESSION);
			echo '</pre>';
			die();
		}
		else
			curl_close($ch);

		return $nvpResArray;
	}

	/** This function will take NVPString and convert it to an Associative Array and it will decode the response.
	  * It is usefull to search for a particular key and displaying arrays.
	  * @nvpstr is NVPString.
	  * @nvpArray is Associative Array.
	  */
	private function deformatNVP($nvpstr)
	{
		$intial = 0;
		$nvpArray = array();

		while( strlen($nvpstr) )
		{
			// Postion of Key
			$keypos = strpos($nvpstr,'=');

			// Position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&') : strlen($nvpstr);

			// Getting the Key and Value values and storing in a Associative Array
			$keyval = substr($nvpstr, $intial, $keypos);
			$valval = substr($nvpstr, $keypos + 1, $valuepos - $keypos - 1);

			// Decoding the respose
			$nvpArray[urldecode($keyval)] = urldecode($valval);
			$nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
		}

		ksort($nvpArray);
		return $nvpArray;
	}
}
?>
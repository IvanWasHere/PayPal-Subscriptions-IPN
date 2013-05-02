<?php
	// Settings, these need to be changed for your situation
	$settings = array
	(
		'test'				=> true,
		'billingperiod'		=> 'Month',
		'billingfrequency'	=> 1,
		'startdate'			=> date('Y-m-d') . 'T00:00:00',
		'country_code'		=> 'US',
		'currency_code'		=> 'USD',
		'description'		=> 'Test Store payment',

		'products' => array
		(
			array
			(
				'item_name'		=> 'Test one-time',
				'item_number'	=> 'TEST-OT-1',
				'amount'		=> 25,
				'quantity'		=> 1
			)
		),
		'products_subscriptions' => array
		(
			array
			(
				'item_name'		=> 'Test subscription 1',
				'item_number'	=> 'TEST-SUBSCR-1',
				'amount'		=> 10,
				'quantity'		=> 1
			),
			array
			(
				'item_name'		=> 'Test subscription 2',
				'item_number'	=> 'TEST-SUBSCR-2',
				'amount'		=> 10,
				'quantity'		=> 2
			)
		),

		'url_success'		=> 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF'] . '?action=success&profileid=',
		'url_pending'		=> 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF'] . '?action=pending&profileid=',
		'url_fail'			=> 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF'] . '?action=failed',
		'url_callback'		=> 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF'] . '?action=callback',
		'url_setup'			=> 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF'] . '?action=setup',

		'api_username'		=> '',
		'api_password'		=> '',
		'api_signature'		=> ''
	);

	// Require the Paypal Subscriptions class and initialize the object
	require('paypal_subscriptions.php');
	$paypal = new PaypalSubscriptions($settings['api_username'], $settings['api_password'], $settings['api_signature'], $settings['test']);

	// Send settings to the PayPal object
	foreach ( $settings as $k => $v )
		$paypal->set($k, $v);

	// Fetch action from GET
	$action = isset($_GET['action']) ? $_GET['action'] : 'init';

	switch ( $action )
	{
		/**
		 * Initialization. For this test class, it just generates a form with pre-entered fields and automatically
		 * submits to Paypal.
		 */
		case 'init':
			if ( !$paypal->authorize() )
			{
				echo '<pre>';
				print_r($_SESSION);
				echo '</pre>';
			}

			exit();
		break;

		/**
		 * Do the actual payment
		 */
		case 'setup':
			$pp_result = $paypal->setup();
			if ( $pp_result === false )
			{
				echo '<h1>Error</h1><pre>';
				print_r($_SESSION);
				echo '</pre>';
			}
			else
			{
				/*
				// For testing purposes, print out the result array
				echo '<h1>Success</h1><pre>';
				print_r($settings);
				print_r($pp_result);
				echo '</pre>';
				*/

				# TODO: Do something with the profile id, based on the profile state (active/pending)

				// Redirect the user to the corresponding page
				if ( strtoupper($pp_result['PROFILESTATUS']) == 'PENDINGPROFILE' )
					header('Location: ' . $settings['url_pending'] . $pp_result['PROFILEID']);
				else
					header('Location: ' . $settings['url_success'] . $pp_result['PROFILEID']);
			}

			exit();
		break;

		/**
		 * Callback function
		 */
		case 'callback':
			// Because there is no output at this point, for this test case we will write the contents of the $_POST to a file
			ob_start();
			echo date('H:i:s') . "\r\n\r\n";
			print_r($_POST);
			print_r($_SERVER);
			file_put_contents('callback.txt', ob_get_clean());

			// Check state
			if ( strtoupper($_POST['profile_status']) == 'ACTIVE' )
			{
				/* Store this in your database, when checked against the amount ($_POST['amount'] for the one time payments and $_POST['initial_payment_amount'] for the subscriptions)
				 * and use the recurring profile id ($_POST['recurring_payment_id']) for storage/identifying the order.
				 */
			}
		break;

		/**
		 * When a payment has been done, the recurring payments profile might be 'pending'.
		 * 
		 */
		case 'pending':
			die('Payment is being reviewed: ' . $_GET['profileid']);
		break;

		/**
		 * Success function. Shows a message for the customer, when he gets redirected from PayPal back to the site.
		 * This function is no guarantee that the payment has actually been succesful. This information is passed to
		 * the callback action, where it is verified as well.
		 */
		case 'success':
			die('Payment success: ' . $_GET['profileid']);
		break;

		/**
		 * Return page for a failed payment.
		 */
		case 'failed':
			die('Payment failed. <a href="index.php">Restart</a>');
		break;
	}
?>
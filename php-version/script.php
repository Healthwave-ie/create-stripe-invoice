<?php
// Debug tools if needed
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('access-control-allow-origin: *');
set_time_limit(240);
http_response_code(200); // Setting status code for Zendesk,

// Utilities
require_once('utilities/Stripe.php');
require_once('utilities/Twilio.php');
require_once('utilities/Rebrandly.php');
require_once('utilities/Zendesk.php');
require_once('utilities/main_utilities.php');
require_once('utilities/Databases.php');
require_once('templates/messages.php');

$data = json_decode(file_get_contents('php://input'));

if (isset($data)) {
  $description      = "";
  $env              = "PROD";
  $name             = $data->name;
  $email            = $data->email;
  $phone            = $data->phone;
  $amount           = $data->amount;
  $zendesk_id       = $data->zendesk_id;
  $member_id        = $data->member_id;
  $membership       = $data->membership;
  $dispatch_date    = $data->dispatch_date;
  $courier_fees     = 0;
  $is_exempt        = false;
  $courier_charge   = array();

  if ($membership === 'an_post_employee_member' || $membership === 'an_post_family_member' || $membership === 'garda_medical_aid' || $membership === 'pomas') {
    $is_exempt = true;
  }

  if ($membership === 'member_yes') {
    /*
    $freeDeliveriesUsed = display_free_deliveries_used ($member_id);
    if ($freeDeliveriesUsed < 6 && $freeDeliveriesUsed !== null) {
      $is_exempt = true;
      add_free_delivery_used($member_id);
    }
    */
    // NEW LOGIC - 13/08/2020
    //Don't charge until end of year
    $dispatch_date_unix = strtotime($dispatch_date);
    if (date("Y", $dispatch_date_unix) == date("2020")) {
      //echo "no courier charge as it's 2020";
      $is_exempt = true;
    } else if (date("Y", $dispatch_date_unix) > date("2020")) {
      //echo "Adding courier charge as it's not 2020";
      $is_exempt = false;
    }
  }

  ////

  // NEW LOGIC
  // if ((int)$amount < 50 && $is_exempt === false) {
  //   $courier_fees                   = 5;
  //   $courier_charge["add"]          = true;
  //   $courier_charge["amount"]       = $courier_fees * 100;
  //   $courier_charge["description"]  = "courier charge";
  //   $formatted_amount               = format_price($amount + $courier_fees);
  // } else {
  //   $courier_charge["add"]          = false;
  //   $formatted_amount               = format_price($amount);
  // }

  if ((int)$amount < 30 && $is_exempt === false) {
    $courier_fees                   = 5;
    $courier_charge["add"]          = true;
    $courier_charge["amount"]       = $courier_fees * 100;
    $courier_charge["description"]  = "courier charge";
    $formatted_amount               = format_price($amount + $courier_fees);
  } else {
    $courier_charge["add"]          = false;
    $formatted_amount               = format_price($amount);
  }

  $amount_integer = $amount * 100; // Stripe need the price in that format to generate the invoice
  $today          = date('m/d/Y');
  if (strlen($member_id) > 1) {
    $description .= "Membership ID #" . $member_id . ", ";
  }
  $description    .= "Order #" . $zendesk_id . " - " . $today;

  $name_arr               = explode(' ', trim($name));
  $first_name             = $name_arr[0];
  $formatted_phone        = format_phone($phone);
  $invoice                = create_and_finalize_invoice($courier_charge, $email, $first_name, $amount_integer, $description, $zendesk_id);
  $invoice_url            = $invoice["hosted_invoice_url"];
  $invoice_url_shortened  = "https://" . rebrand_url($invoice_url);
  $sms                    = new msg;
  $message                = $sms->payment_request_sms($courier_charge['add'], $courier_fees, $first_name, $formatted_amount, $invoice_url_shortened);
  $comment1               = "Invoice link sent: " . $invoice_url_shortened;
  $comment2               = "SMS Sent: \n" . $message;
  add_invoice_url($zendesk_id, $invoice_url_shortened); // Adding the link to the Zendesk ticket
  send_sms('Healthwave',$formatted_phone, $message);
  mark_invoice_as_sent($zendesk_id, $comment1); // pushing the invoice link back to the Zendesk ticket for reference.
  push_private_comment($zendesk_id, $comment2); // Pushing SMS as comment in Zendesk for our records.
  add_payment_reminder($env, $name, $formatted_phone, $zendesk_id, $invoice_url_shortened); //  Adding the client to the 'Payment_reminders' table in the db for track keeping.
  http_response_code(200);
} else {
  echo "no payload provided.";
}

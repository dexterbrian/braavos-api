<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class BraavosController extends Controller
{

    # TODO remove test function
    public function handleIncomingSm(Request $request) {

        $senderPhoneNo = str_replace('+', '', $request->input('from'));

        $this->sendMessage('phone: ' . $senderPhoneNo, (object) array('uid' => null, 'primary_phone' => $request->input('from')));
    }

    /**
     * A callback function that is invoked by Africa's Talking when a user sends an SMS to the shortcode.
     * It executes specific functions based on the keyword in the SMS received from the user.
     *
     * @param Request $request
     * @return void
     */
    public function handleIncomingSms(Request $request) {

        # Test phone number to use 254716330450

        # Remove the plus sign from the phone number
        $senderPhoneNo = str_replace('+', '', $request->input('from'));

        $user = $this->getUser($senderPhoneNo);

        $keyword = strtolower($request->input('text'));

        $amount = intval($request->input('text'));

        $message = (object) array(
            'text' => $keyword,
            'date' => $request->input('date'),
            'id' => $request->input('id'),
            'linkId' => $request->input('linkId')
        );
        

        # Check if $user contains an error message from the model
        if (empty($user->message)) {

            $this->logMessages($message, $user, $this->africasTalking['prodShortCode'], true);

            # Check if the text received from the user is an integer. Lower loan limit is KES 500
            if ($amount >= 500) {

                $response = $this->processLoan($user, $amount);
            }
            elseif ($amount == 0) {

                switch ($keyword) {
                    case 'limit':

                        $response = $this->sendLoanLimit($user);

                        break;
                    case 'balance':

                        $response = $this->sendLoanBalance($user);

                        break;
                    case 'loan':

                        $response = $this->sendEligibility($user);

                        break;
                    default:
                        $response = $this->sendMessage('Dear ' . $user->first_name . ', you sent the wrong keyword/amount. Please send the keyword Loan to ' . $this->africasTalking['prodShortCode'] . '.', $user);
                };
            }
            elseif ($amount < 500) {

                $response = $this->sendMessage('Dear ' . $user->first_name . ', you sent an amount below the minimum of KES 500.', $user);
            }
        }
        else {
            $response = $this->sendMessage('Dear customer, we do not seem to have your details on file. Please visit the office to get registered.', (object) array('uid' => null, 'primary_phone' => $request->input('from')));
        }

        return $response;
    }

    /**
     * Send the user a message based on whether the user is eligible for a loan
     *
     * @param object $user
     * @return json $response
     */
    private function sendEligibility($user) {
        $isEligible = $this->isEligible($user);
        $isLoanDisbursable = $this->isLoanDisbursable();

        if ($isEligible && $isLoanDisbursable) {
            # Send and log success message
            $response = $this->sendMessage('Dear ' . $user->first_name . ', you qualify for a new loan. Please enter a loan value between KES 500 and ' . $user->loan_limit  . '. Do not use commas.', $user);
        }
        elseif ($isEligible && !$isLoanDisbursable) {
            $response = $this->sendMessage('Dear ' . $user->first_name . ', please note we do not disburse advances after the 15th of the month.', $user);
        }
        elseif (!$isEligible) {
            # Send and log regret message
            $response = $this->sendMessage('Dear ' . $user->first_name . ', you do not qualify for a new loan until you pay back your current loan of KES ' . $this->checkLoanBalance($user) . '.', $user);
        }

        return $response;
    }

    /**
     * Check if user is eligible to receive a loan
     *
     * @param object $user
     * @return boolean
     */
    private function isEligible($user = '') {
        $loanBalance = $this->checkLoanBalance($user);

        if ($loanBalance > 0) {
            return false;
        }
        elseif ($loanBalance == 0) {
            return true;
        }
    }

    /**
     * Checks what day of the month it is. If the date is after the 15th then return false, else return true.
     *
     * @param object $user
     * @return boolean
     */
    private function isLoanDisbursable() {
        if (date('d') > 15) {
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Send maximum loan amount that the user can receive
     *
     * @param object $user
     * @return json $response
     */
    private function sendLoanLimit($user) {
        $loanLimit = $user->loan_limit;

        # Send loan limit to the user 
        $response = $this->sendMessage('Dear ' . $user->first_name . ', your advance limit, as at ' . date('d-m-Y') . ', is KES ' . $loanLimit  . '.', $user);

        return $response;
    }

    private function checkLoanLimit($user = '') {

        # TODO use $user->primary_phone or other user details to fetch the user's loan limit from the relevant tables on the mysql db
        
        return '5000';
    }

    /**
     * Send user the amount left for them to pay to have cleared the loan
     *
     * @param object $user
     * @return json $response
     */
    private function sendLoanBalance($user) {
        $loanBalance = $this->checkLoanBalance($user);
        
        $response = $this->sendMessage('Dear ' . $user->first_name . ', your effective loan balance, as at ' . date('d-m-Y') . ', is KES ' . $loanBalance  . '.', $user);

        return $response;
    }

    public function checkLoanBalance($user = '') {

        # TODO remove test code below, default arguement assignment above and change this function to a private function
        $user = empty($user) ? (object) array('uid' => 1) : $user;

        # 1) Fetch loans from s_loans table where the customer_id is $user->uid and where status is Disbursed or Overdue
        $loan = DB::select('select uid, loan_total from s_loans where customer_id = ? and status = ? or status = ?', [$user->uid, 5, 2]);

        # 2) Get payments from s_incoming_payments table where the loan_id is the loan_id from the loan record found in step 1
        if (!empty($loan)) {
            
            $totalLoanPayments = DB::table('s_incoming_payments')->where('loan_id', $loan[0]->uid)->sum('amount');
            
            # 3) Calculate the loan balance using the loan_total and subtracting the total from the column amount in s_incoming_payments      
            return $loan[0]->loan_total - $totalLoanPayments;
        }
        else {
            return 0;
        }
    }

    /**
     * Get User details from the db
     *
     * @param string $phoneNumber
     * @return object
     */
    public function getUser($phoneNumber = '254728499458') {

        $user = DB::select('select uid, first_name, second_name, primary_phone, loan_limit from s_users_primary where primary_phone = ?', [$phoneNumber]);

        return empty($user) ? array('message' => 'No users found with that phone number') : $user[0];
    }

    # TODO remove the test function getMyUser()
    public function getMyUser($phoneNumber = '254716330450') {

        $user = DB::select('select uid, first_name, second_name, primary_phone, loan_limit from s_users_primary where primary_phone = ?', [$phoneNumber]);

        return empty($user) ? array('message' => 'No users found with that phone number') : $user[0]->first_name;
    }

    /**
     * Log incoming and outgoing messages in the db
     *
     * @param object $message
     * @param object $sender
     * @param object $recipient
     * @param boolean $isIncoming
     * @return bool
     */
    private function logMessages($message, $sender, $recipient, $isIncoming = false) {

        # Log incoming messages in the db if $isIncoming is true
        if ($isIncoming) {
            DB::insert('insert into s_incoming_sms (customer_Id, phone, content, date_received, id, linkId) values (?, ?, ?, ?, ?, ?)',
                [
                    $sender->uid,
                    $sender->primary_phone,
                    $message->text,
                    $message->date,
                    $message->id,
                    $message->linkId
                ]
            );
        }
        # Log outgoing messages in db if $isIncoming is false
        else {
            DB::insert('insert into s_outgoing_sms (short_code, customer_Id, mobile_number, message, date_queued, date_processed, received_) values (?, ?, ?, ?, ?, ?, ?)',
                [
                    $this->africasTalking['prodShortCode'],
                    $recipient->uid,
                    $recipient->primary_phone,
                    $message,
                    null,
                    date('Y-m-d h:m:s'),
                    '2' # Key: 1-queued 2-processed 3-reprocessed 4-deleted
                ]
            );
        }
    }

    /**
     * Send messages using the Africa's Talking SMS API
     *
     * @param string $message
     * @param object $recipient
     * @return string json
     */
    private function sendMessage($message, $recipient) {
        $headers = array(
            'apiKey' => $this->africasTalking['prodApiKey'],
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        );

        $response = Http::withHeaders($headers)->asForm()->post($this->africasTalking['prodUrl'] . 'messaging',
            array(
                'username' => 'sandbox',
                'to' => $recipient->primary_phone,
                'message' => $message,
                'from' => $this->africasTalking['prodShortCode']
            )
        );

        # Log outgoing messages
        $this->logMessages($message, $this->africasTalking['prodShortCode'], $recipient, false);

        # Log Africa's Talking API response
        $this->logApiResponses('africastalking', $response);

        return $response;
    }

    private function logApiResponses($api = '', $response) {
        DB::insert('insert into s_activity_logs (activity, date_created) values (?, ?)', [$api . ': ' . $response, date('Y-m-d h:m:s')]);
    }

    /**
     * This function enables you to send an SMS to a phone number from a given shortcode using
     * Africa's Talking SMS API: https://developers.africastalking.com/docs/sms/overview
     * 
     * The following parameters are needed to be passed as form data in the POST request's body:
     * - username (of the Africa's Talking app)
     * - to (the recipient)
     * - text (the message you want to send)
     * - shortcode (the shortcode you have created on Africa's Talking)
     *
     * @param Request $request
     * @return $response
     */
    public function messageMe(Request $request) {
        $headers = array(
            'apiKey' => $request->input('apiKey'),
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        );

        $response = Http::withHeaders($headers)->asForm()->post($this->africasTalking['prodUrl'] . 'messaging',
            array(
                'username' => $request->input('username'),
                'to' => $request->input('to'),
                'message' => $request->input('text'),
                'from' => $request->input('shortcode')
            )
        );

        return $response;
    }

    /**
     * Initializes the process of loaning an amount to a customer.
     * - Checks loan eligibility
     * - Checks if the 15th day of the month has passed
     * - Sends the relevant SMS message
     * - Sends the loan amount via mpesa
     * - Record the loan
     *
     * @param object $user
     * @param int $amount
     * @return json
     */
    private function processLoan($user, $amount) {
        $isEligible = $this->isEligible($user);
        $isLoanDisbursable = $this->isLoanDisbursable();

        if ($isEligible && $isLoanDisbursable) {
            
            DB::insert('insert into s_loans (customer_id, customer_phone, given_date, due_date, loan_amount, loan_total, time_created, mobile_disburse, approval, status) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $user->uid,
                    $user->primary_phone,
                    date('Y-m-d h:m:s'),
                    date('Y-m-d h:m:s'), # TODO set appropriate loan due_date
                    $amount,
                    $amount, # TODO set appropriate loan_total by adding loan_amount, processing_fee and loan_interest
                    date('Y-m-d h:m:s'), # TODO set appropriate time_created 
                    1, # If set to 1, mobile money process will send the amount via mobile
                    2, # If approval is set to one, loan will require approval that turns it to 2 	
                    2 # s_loan_statuses: 1 - Pending Disbursement, 2 - Disbursed, 3 - Failed, 4 - Cleared, 5 - Overdue, 6 - Written Off, 7 - Deleted, 8 - Cancelled, 9 - Pending Approval, 10 - Declined
                ]
            );

            $this->sendMessage('Dear ' . $user->first_name. ', you have selected KES ' . $amount . '. The loan advance will be processed shortly.', $user);

            $response = $this->sendMoney($user, $amount);
        }
        elseif ($isEligible && !$isLoanDisbursable) {
            $response = $this->sendMessage('Dear ' . $user->first_name . ', please note we do not disburse advances after the 15th of the month.', $user);
        }
        elseif (!$isEligible) {
            $response = $this->sendMessage('Dear ' . $user->first_name . ', you do not qualify for a new loan until you pay back your current loan of KES ' . $this->checkLoanBalance($user) . '.', $user);
        }

        return $response;
    }

    /**
     * Send money using mpesa B2C API
     *
     * @param array $user
     * @param integer $amount
     * @return string
     */
    # TODO change this function to a private function
    public function sendMoney(
        $user = '', # TODO remove default value of ''
        $amount = 1 # TODO remove default value of 1
        ){

        # TODO Remove from line 261 to 266 once project is ready to deploy
        if ($user == '') {
            $user = (object) array(
                # The passed user object will have other user information other than phone number
                'primary_phone' => 254708374149
            );
        }

        # 1) Use Daraja's Authorization API to get a time bound (1hr) access token to call allowed APIs
        # 2) Send a POST request to Daraja's B2C API using the access token as a Bearer token in the Authorization header 
        # and then passing the relevant parameters in a json body
        $authApiResponseObj = json_decode($this->getAccessToken());

        $accessToken = $authApiResponseObj->access_token;

        $paymentRequestUrl = 'mpesa/b2c/v1/paymentrequest';

        $response = Http::withToken($accessToken, 'Bearer')->asJson()
            ->post(
                $this->mpesa['sandboxUrl'] . $paymentRequestUrl,
                array(
                    'InitiatorName' => 'testapi',
                    'SecurityCredential' => $this->mpesa['sandboxSecurityCredential'],
                    'CommandID' => 'BusinessPayment',
                    'Amount' => $amount,
                    'PartyA' => $this->mpesa['devShortcode'],
                    'PartyB' => $user->primary_phone,
                    'Remarks' => 'Loan disbursment',
                    'QueueTimeOutURL' => $this->baseUrl['prod'] . 'queue',
                    'ResultURL' => $this->baseUrl['prod'] . 'result',
                    'Occassion' => 'null'
                )
            )
        ;

        # Log Daraja API's response
        $this->logApiResponses('mpesa', $response);

        return $response;
    }

    /**
     * Using Daraja's Authorization API to get a time bound (1hr) access token to call allowed APIs
     *
     * @return string json
     */
    public function getAccessToken() {
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($this->mpesa['consumerKey'] . ':' . $this->mpesa['consumerSecret'])
        );

        $authUrl = 'oauth/v1/generate?grant_type=client_credentials';

        # TODO Store mpesa access tokens in the db along with the timestamp for when it will expire
        # so that on subsequent requests, we simply reuse the access token until it expires. Once it
        # expires, we should then request a new access token.

        $response = Http::withHeaders($headers)->get($this->mpesa['sandboxUrl'] . $authUrl);

        return $response;
    }

    /**
     * Handles the notification from daraja when the request is accepted successfully
     */
    public function resultCallback() {
        # TODO log the timeout in the table s_activity_log in the db
    }

    /**
     * Handles the success notification from daraja when the request times out
     */
    public function timeOutCallback() {
        # TODO log the success notification response in the table s_activity_log in the db
    }
}

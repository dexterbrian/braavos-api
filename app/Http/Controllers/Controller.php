<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * Africa's Talking API URLs and Credentials
     */ 
    protected $africasTalking = array(
        'sandboxUrl' => 'https://api.sandbox.africastalking.com/version1/',
        'prodUrl' => 'https://api.africastalking.com/version1/',
        'prodApiKey' => 'b41dc0f42a6ac2646e41fe028ee0bdf2c37cf205b0bd180b205b0888e89a5b0f',
        'devApiKey' => '18ec5670f709297b8549a9813bc46df19f813922a217024323dca6fb2a9f41da',
        'devShortCode' => '23036',
        'prodShortCode' => '23572'
    );

    /**
     * Mpesa Daraja API URLs and Credentials
     */ 
    protected $mpesa = array(
        'sandboxUrl' => 'https://sandbox.safaricom.co.ke/',
        'consumerKey' => 'drjETz5hrML5dHnXFcN65fGZLWAxp7wE',
        'consumerSecret' => 'AmxAtfDG865phHnp',
        // The sandbox Security Credential is generated from the Daraja developer's portal in the profile page
        'sandboxSecurityCredential' => 'qEczPm3fkoUA2Ty47+BBGSxKI9iT2xc6UekkBYKTzNBVDBxCI9BZveMNvJBPMySiU2+vIOz147ULybDE9Z4wJYGU2k+WlvsBqpNrcEAC9g64QXv4Z6sdCjqfGEF6aTCA2Lv/eOOcZ6OUNRnkqnqvcyMUGRlnsQxuHhLMD/aKa4fS2ix7FgPjpBHRxMiVbMhdNcoe9WhveotN9YnWyh17c6qN0ztsQq3YBPm/uU8KmsYG2Vha1AP8TcIEEjABi/8WYx6tUFCJt/Ssej56jIRWQvwpyLWmCsu94jMl4rycoppVT8f6/UhprPmgxScRwkjHV02HUPVGU1WeHJwDFjzV7w==',
        'devShortcode' => '600978'
    );

    protected $baseUrl = array(
        'dev' => 'http://5ae6-102-220-228-235.ngrok.io/',
        'prod' => 'https://cyka.zhen.co.ke/'
    );
}

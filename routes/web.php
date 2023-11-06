<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return "Who told you about this URL?🤔";
    #return $router->app->version();
});

# Callback URL that is invoked by Africa's Talking when a customer sends an SMS to the shortcode
$router->post('/handleIncomingSms', 'BraavosController@handleIncomingSms');

$router->post('/queue' , 'BraavosController@timeOutCallback');

$router->post('/result', 'BraavosController@resultCallback');

# TODO remove the test route /messageMe
$router->post('/messageMe', 'BraavosController@messageMe');

# TODO remove the test route /sendMoney
$router->get('/sendMoney', 'BraavosController@sendMoney');

# TODO remove the test route /getUser
$router->get('/getUser', 'BraavosController@getMyUser');

# TODO remove the test route /getAccessToken
$router->get('/getAccessToken', 'BraavosController@getAccessToken');

# TODO remove the test route /checkLoanBalance
$router->get('/checkLoanBalance', 'BraavosController@checkLoanBalance');

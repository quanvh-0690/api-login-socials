<?php

use Illuminate\Http\Request;

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

Route::group(['namespace' => 'Api'], function () {
    Route::post('user/login', 'AuthController@login');
    Route::post('user/login/facebook', 'AuthController@facebook');
    Route::post('user/login/twitter', 'AuthController@twitter');
    Route::post('user/login/google', 'AuthController@google');
});

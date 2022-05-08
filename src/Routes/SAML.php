<?php

use Illuminate\Support\Facades\Route;
use OneLoginToolkit\Helpers\SAMLAuth;
use App\Http\Controllers\OneLoginController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::middleware('web')->group(function() {
    Route::get('/auth/saml/{site_name}', [SAMLAuth::class, 'requestLogin']);
    Route::get('/auth/saml/{site_name}/metadata', [SAMLAuth::class, 'metadata']);
    Route::get('/auth/saml/{site_name}/logout', [SAMLAuth::class, 'logout']);
    Route::post('/auth/saml/{site_name}/consume', [OneLoginController::class, 'consume']);
});
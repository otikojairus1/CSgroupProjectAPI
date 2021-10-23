<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterUsersController;
use App\Http\Controllers\ContributionController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [RegisterUsersController::class, 'store']);
Route::post('/login', [RegisterUsersController::class, 'login']);
Route::post('/create/contribution', [ContributionController::class, 'contribute']);
Route::post('/view/contribution', [ContributionController::class, 'show']);
Route::get('/all/contributions', [ContributionController::class, 'index']);
Route::post('/delete/contribution', [ContributionController::class, 'destroy']);
Route::post('/update/contribution', [ContributionController::class, 'update']);
Route::get('/verified/contribution', [ContributionController::class, 'verifiedContributions']);

Route::post('/pay', [ContributionController::class, 'pay']);
Route::post('/v2/74aqaGu3sd4/callback', [ContributionController::class, 'callback']);

Route::get('/completed/transactions', [ContributionController::class, 'completed']);
Route::get('/pending/transactions', [ContributionController::class, 'pending']);
Route::get('/all/transactions', [ContributionController::class, 'all']);
Route::post('/search/contribution', [ContributionController::class, 'search']);


<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
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

// Route::get('/', function () {
// return view('welcome');
// });
Route::get('/', function () {
    return view('vendor.l5-swagger.index', [
        'documentation' => 'default', // ya tumhara custom documentation name
        'urlToDocs' => route('l5-swagger.default.docs'),
        'configUrl' => null,
        'validatorUrl' => null,
        'useAbsolutePath' => false,
    ]);
});
Route::get('/routess', function () {
    // Capture the output of Artisan command
    Artisan::call('route:list');

    // Get the output string
    $output = Artisan::output();

    // Return as plain text so you can see it in browser
    return response($output, 200)
        ->header('Content-Type', 'text/plain');
});
Route::get('/cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    Artisan::call('optimize:clear');
    return "Cleazred!";
});
Route::get('/generate-swagger', function () {
    if (!app()->environment('local')) {
        abort(403, 'Forbidden');
    }

    Artisan::call('l5-swagger:generate');
    return "Swagger docs regenerated!";
});


Route::get('/list', function () {
    Artisan::call('route:list');

    return response()->make(
        '<pre>' . Artisan::output() . '</pre>',
        200,
        ['Content-Type' => 'text/html']
    );
});

Route::get('/api/documentation', function () {
    return redirect('/docs');
});

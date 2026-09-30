<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/file/{file}', function ($file) {
    if (Storage::exists($file)) {
        return Storage::download($file);
    } else {
        abort(400, 'File ' . $file . ' not found');
    }
})->where('file', '.*');
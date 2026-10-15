<?php

use App\Http\Controllers\BoletoController;
use App\Http\Controllers\RemessaController;
use App\Http\Controllers\RetornoController;
use Illuminate\Support\Facades\Route;

Route::apiResource('boleto', BoletoController::class);

Route::apiResource('remessa', RemessaController::class);

Route::apiResource('retorno', RetornoController::class);
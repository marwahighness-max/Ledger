<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LedgerController;

Route::get('/', [LedgerController::class, 'index'])->name('ledger.index');

Route::post('/plan', [LedgerController::class, 'updatePlan'])->name('ledger.plan.update');

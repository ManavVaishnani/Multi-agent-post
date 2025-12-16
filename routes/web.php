<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MultiAgentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/agent-dashboard', [MultiAgentController::class, 'index'])->name('agent.dashboard');
Route::post('/agent-dashboard/run', [MultiAgentController::class, 'generate'])->name('agent.run');
Route::get('/agent-dashboard/result/{runId}', [MultiAgentController::class, 'getResult'])->name('agent.result');

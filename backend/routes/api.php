<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\TopicoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('login', [TopicoController::class, 'login'])->middleware('throttle:20,1');
    Route::middleware('topico.auth')->group(function (): void {
        Route::get('products', [InventoryController::class, 'products']);
        Route::post('products', [InventoryController::class, 'saveProduct']);
        Route::put('products/{product}', [InventoryController::class, 'saveProduct']);
        Route::get('movements', [InventoryController::class, 'movements']);
        Route::post('movements', [InventoryController::class, 'movement']);
        Route::get('prescriptions', [InventoryController::class, 'prescriptions']);
        Route::post('prescriptions', [InventoryController::class, 'prescribe']);
        Route::post('prescriptions/{prescription}/dispense', [InventoryController::class, 'dispense']);
        Route::patch('encounters/{encounter}/followup', [InventoryController::class, 'followup']);
        Route::get('reports', [InventoryController::class, 'reports']);
        Route::post('patients/import', [InventoryController::class, 'importPatients']);
        Route::get('audit', [InventoryController::class, 'auditLogs']);
        Route::get('me', [TopicoController::class, 'me']);
        Route::post('logout', [TopicoController::class, 'logout']);
        Route::put('password', [TopicoController::class, 'password']);
        Route::get('dashboard', [TopicoController::class, 'dashboard']);
        Route::get('directory', [TopicoController::class, 'directory']);
        Route::get('users', [TopicoController::class, 'users']);
        Route::post('users', [TopicoController::class, 'saveUser']);
        Route::put('users/{user}', [TopicoController::class, 'saveUser']);
        Route::get('patients', [TopicoController::class, 'patients']);
        Route::post('patients', [TopicoController::class, 'savePatient']);
        Route::put('patients/{patient}', [TopicoController::class, 'savePatient']);
        Route::get('patients/{patient}/history', [TopicoController::class, 'history']);
        Route::get('requests', [TopicoController::class, 'requests']);
        Route::post('requests', [TopicoController::class, 'createRequest']);
        Route::patch('requests/{careRequest}', [TopicoController::class, 'updateRequest']);
        Route::get('encounters', [TopicoController::class, 'encounters']);
        Route::post('encounters', [TopicoController::class, 'createEncounter']);
    });
});

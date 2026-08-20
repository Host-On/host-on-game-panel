<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\HostOn\BootstrapController;

/*
|--------------------------------------------------------------------------
| Host-On Games Bootstrap Routes
|--------------------------------------------------------------------------
|
| Endpoint: /api/hoston/bootstrap
|
| These routes are called by freshly provisioned customer VMs to retrieve
| their Wings bootstrap configuration. Authentication is performed using a
| one-time, expiring bootstrap token rather than a session.
|
*/

Route::post('/api/hoston/bootstrap', [BootstrapController::class, 'bootstrap']);

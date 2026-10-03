<?php

use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

/*
| Dashboard JSON API, loaded by bootstrap/app.php under /dashboard/api/v1 with the "web"
| middleware: requests share the dashboard session and must send the CSRF token.
| The dashboard calls it through App.api in public/assets/dashboard/js/app.js.
*/

Route::get('/status', StatusController::class)->name('status');

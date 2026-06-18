<?php

use App\Http\Controllers\Dashboard\ReceptionDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:reception
*/

registerDashboardPages('reception', 'reception.', ReceptionDashboardController::class, 'reception');

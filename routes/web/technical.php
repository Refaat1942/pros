<?php

use App\Http\Controllers\Dashboard\TechnicalDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:technical
*/

registerDashboardPages('technical', 'technical.', TechnicalDashboardController::class, 'technical');

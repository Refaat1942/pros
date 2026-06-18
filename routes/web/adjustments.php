<?php

use App\Http\Controllers\Dashboard\AdjustmentsDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:adjustments
*/

registerDashboardPages('adjustments', 'adjustments.', AdjustmentsDashboardController::class, 'adjustments');

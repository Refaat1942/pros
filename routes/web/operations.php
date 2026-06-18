<?php

use App\Http\Controllers\Dashboard\OperationsDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:operations
*/

registerDashboardPages('operations', 'operations.', OperationsDashboardController::class, 'operations');

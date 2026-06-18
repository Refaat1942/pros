<?php

use App\Http\Controllers\Dashboard\AdminDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:admin
*/

registerDashboardPages('admin', 'admin.', AdminDashboardController::class, 'admin');

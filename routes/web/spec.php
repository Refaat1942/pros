<?php

use App\Http\Controllers\Dashboard\SpecDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:spec
*/

registerDashboardPages('spec', 'spec.', SpecDashboardController::class, 'spec');

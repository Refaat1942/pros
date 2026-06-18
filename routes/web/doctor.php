<?php

use App\Http\Controllers\Dashboard\DoctorDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:doctor
*/

registerDashboardPages('doctor', 'doctor.', DoctorDashboardController::class, 'doctor');

<?php

use App\Http\Controllers\Department\DepartmentStaffController;
use Illuminate\Support\Facades\Route;

if (! function_exists('registerDepartmentStaffRoutes')) {
    /**
     * مسارات إدارة موظفي القسم — لمدير القسم فقط (صفحة staff).
     */
    function registerDepartmentStaffRoutes(
        string $uriPrefix,
        string $routeNamePrefix,
        string $dashboardKey,
    ): void {
        Route::prefix($uriPrefix)
            ->middleware(['auth', 'dashboard.guard'])
            ->name($routeNamePrefix)
            ->group(function () use ($dashboardKey) {
                Route::middleware("dashboard.page:{$dashboardKey},staff")->group(function () {
                    Route::get('staff/catalog-list-visibility', [DepartmentStaffController::class, 'catalogListVisibility'])
                        ->name('staff.catalog-list-visibility');
                    Route::get('staff/role-pages', [DepartmentStaffController::class, 'rolePages'])
                        ->name('staff.role-pages');
                    Route::post('staff', [DepartmentStaffController::class, 'store'])
                        ->name('staff.store');
                    Route::put('staff/{user}', [DepartmentStaffController::class, 'update'])
                        ->name('staff.update');
                    Route::patch('staff/{user}/toggle', [DepartmentStaffController::class, 'toggleStatus'])
                        ->name('staff.toggle');
                    Route::delete('staff/{user}', [DepartmentStaffController::class, 'destroy'])
                        ->name('staff.destroy');
                    Route::post('staff/{user}/reset-password', [DepartmentStaffController::class, 'resetPassword'])
                        ->name('staff.reset-password');
                });
            });
    }
}

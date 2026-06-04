<?php

use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDepositController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\AdminMaintenanceController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\NetworkController;
use App\Http\Controllers\Api\ReinvestController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Regal Markets API
|--------------------------------------------------------------------------
*/

// ---- Public -----------------------------------------------------------------
Route::get('/maintenance-status', [MiscController::class, 'maintenanceStatus']);
Route::get('/public-settings', [MiscController::class, 'publicSettings']);
Route::get('/ranks', [MiscController::class, 'ranks']);

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('maintenance');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

// ---- Authenticated members --------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [MiscController::class, 'updateProfile']);
    Route::put('/profile/password', [MiscController::class, 'changePassword']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/wallet-transactions', [MiscController::class, 'walletTransactions']);

    // Deposits
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::post('/deposits', [DepositController::class, 'store']);

    // Network
    Route::get('/network/tree', [NetworkController::class, 'tree']);
    Route::get('/network/stats', [NetworkController::class, 'stats']);

    // Bonus / ROI logs
    Route::get('/logs/roi', [LogController::class, 'roi']);
    Route::get('/logs/sponsor', [LogController::class, 'sponsor']);
    Route::get('/logs/matching', [LogController::class, 'matching']);

    Route::get('/announcements', [MiscController::class, 'announcements']);

    // Actions blocked during maintenance + when frozen
    Route::middleware(['not.frozen', 'maintenance'])->group(function () {
        Route::get('/withdrawals/config', [WithdrawalController::class, 'config']);
        Route::get('/withdrawals', [WithdrawalController::class, 'index']);
        Route::post('/withdrawals', [WithdrawalController::class, 'store']);

        Route::get('/transfers', [TransferController::class, 'index']);
        Route::post('/transfers/self', [TransferController::class, 'self']);
        Route::post('/transfers/member', [TransferController::class, 'member']);

        Route::post('/reinvest', [ReinvestController::class, 'store']);
    });

    // ---- Admin --------------------------------------------------------------
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/deposits', [AdminDepositController::class, 'index']);
        Route::post('/deposits/{deposit}/approve', [AdminDepositController::class, 'approve']);
        Route::post('/deposits/{deposit}/reject', [AdminDepositController::class, 'reject']);

        Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
        Route::post('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve']);
        Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject']);

        Route::get('/members', [AdminMemberController::class, 'index']);
        Route::get('/members/{user}', [AdminMemberController::class, 'show']);
        Route::get('/members/{user}/tree', [AdminMemberController::class, 'tree']);
        Route::post('/members/{user}/freeze', [AdminMemberController::class, 'freeze']);
        Route::post('/members/{user}/adjust-wallet', [AdminMemberController::class, 'adjustWallet']);
        Route::post('/members/{user}/rank', [AdminMemberController::class, 'editRank']);
        Route::post('/members/{user}/reset-password', [AdminMemberController::class, 'resetPassword']);

        Route::get('/settings', [AdminSettingController::class, 'index']);
        Route::put('/settings', [AdminSettingController::class, 'update']);

        Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);

        Route::get('/maintenance', [AdminMaintenanceController::class, 'status']);
        Route::post('/maintenance/toggle', [AdminMaintenanceController::class, 'toggle']);

        Route::get('/logs/matching', [AdminLogController::class, 'matching']);
        Route::get('/logs/sponsor', [AdminLogController::class, 'sponsor']);
        Route::get('/logs/audit', [AdminLogController::class, 'audit']);

        Route::get('/reports/{type}', [AdminReportController::class, 'export']);
    });
});

<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\CreditController as AdminCreditController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ReconController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScanReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dns');
});

Route::post('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
Route::get('/about', [ContentController::class, 'about'])->name('about');
Route::get('/faq', [ContentController::class, 'faq'])->name('faq');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active-user'])->group(function () {
    Route::get('/dns', [DnsController::class, 'index'])->name('dns.index');
    Route::get('/network/public-ip', [DnsController::class, 'publicIp'])->name('network.public-ip');
    Route::post('/dns/lookup', [DnsController::class, 'lookup'])->name('dns.lookup');
    Route::post('/recon/start', [ReconController::class, 'start'])->name('recon.start');
    Route::post('/recon/scan/{service}', [ReconController::class, 'scan'])->name('recon.scan');
    Route::get('/recon/history', [ReconController::class, 'index'])->name('recon.history.index');
    Route::get('/recon/history/{history}', [ReconController::class, 'show'])->name('recon.history.show');
    Route::get('/recon/history/{history}/export/json', [ReportController::class, 'json'])->name('recon.export.json');
    Route::get('/recon/history/{history}/export/pdf', [ReportController::class, 'pdf'])->name('recon.export.pdf');

    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
    Route::get('/scanner/history', [ScannerController::class, 'history'])->name('scanner.history');
    Route::get('/scanner/compare', [ScannerController::class, 'compare'])->name('scanner.compare');
    Route::get('/scanner/scans/{scan}', [ScannerController::class, 'show'])->name('scanner.show');
    Route::post('/scanner/scans', [ScanController::class, 'store'])->name('scanner.scans.store');
    Route::get('/scanner/scans/{scan}/status', [ScanController::class, 'status'])->name('scanner.scans.status');
    Route::get('/scanner/scans/{scan}/results', [ScanController::class, 'results'])->name('scanner.scans.results');
    Route::get('/scanner/scans/{scan}/export/json', [ScanReportController::class, 'json'])->name('scanner.scans.export.json');
    Route::get('/scanner/scans/{scan}/export/csv', [ScanReportController::class, 'csv'])->name('scanner.scans.export.csv');
    Route::get('/scanner/scans/{scan}/export/pdf', [ScanReportController::class, 'pdf'])->name('scanner.scans.export.pdf');
    Route::post('/scanner/scans/{scan}/cancel', [ScanController::class, 'cancel'])->name('scanner.scans.cancel');
    Route::post('/scanner/scans/{scan}/rerun', [ScanController::class, 'rerun'])->name('scanner.scans.rerun');
    Route::delete('/scanner/scans/{scan}', [ScanController::class, 'destroy'])->name('scanner.scans.destroy');

    Route::get('/membership', [MembershipController::class, 'index'])->name('membership.index');
    Route::post('/membership/request-upgrade', [MembershipController::class, 'requestUpgrade'])->name('membership.request-upgrade');
    Route::get('/credits', [MembershipController::class, 'credits'])->name('credits.index');
    Route::get('/login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/credits', [AdminUserController::class, 'adjustCredits'])->name('users.credits');
        Route::get('/credits', [AdminCreditController::class, 'index'])->name('credits.index');
        Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::get('/features', [AdminFeatureController::class, 'index'])->name('features.index');
        Route::post('/features', [AdminFeatureController::class, 'store'])->name('features.store');
        Route::put('/features/{feature}', [AdminFeatureController::class, 'update'])->name('features.update');
        Route::get('/content', [AdminContentController::class, 'index'])->name('content.index');
        Route::put('/content/{page}', [AdminContentController::class, 'updateAbout'])->name('content.about');
        Route::post('/faqs', [AdminContentController::class, 'storeFaq'])->name('faqs.store');
        Route::put('/faqs/{faq}', [AdminContentController::class, 'updateFaq'])->name('faqs.update');
        Route::delete('/faqs/{faq}', [AdminContentController::class, 'destroyFaq'])->name('faqs.destroy');
        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit.index');
    });
});

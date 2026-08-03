<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\StudentApprovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DosenSidangController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\MahasiswaTaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ActionItemController;
use App\Http\Controllers\PdfCommentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickReviewController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

// ------------------------------------------------------------------ auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    // Forgot / reset password.
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

    // Registrasi mandiri mahasiswa.
    Route::get('/register', [RegisterController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Persetujuan registrasi mahasiswa (dosen/admin).
    Route::middleware('role_or_permission:dosen|admin')->group(function () {
        Route::get('/approval', [StudentApprovalController::class, 'index'])->name('approval.index');
        Route::post('/approval/{mahasiswa}/approve', [StudentApprovalController::class, 'approve'])->name('approval.approve');
        Route::post('/approval/{mahasiswa}/reject', [StudentApprovalController::class, 'reject'])->name('approval.reject');
    });

    // Fase D: dosen mencatat sidang / riwayat menguji (termasuk mahasiswa orang lain).
    Route::middleware('role_or_permission:dosen|admin')->group(function () {
        Route::get('/dosen-sidang', [DosenSidangController::class, 'index'])->name('dosen-sidang.index');
        Route::post('/dosen-sidang', [DosenSidangController::class, 'store'])->name('dosen-sidang.store');
        Route::delete('/dosen-sidang/{sidang}', [DosenSidangController::class, 'destroy'])->name('dosen-sidang.destroy');
    });
    Route::get('/dashboard/dosen/mahasiswa-list', [DashboardController::class, 'dosenMahasiswaList'])->name('dashboard.dosen.mahasiswa-list');
    Route::get('/dashboard/dosen/sidang-list', [DashboardController::class, 'dosenSidangList'])->name('dashboard.dosen.sidang-list');
    Route::get('/dashboard/dosen/sidang-list/export', [ExportController::class, 'exportSidangPdf'])->name('dashboard.dosen.sidang-list.export');

    // ---------------------------------------------------------- profil
    Route::get('/profil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profil', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/profil/{user}', [ProfileController::class, 'show'])->name('profile.show');

    // ------------------------------------------------------ detail & fase TA
    Route::get('/mahasiswa-ta/{mahasiswaTa}', [MahasiswaTaController::class, 'show'])->name('mahasiswa-ta.show');
    Route::post('/mahasiswa-ta/{mahasiswaTa}/fase', [MahasiswaTaController::class, 'updateFase'])
        ->name('mahasiswa-ta.fase');

    // -------------------------------------------------- jadwal bimbingan
    Route::get('/jadwal-bimbingan', [SchedulingController::class, 'index'])->name('scheduling.index');

    // -------------------------------------------------- quick review (dosen)
    Route::get('/quick-review', [QuickReviewController::class, 'index'])->name('quick-review.index');
    Route::post('/quick-review/{logbook}/approve-next', [QuickReviewController::class, 'approveNext'])->name('quick-review.approve-next');
    Route::post('/quick-review/{logbook}/revisi-next', [QuickReviewController::class, 'revisiNext'])->name('quick-review.revisi-next');
    Route::post('/quick-review/{logbook}/build-feedback', [QuickReviewController::class, 'buildFeedbackFromComments'])->name('quick-review.build-feedback');
    Route::post('/feedback-templates', [QuickReviewController::class, 'storeTemplate'])->name('feedback-templates.store');
    Route::delete('/feedback-templates/{template}', [QuickReviewController::class, 'destroyTemplate'])->name('feedback-templates.destroy');

    // ---------------------------------------------------- action items (mahasiswa)
    Route::post('/logbook/{logbook}/action-items', [ActionItemController::class, 'store'])->name('action-items.store');
    Route::post('/logbook/{logbook}/action-items/{item}/toggle', [ActionItemController::class, 'toggle'])->name('action-items.toggle');
    Route::delete('/logbook/{logbook}/action-items/{item}', [ActionItemController::class, 'destroy'])->name('action-items.destroy');

    // ---------------------------------------------------- global search + import
    Route::get('/search', [UtilityController::class, 'globalSearch'])->name('global-search');
    Route::post('/admin/import-mahasiswa', [UtilityController::class, 'importMahasiswa'])->name('admin.import-mahasiswa');

    // ------------------------------------------------------ workspace
    Route::get('/workspace/{mahasiswaTa}', [WorkspaceController::class, 'index'])->name('workspace.index');
    Route::post('/workspace/{mahasiswaTa}/files', [WorkspaceController::class, 'store'])->name('workspace.store');
    Route::get('/workspace/files/{file}/download', [WorkspaceController::class, 'download'])->name('workspace.download');
    Route::get('/workspace/files/{file}/preview', [WorkspaceController::class, 'preview'])->name('workspace.preview');
    Route::patch('/workspace/files/{file}', [WorkspaceController::class, 'update'])->name('workspace.update');
    Route::delete('/workspace/files/{file}', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');

    // ---------------------------------------------------- chat (Fase 9)
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/start', [ChatController::class, 'start'])->name('chat.start');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}', [ChatController::class, 'store'])->name('chat.store');
    Route::post('/chat/{conversation}/attach-options', [ChatController::class, 'attachOptions'])->name('chat.attach-options');
    Route::put('/chat/{conversation}/{message}', [ChatController::class, 'update'])->name('chat.update');

    // -------------------------------------------------- announcements (Fase 9)
    Route::get('/pengumuman', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/pengumuman/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/pengumuman', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/pengumuman/{announcement}/report', [AnnouncementController::class, 'report'])->name('announcements.report');
    Route::post('/pengumuman/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('announcements.read');
    Route::post('/pengumuman/{announcement}/remind', [AnnouncementController::class, 'remindUnread'])->name('announcements.remind');

    // ---------------------------------------------------- notifikasi
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifikasi/dropdown', [NotificationController::class, 'dropdown'])->name('notifications.dropdown');
    Route::post('/notifikasi/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/notifikasi/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    // ---------------------------------------------------------- logbook
    Route::get('/logbook', [LogbookController::class, 'index'])->name('logbook.index');
    Route::get('/logbook/create', [LogbookController::class, 'create'])->name('logbook.create');
    Route::post('/logbook', [LogbookController::class, 'store'])->name('logbook.store');
    Route::get('/logbook/create-revisi', [LogbookController::class, 'createRevisi'])->name('logbook.create-revisi');
    Route::post('/logbook/revisi', [LogbookController::class, 'storeRevisi'])->name('logbook.store-revisi');
    Route::get('/logbook/feedback', [LogbookController::class, 'feedback'])->name('logbook.feedback');
    Route::put('/logbook/{logbook}/feedback-note', [LogbookController::class, 'updateFeedbackNote'])->name('logbook.feedback-note');

    Route::get('/logbook/{logbook}', [LogbookController::class, 'show'])->name('logbook.show');
    Route::get('/logbook/{logbook}/edit', [LogbookController::class, 'edit'])->name('logbook.edit');
    Route::put('/logbook/{logbook}', [LogbookController::class, 'update'])->name('logbook.update');
    Route::delete('/logbook/{logbook}/lampiran', [LogbookController::class, 'removeLampiran'])->name('logbook.remove-lampiran');
    Route::delete('/logbook/{logbook}/catatan', [LogbookController::class, 'removeCatatan'])->name('logbook.remove-catatan');
    Route::post('/logbook/{logbook}/submit', [LogbookController::class, 'submit'])->name('logbook.submit');
    Route::post('/logbook/{logbook}/approve', [LogbookController::class, 'approve'])->name('logbook.approve');
    Route::post('/logbook/{logbook}/revisi', [LogbookController::class, 'requestRevisi'])->name('logbook.request-revisi');

    Route::get('/logbook/{logbook}/pdf', [LogbookController::class, 'pdf'])->name('logbook.pdf');
    Route::get('/logbook/{logbook}/catatan-pdf', [LogbookController::class, 'catatanPdf'])->name('logbook.catatan-pdf');
    Route::get('/logbook/{logbook}/pdf/burn', [LogbookController::class, 'burnPdf'])->name('logbook.pdf.burn');
    Route::get('/logbook/{logbook}/pdf/viewer', [LogbookController::class, 'viewer'])->name('logbook.pdf-viewer');
    Route::get('/logbook/{logbook}/pdf/comments', [LogbookController::class, 'comments'])->name('logbook.pdf.comments');
    Route::post('/logbook/{logbook}/pdf/comments', [LogbookController::class, 'storeComment'])->name('logbook.pdf.store-comment');

    Route::post('/pdf-comments/{comment}/resolve', [PdfCommentController::class, 'resolve'])->name('pdf-comments.resolve');
    Route::delete('/pdf-comments/{comment}', [PdfCommentController::class, 'destroy'])->name('pdf-comments.destroy');

    // ---------------------------------------------------------- export
    Route::get('/logbook/export/pdf/{mahasiswaTa}', [ExportController::class, 'exportPdf'])->name('logbook.export.pdf');
    Route::get('/logbook/export/excel/{mahasiswaTa}', [ExportController::class, 'exportExcel'])->name('logbook.export.excel');

    // ---------------------------------------------------------- admin
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/tas', [AdminController::class, 'tas'])->name('tas');
        Route::post('/tas', [AdminController::class, 'storeTa'])->name('tas.store');
        Route::put('/tas/{mahasiswaTa}', [AdminController::class, 'updateTa'])->name('tas.update');

        Route::get('/entries', [AdminController::class, 'entries'])->name('entries');
        Route::post('/bulk', [AdminController::class, 'bulkAction'])->name('bulk');

        Route::get('/sidangs', [AdminController::class, 'sidangs'])->name('sidangs');
        Route::post('/sidangs', [AdminController::class, 'storeSidang'])->name('sidangs.store');
        Route::delete('/sidangs/{sidang}', [AdminController::class, 'destroySidang'])->name('sidangs.destroy');
        Route::post('/tas/{mahasiswaTa}/status', [AdminController::class, 'updateStatusTa'])->name('tas.status');

        Route::get('/institusi', [AdminController::class, 'institution'])->name('institution');
        Route::post('/institusi', [AdminController::class, 'updateInstitution'])->name('institution.update');
    });
});

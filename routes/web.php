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
use App\Http\Controllers\FinalizationController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\LogbookHarianController;
use App\Http\Controllers\MahasiswaTaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ActionItemController;
use App\Http\Controllers\PdfCommentController;
use App\Http\Controllers\ProfilPerusahaanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuickReviewController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\SeminarSubmissionController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
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
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:6,1')->name('login.attempt');

    // Forgot / reset password.
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');

    // Registrasi mandiri mahasiswa.
    Route::get('/register', [RegisterController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1')->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/switch', [DashboardController::class, 'switchDashboard'])->name('dashboard.switch');

    // -------------------------------------------------- verifikasi email
    Route::get('/email/verify', fn () => view('auth.verify-email'))->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route('dashboard');
    })->middleware(['signed'])->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware(['throttle:6,1'])->name('verification.send');

    // Persetujuan attachment dosen (mahasiswa pilih dosen → dosen setujui/tolak).
    Route::middleware('role_or_permission:dosen|admin')->group(function () {
        Route::get('/approval', [StudentApprovalController::class, 'index'])->name('approval.index');
        Route::post('/approval/invite', [StudentApprovalController::class, 'invite'])->name('approval.invite');
        Route::post('/approval/{mahasiswaTa}/approve', [StudentApprovalController::class, 'approve'])->name('approval.approve');
        Route::post('/approval/{mahasiswaTa}/reject', [StudentApprovalController::class, 'reject'])->name('approval.reject');
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
    Route::put('/profil/program/{mahasiswaTa}', [ProfileController::class, 'updateProgram'])->name('profile.program');
    Route::get('/profil/pilih-dosen', [ProfileController::class, 'selectDosen'])->name('profile.select-dosen');
    Route::post('/profil/pilih-dosen', [ProfileController::class, 'storeDosen'])->name('profile.store-dosen');
    Route::get('/profil/{user}', [ProfileController::class, 'show'])->name('profile.show');

    // ------------------------------------------------------ detail & fase TA
    Route::get('/mahasiswa-ta/{mahasiswaTa}', [MahasiswaTaController::class, 'show'])->name('mahasiswa-ta.show');
    Route::post('/mahasiswa-ta/{mahasiswaTa}/fase', [MahasiswaTaController::class, 'updateFase'])
        ->name('mahasiswa-ta.fase');

    // ------------------------------------------------------ detail & fase KP
    Route::get('/mahasiswa-kp/{mahasiswaTa}', [MahasiswaTaController::class, 'show'])->name('mahasiswa-kp.show');
    Route::post('/mahasiswa-kp/{mahasiswaTa}/fase', [MahasiswaTaController::class, 'updateFase'])
        ->name('mahasiswa-kp.fase');

    // ------------------------------------------------------ logbook harian KP
    Route::get('/kp/{mahasiswaTa}/logbook-harian', [LogbookHarianController::class, 'index'])->name('logbook-harian.index');
    Route::get('/kp/{mahasiswaTa}/logbook-harian/create', [LogbookHarianController::class, 'create'])->name('logbook-harian.create');
    Route::post('/kp/{mahasiswaTa}/logbook-harian', [LogbookHarianController::class, 'store'])->name('logbook-harian.store');
    Route::get('/kp/{mahasiswaTa}/logbook-harian/{logbookHarian}/edit', [LogbookHarianController::class, 'edit'])->name('logbook-harian.edit');
    Route::put('/kp/{mahasiswaTa}/logbook-harian/{logbookHarian}', [LogbookHarianController::class, 'update'])->name('logbook-harian.update');
    Route::delete('/kp/{mahasiswaTa}/logbook-harian/{logbookHarian}', [LogbookHarianController::class, 'destroy'])->name('logbook-harian.destroy');

    // ------------------------------------------------------ profil perusahaan KP
    Route::get('/kp/{mahasiswaTa}/profil-perusahaan', [ProfilPerusahaanController::class, 'index'])->name('profil-perusahaan.index');
    Route::put('/kp/{mahasiswaTa}/profil-perusahaan', [ProfilPerusahaanController::class, 'update'])->name('profil-perusahaan.update');

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
    Route::get('/workspace', [WorkspaceController::class, 'roleIndex'])->name('workspace.role');
    Route::get('/workspace/{mahasiswaTa}', [WorkspaceController::class, 'index'])->name('workspace.index');
    Route::post('/workspace/{mahasiswaTa}/files', [WorkspaceController::class, 'store'])->name('workspace.store');
    Route::get('/workspace/files/{file}/download', [WorkspaceController::class, 'download'])->name('workspace.download');
    Route::get('/workspace/files/{file}/preview', [WorkspaceController::class, 'preview'])->name('workspace.preview');
    Route::patch('/workspace/files/{file}', [WorkspaceController::class, 'update'])->name('workspace.update');
    Route::delete('/workspace/files/{file}', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');

    // -------------------------------------------------- workspace pribadi (dosen) — redirect ke /workspace
    Route::get('/workspace-saya', fn () => redirect()->route('workspace.role'))->name('workspace.personal');
    Route::post('/workspace-saya', [WorkspaceController::class, 'personalStore'])->name('workspace.personal-store');

    // -------------------------------------------------- pemberian bahan seminar/sidang
    Route::get('/seminar-submission/{mahasiswaTa}/create', [SeminarSubmissionController::class, 'create'])->name('seminar-submission.create');
    Route::post('/seminar-submission/{mahasiswaTa}', [SeminarSubmissionController::class, 'store'])->name('seminar-submission.store');
    Route::get('/seminar-submission/{submission}', [SeminarSubmissionController::class, 'show'])->name('seminar-submission.show');
    Route::get('/seminar-submission/{submission}/edit', [SeminarSubmissionController::class, 'edit'])->name('seminar-submission.edit');
    Route::put('/seminar-submission/{submission}', [SeminarSubmissionController::class, 'update'])->name('seminar-submission.update');
    Route::put('/seminar-submission/{submission}/hardcopy-note', [SeminarSubmissionController::class, 'updateHardcopyNote'])->name('seminar-submission.hardcopy-note');
    Route::get('/seminar-submission/{submission}/undangan/download', [SeminarSubmissionController::class, 'downloadUndangan'])->name('seminar-submission.undangan-download');
    Route::get('/seminar-submission/{submission}/materi/download', [SeminarSubmissionController::class, 'downloadMateri'])->name('seminar-submission.materi-download');
    Route::post('/seminar-submission/{submission}/convert-to-sidang', [SeminarSubmissionController::class, 'convertToSidang'])->name('seminar-submission.convert-to-sidang');

    // -------------------------------------------------- finalisasi TA/KP
    Route::get('/finalisasi/{mahasiswaTa}', [FinalizationController::class, 'index'])->name('finalization.index');
    Route::post('/finalisasi/{mahasiswaTa}', [FinalizationController::class, 'store'])->name('finalization.store');
    Route::get('/finalisasi/review', [FinalizationController::class, 'review'])->name('finalization.review');
    Route::post('/finalisasi/{finalization}/approve/{item}', [FinalizationController::class, 'approveItem'])->name('finalization.approve');
    Route::post('/finalisasi/{finalization}/reject/{item}', [FinalizationController::class, 'rejectItem'])->name('finalization.reject');
    Route::post('/finalisasi/{finalization}/unlock/{item}', [FinalizationController::class, 'unlockItem'])->name('finalization.unlock');
    Route::post('/finalisasi/{finalization}/nilai', [FinalizationController::class, 'inputNilai'])->name('finalization.nilai');

    // -------------------------------------------------- grup & cross-link (dosen)
    Route::middleware('role_or_permission:dosen|admin')->group(function () {
        Route::get('/grup', [GroupController::class, 'index'])->name('groups.index');
        Route::post('/grup', [GroupController::class, 'store'])->name('groups.store');
        Route::post('/grup/{group}/invite', [GroupController::class, 'invite'])->name('groups.invite');
        Route::post('/grup/{group}/join', [GroupController::class, 'join'])->name('groups.join');
        Route::post('/grup/{group}/approve', [GroupController::class, 'approve'])->name('groups.approve');
        Route::post('/grup/{group}/reject', [GroupController::class, 'reject'])->name('groups.reject');
    });

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
    Route::prefix('admin')->name('admin.')->middleware('role_or_permission:admin|system_admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');

        // Persetujuan registrasi dosen oleh admin.
        Route::get('/approve-dosen', [AdminController::class, 'dosenApprovals'])->name('approve-dosen');
        Route::post('/approve-dosen/{dosen}/approve', [AdminController::class, 'approveDosen'])->name('approve-dosen.approve');
        Route::post('/approve-dosen/{dosen}/reject', [AdminController::class, 'rejectDosen'])->name('approve-dosen.reject');

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
        Route::post('/institusi/test-mail', [AdminController::class, 'testMail'])->name('institution.test-mail');
    });

    // -------------------------------------------------- system admin (khusus)
    Route::prefix('admin/system')->name('admin.system.')->middleware('role:system_admin')->group(function () {
        // Kelola admin lain.
        Route::get('/admins', [AdminController::class, 'systemAdmins'])->name('admins');
        Route::post('/admins', [AdminController::class, 'storeSystemAdmin'])->name('admins.store');
        Route::delete('/admins/{user}', [AdminController::class, 'destroySystemAdmin'])->name('admins.destroy');
        Route::post('/admins/{user}/reset-password', [AdminController::class, 'resetSystemAdminPassword'])->name('admins.reset-password');

        // Paket & override per user (hanya system admin).
        Route::get('/users/{user}/plan', [AdminController::class, 'planSettings'])->name('users.plan');
        Route::post('/users/{user}/plan', [AdminController::class, 'updatePlanSettings'])->name('users.plan.update');
    });
});

# [0.7.0](https://github.com/relooplab/thesis-logbook-management/compare/v0.6.0...v0.7.0) (2026-08-05)


### Features

* institution workspace for sharing files per directory level ([76f35b4](https://github.com/relooplab/thesis-logbook-management/commit/76f35b42d29c82a56061537a524040a5470f57f0))

# [0.6.0](https://github.com/relooplab/thesis-logbook-management/compare/v0.5.3...v0.6.0) (2026-08-05)


### Bug Fixes

* remove demo account credentials from login page ([08bb3ec](https://github.com/relooplab/thesis-logbook-management/commit/08bb3ecb47e9d8123674c44a38721fd53579f812))


### Features

* institution directory subscriptions, admin scopes, and storage top-ups (v0.5.4) ([231b3d0](https://github.com/relooplab/thesis-logbook-management/commit/231b3d0c337029cefc461ee332f081d434a73ad6))
* single affiliation rule, per-context institution resolution, and sub-admin hierarchy ([6f610ec](https://github.com/relooplab/thesis-logbook-management/commit/6f610ec085f57c2eccafbb83f3aac0f9b16b0db4))

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.4] - 2026-08-05

### Added

#### Institution Subscription + Admin-Scope (Prodi/Fakultas) + Storage Top-up
- New `directory_subscriptions` table — plans can be assigned to directory nodes (university/faculty/department/study_program), with hierarchical coverage (a subscription at a parent node automatically covers all descendants).
- New `user_storage_addons` table — individual storage top-ups that are **always additive** on top of any base quota (institution or individual plan).
- New `admin_scopes` table — restricts an admin account to specific study programs/departments/faculties. No rows = full institution (existing behavior unchanged).
- `Feature::storageLimitMb()` now resolves: **override admin > directory subscriptions (institution) > individual plan > free plan**, plus **always adds** storage addons.
- `Feature::directoryStorageLimitMb()` — sums quotas from all active directory subscriptions across different branches (deduplicated when multiple affiliations resolve to the same subscription).
- `Feature::directorySubscriptionActive()` — checks if a directory node (or any ancestor) is covered by an active subscription.
- `Feature::validateDirectorySubscriptionNoOverlap()` — rejects assigning a subscription to a node whose ancestor OR descendant already has an active subscription.
- `Feature::institutionHasActiveDirectorySubscription()` — checks if an institution has at least one active directory subscription (gate for admin creation).
- New **"Langganan Direktori"** page (`/admin/system/directory-subscriptions`) for System Admins — assign plans to directory nodes with no-overlap validation, and cancel subscriptions.
- Admin creation in institution mode now requires: (1) an active directory subscription for the institution, and (2) each admin_scope (if any) must be covered by an active subscription.
- New sidebar menu "Langganan Direktori" for System Admins.

#### Institution Isolation (Security Patch)
- `AdminController::users()` now filters by `institution_id` for regular admins in institution mode — admins can no longer see users from other institutions.
- `AdminController::storeUser()` automatically sets `institution_id` to the acting admin's institution (system_admin can choose explicitly).
- `AdminController::destroyUser()`, `resetPassword()`, `approveDosen()`, `rejectDosen()` now reject cross-institution operations.
- `AdminController::tas()`, `sidangs()`, `entries()`, `bulkAction()` now filter data to the acting admin's institution.
- `AdminController::storeTa()` and `storeSidang()` automatically set `institution_id` for regular admins.
- New `canManageUser()` and `canManageTa()` helpers centralize cross-institution authorization.

#### Admin-Scope Data Filtering (Fase D)
- Admins with active `admin_scopes` are now restricted to users/TA/sidangs/entries whose affiliations match one of their scopes (OR).
- Admins without `admin_scopes` retain full-institution access (existing behavior unchanged).
- `canManageUser()` also checks admin_scopes — a scoped admin cannot delete/reset users outside their scope.

#### Subscription Expiry Notifications
- New `directory:notify-expiring-subscriptions` command — notifies all `system_admin` users when a directory subscription is expiring (H-7 and H-1) or has just expired (within the last day).
- New `SubscriptionExpiringNotification` class — sends database + email notifications with the node name (prodi/fakultas), expiry date, and a link to the subscription management panel.
- Scheduled daily at 08:00 Asia/Jakarta (consistent with other reminders).
- `DirectorySubscription` model: added `scopeName()` and `scopeLabel()` helpers to resolve human-readable node names.

#### Single Affiliation Rule for Students
- **Mahasiswa (students) are now restricted to exactly ONE university affiliation** — when a dosen invites or approves a student, all existing affiliations are **replaced** with the dosen's affiliation (previously they were added, allowing multi-affiliation).
- **Dosen (lecturers) may still have multiple affiliations** (unchanged).
- `OrganizationalDirectoryService::attachUserToUniversity()` now accepts a `$replaceAll` parameter — when `true`, all existing affiliations are detached before attaching the new one.
- `StudentApprovalController::copyUniversityToStudent()` now passes `$replaceAll = true` to enforce the single-affiliation rule for students.
- This ensures the scenario "dosen A from prodi X, student from prodi Y" cannot occur in the normal flow — the student always follows the dosen's affiliation.

#### Per-Context Institution Resolution (fixing `Institution::active()` singleton)
- `Institution` model now provides `forInstitutionId($id)`, `forUser($user)`, and `current()` — all resolve the institution that is **relevant to the current context** instead of the global first-row singleton.
- `active()` is unchanged and remains the global fallback for pre-auth, console commands, and queue workers without user context.
- `flush()` now accepts an optional `$institutionId` to flush only the specific institution's cache key.
- `AdminController::institution()`, `updateInstitution()`, `testMail()` now use `Institution::current()` — an admin from institution B can no longer overwrite institution A's settings.
- The 3 logbook FormRequests (`StoreLogbookEntryRequest`, `UpdateLogbookEntryRequest`, `StoreRevisiRequest`) and `SeminarSubmissionController` (4 points) now use `Institution::current()` so upload size/type limits follow the acting user's institution.
- 5 notification classes (`ActivityNotification`, `ReminderNotification`, `SeminarSubmissionNotification`, `WeeklyDigestNotification`, `InactivityReminderNotification`) now call `Institution::forUser($notifiable)->applyToConfig()` at the start of `toMail()` — queue workers resolve the correct mail/branding config for the recipient.
- `rekap-bimbingan.blade.php` and `catatan-perbaikan.blade.php` now use `Institution::forUser($mahasiswaTa->pembimbing1)` so document headers reflect the supervising dosen's institution.
- `logbook/create.blade.php`, `logbook/edit.blade.php`, `logbook/create-revisi.blade.php` now use `Institution::current()` so the displayed upload limit matches backend validation.
- `guest.blade.php` and `AppServiceProvider::boot()` are intentionally **unchanged** — they remain the global fallback.

#### Security Fixes (Code Review)
- **CRITICAL — `AdminController::bulkAction()` wrong-table filter**: The institution filter was querying `MahasiswaTa` for all actions, but `approve`/`revisi`/`delete` operate on `LogbookEntry` IDs (separate auto-increment tables). This allowed a regular admin to approve/reject/delete entries from other institutions by sending entry IDs directly. Now the filter correctly routes through `whereHas('mahasiswaTa', ...)` for LogbookEntry actions, and `MahasiswaTa` only for `assign_dosen`.
- **MEDIUM — `ProfileController::show()` cross-institution info disclosure**: `isAdmin()` (which includes regular `admin` role) returned `true` without checking `institution_id`, allowing admin A to view full profiles of users from institution B. Now regular admins in institution mode are blocked (403) from viewing users outside their institution; `system_admin` remains platform-level.
- **LOW/MEDIUM — `AdminController::sidangs()` dosenList not filtered**: The penguji dropdown showed all dosen across institutions to scoped admins. Now `dosenList` and `mahasiswaList` are filtered by the acting admin's institution (same pattern as `tas()`).
- **LOW — `AdminController::storeSidang()` missing penguji role validation**: `penguji_id` was only validated with `exists:users,id`, allowing non-dosen users to be assigned as penguji. Now uses `$this->roleRule('dosen')` like `storeTa()`/`updateTa()`.

#### Admin Hierarchy (Sub-Admin Creation)
- New `admin.create-admin` permission — controls which admins can create sub-admins.
- New `AdminController::storeSubAdmin()` — allows a scoped admin (with `admin_scopes`) to create admin accounts **below their scope** (e.g., a faculty admin can create department/prodi admins).
- Validation rules:
  - Only active in institution mode.
  - Creator must have at least 1 active `admin_scope`.
  - Each new admin's scope must be a **descendant** of at least one of the creator's scopes (cannot be wider or outside).
  - Target node must still be covered by an active `directory_subscriptions`.
  - Creator must have the `admin.create-admin` permission.
- New route `POST /admin/sub-admins` (guarded by `permission:admin.users`).
- New "Tambah Admin (Sub-Admin)" form on the users page for scoped admins.
- Fixed bug in `Feature::institutionHasActiveDirectorySubscription()` — the `break` statement was preventing the walk-up to higher ancestors (e.g., prodi not subscribed but faculty/university is).

#### Institution Workspace (Berbagi File per Level Direktori)
- New `institution_workspaces`, `institution_workspace_files`, and `institution_workspace_allowed_users` tables.
- Workspaces can be created at university/faculty/department/study_program level, tied to active `directory_subscriptions`.
- **Access rules:**
  - **Dosen**: only access workspace at their own prodi (default `hierarchical` mode = all dosen in same prodi), or via custom grant.
  - **Admin**: all admins at the same node (same scope_type + scope_id) can access, upload, delete, and manage access — regardless of who created the workspace.
  - **Admin at different level**: cannot access unless custom-granted.
- **Fingerprint**: files record `uploaded_by` (uploader) and `deleted_by` + `deleted_at` (soft delete).
- New `InstitutionWorkspaceController` with: dashboard grouping per level, workspace detail, create, upload, soft-delete, download/preview, and access management.
- New routes under `/workspace-institusi`.
- New sidebar menu "Workspace Institusi" for dosen.
- New views: `workspace-institusi/index.blade.php` (dashboard grouping), `workspace-institusi/show.blade.php` (detail + files + manage access).
- New `InstitutionWorkspaceTest` (9 tests) — verifies dosen same-prodi access, other-prodi denial, custom grant, admin same-node manage, admin different-level denial, uploader fingerprint, and dosen cannot upload.

### Changed
- `Feature::storageLimitMb()` precedence: override admin (unchanged, absolute) > directory subscriptions (institution) > individual plan > free plan, + storage addons always added.
- `AdminController::systemAdmins()` now loads `institution` and `adminScopes` relations, and passes `$institutions` to the view.
- `admin/system-admins.blade.php` now shows institution and scope count per admin, and the create form includes institution select + dynamic scope rows (institution mode only).
- `User` model: added `storageAddons()` and `adminScopes()` relations.

### Tests
- New `AdminInstitutionIsolationTest` (12 tests) — verifies cross-institution isolation for users, delete, reset-password, dosen approvals, and system_admin platform-level access.
- New `DirectorySubscriptionStorageTest` (14 tests) — verifies storage quota resolution: override > directory > individual plan, addons always additive, multi-branch summation, dedup, no-overlap validation, and individual-mode fallback.
- New `AdminCreationGateTest` (7 tests) — verifies admin creation is blocked without active subscription, allowed with subscription, scope coverage validation, and individual mode unaffected.
- New `AdminScopeFilterTest` (7 tests) — verifies admin without scopes = full institution, admin with scopes = restricted to scope.
- New `OrganizationalDirectoryTest` additions (2 tests) — verifies students are restricted to a single affiliation (replaced on dosen invite/approve) and dosen can retain multiple affiliations.
- New `InstitutionResolutionTest` (8 tests) — verifies per-context institution resolution: admin B cannot overwrite institution A settings, upload limits follow the acting user's institution, queued notifications use the recipient's institution config, rekap documents show the pembimbing_1 institution, fallback when id not found, and single-institution deployment unchanged.
- New `SubAdminHierarchyTest` (7 tests) — verifies scoped admins can create sub-admins below their scope, cannot create outside/wider scopes, full-institution admins cannot create sub-admins, system_admin still works, and the `admin.create-admin` permission is enforced.
- All 84 tests pass (218 assertions).

## [0.5.3] - 2026-08-05

### Added

#### Role-Based Permission Management ("Kelola Hak Akses")
- New **"Kelola Hak Akses"** page (`/admin/system/permissions`) for System Admins — a permission matrix per role with checkboxes, plus plan feature settings (label, price, storage limit, export/import toggles).
- New `AdminController::permissions()`, `updatePermissions()`, and `updatePlanFeatures()` methods.
- New routes: `admin.system.permissions`, `admin.system.permissions.update`, `admin.system.plans.update`.
- New sidebar menu "Kelola Hak Akses" for System Admins.
- The permissions page is deliberately gated only by `role:system_admin` (not a permission) so a System Admin cannot accidentally lock themselves out of the page.

#### Spatie Permission System (26 Permissions)
- New migration `2026_08_05_000000_create_permissions_and_assign_roles.php` creates **26 permissions** across 12 groups:
  - Logbook: `logbook.create`, `logbook.review`
  - Workspace: `workspace.upload`, `workspace.delete`, `workspace.manage-others`
  - Export/Import: `export.pdf`, `export.excel`, `import.excel`
  - Seminar: `seminar.submit`, `seminar.review`
  - Finalization: `finalization.submit`, `finalization.approve`
  - Sidang: `sidang.record`
  - Communication: `announcement.create`, `chat.send`
  - Admin: `admin.users`, `admin.tas`, `admin.sidangs`, `admin.institution`, `admin.bulk-review`
  - Storage: `storage.manage`
  - Groups: `groups.create`, `groups.invite`
  - Approval: `approval.manage`
  - System: `system.admins`, `system.plans`
- Role → permission assignments:
  - **system_admin**: all 26 permissions
  - **admin**: all except `system.*` (24 permissions)
  - **dosen**: 15 permissions (review, workspace, export, seminar.review, finalization.approve, sidang, announcement, chat, storage, groups, approval)
  - **mahasiswa**: 6 permissions (logbook.create, workspace.upload/delete, seminar.submit, finalization.submit, chat.send)

#### Granular Permission Middleware on Admin Routes
- Admin routes are now grouped by permission: `admin.users` (users + dosen approvals), `admin.tas`, `admin.bulk-review`, `admin.sidangs`, `admin.institution`.
- System Admin routes grouped by permission: `system.admins`, `system.plans`.
- `Feature::has()` now checks the mapped permission via `$user->hasPermissionTo()` when a feature has a corresponding permission.
- Sidebar admin menu items (Pengguna, Persetujuan Dosen, Data TA, Review Massal, Sidang, Institusi, Kelola Admin) are now gated with `@can()`.
- Admin dashboard "Data TA Terbaru → Kelola" link gated with `@can('admin.tas')`.

### Changed
- `DatabaseSeeder` now calls `syncPermissions()` on every seed to keep role-permission assignments consistent with the migration.
- `User` model: removed `examiner_supervisor_names` from `$fillable` and `$casts`.

### Removed
- Migration `2026_08_05_000100_drop_examiner_supervisor_names.php` drops the unused `examiner_supervisor_names` column from `users` (feature removed in v0.5.1).

## [0.5.2] - 2026-08-05

### Added

#### "Penyimpanan Saya" (My Storage) Page for Dosen
- New `StorageController` and `storage/index.blade.php` page — dosen can view their storage quota usage across all supervised students' programs (TA/KP).
- Lists all workspace files and KP daily-logbook photos that count against the dosen's quota, grouped by student.
- Dosen can **delete** a supervised student's workspace file or KP daily-logbook photo to manage storage.
- The student is notified via `ActivityNotification` when a file is deleted.
- New routes: `storage.index`, `storage.destroy-workspace`, `storage.destroy-logbook-harian` (guarded by `role_or_permission:dosen|admin` and `isPembimbing` authorization).
- New "Penyimpanan Saya" sidebar menu for dosen/admin.

#### Storage Quota Enforced Against the Supervising Dosen
- Storage quota is now **charged to the supervising dosen** (Pembimbing 1, fallback Pembimbing 2) instead of the student.
- `StorageUsageService` expanded to count: workspace files, logbook attachments, revision notes, KP daily-logbook photos, seminar materials, finalization files, and profile photos (dosen + supervised students).
- New `assertCanUpload()` — rejects uploads with HTTP 422 when the dosen's quota would be exceeded. Applied to logbook uploads, revisions, workspace uploads (student + personal dosen), and finalization files.
- New `formatBytes()` helper for readable byte display.
- `Feature::storageLimitMb()` now falls back to the **free plan** (5 GB) when the user has no plan/subscription, instead of returning 0 (unlimited).

#### KP Daily-Logbook Photo Serving
- New `logbook-harian.foto` route serves logbook-harian photos directly from the `local` disk (previously used the `public` disk URL).

### Changed

#### Workspace UI
- Student workspace and dosen personal workspace now show a **quota progress bar** (used vs. limit) with color-coded status.
- Removed the "Bab" filter from the student workspace page (kept Type filter).
- Dosen personal workspace ("My Workspace") now uses a **drag & drop upload** with file list, remove option, and upload progress bar.

#### Revisi Table UX (create-revisi)
- New revision form now starts with **5 empty rows** (previously just 1).
- Status defaults to the first option (e.g. "Sudah") instead of an empty "—" placeholder.
- Pressing **Enter** in a table input adds a new row and focuses its first input.
- "Tambah Baris" button moved below the table.

#### Route & Controller Fixes
- `GET /finalisasi/review` is now defined **before** `GET /finalisasi/{mahasiswaTa}` to fix route shadowing that made the review page unreachable.
- `AdminController::updateInstitution` now deletes the **old institution logo** before storing a new one (prevents orphaned files).
- `RegisterController` wraps the email verification send in `bestEffort()` so an SMTP failure no longer blocks registration.

#### PruneOrphanFiles Command
- Now scans **all** file types on the `local` disk: logbook attachments, revision notes, workspace (student + dosen), seminar materials, finalization files, KP logbook-harian photos, and institution logos.
- Also scans the `public` disk (`profiles/`) for orphaned profile photos.
- Deletes orphaned files older than the cutoff, logging each to the `audit` channel.

### Fixed
- Logbook attachment `size` is now stored on upload/update/revision and cleared on removal, so quota accounting reflects actual file sizes.
- KP logbook-harian photo URLs now use the new `logbook-harian.foto` route (works after the disk/serving change).

### Tests
- `StudentApprovalTest`: updated to the new active/verified flow — invited students register as `active`, and approval operates on a `MahasiswaTa` with `pending_approval` status.
- `GroupTest`: builds the full faculty → department → study program hierarchy for `prodi`-level group creation.
- `OrganizationalDirectoryTest`: lecturer registration now redirects to `verification.notice` (email verification flow).
- `AuditSmokeTest`: disables CSRF validation for POST requests.

## [0.5.1] - 2026-08-04

### Added

#### Phase/Milestone Selection in Onboarding
- Students now select their **current phase/milestone** (TA or KP) when choosing a lecturer via the **"Pilih Dosen"** page.
- The phase dropdown is dynamically filtered by program type (TA/KP) via JavaScript.
- Lecturers can adjust the phase when approving an attachment request (new "Fase/Milestone" field on the approval page).

#### Dedicated Seminar Submission Notification
- New `SeminarSubmissionNotification` class — sends a **rich email** with full schedule details (type, student, date, time, location, invitation-as) plus a database notification.
- Replaces the generic `ActivityNotification` for seminar/examination material submissions.

#### Demo Account for New Onboarding Flow
- Added `mahasiswa_active@example.com` (NIM 200401004) — an **active** student (email verified, no lecturer attached) to demo the "Pilih Dosen" flow.
- Seeder now sets `email_verified_at` and proper `registration_status` for all demo accounts (verified/active/approved).

### Changed

#### Inactive Student Cleanup (Safer)
- `students:delete-inactive` now only deletes `active` students who **never submitted a lecturer attachment request** (`whereDoesntHave('mahasiswaPrograms')`).
- Students with a pending or rejected program are **no longer deleted** — a slow lecturer response is not the student's fault.

#### Registration Form Simplified
- Removed the **"Saya sebagai penguji"** (I am also an examiner) checkbox and supervisor-name fields from the registration form.
- Removed `examiner_supervisor_names` handling from `RegisterController` (the field remains in the DB for legacy data but is no longer set at registration).

#### Profile Validation for Students
- `identifier` (NIM) and `whatsapp` are now **required** for students when updating their profile.

#### Rejected Program Handling
- Students whose attachment request was rejected can now **select a new lecturer** (rejected programs are excluded from the duplicate-program check).
- Student dashboard shows a **"Permintaan Anda sebelumnya ditolak dosen"** banner when the current program is rejected, directing them to choose another lecturer.

#### Dashboard & UI
- Lecturer dashboard "Today's Actions" now counts **pending attachment requests** (MahasiswaTa with `pending_approval` status) instead of pending user registrations.
- Student dashboard seminar button text changed from "Isi Bahan" to **"Kirim Bahan"**.
- Approval page shows the student-selected phase with an option to adjust it.

### Fixed
- `LogbookHarianKp` seeder now includes `created_by` for the demo KP daily logbook entry.

### Documentation
- Updated `README.md` (fixed GitHub links `hafizhul` → `relooplab`, added new demo account).
- Updated `docs/MODE-SPEC.md`, `docs/USER-GUIDE.md`, and `docs/USER-GUIDE-EN.md` (removed "as examiner" registration option, added phase selection).

## [0.5.0] - 2026-08-04

### Added

#### Email Verification (MustVerifyEmail)
- `User` model now implements `Illuminate\Contracts\Auth\MustVerifyEmail`.
- New email verification page (`/email/verify`) with resend-link functionality.
- Verification routes: `verification.notice`, `verification.verify` (signed), `verification.send` (throttled 6/min).
- Login redirects unverified users to the verification notice page.
- Migration `set_email_verified_for_existing_users` marks all existing users as verified (grandfathered in).

#### New Student Onboarding Flow (Select Lecturer)
- Students self-register and are immediately **active** after email verification (no longer `pending`).
- New **"Pilih Dosen"** page (`/profil/pilih-dosen`) — students select their preferred supervisor/examiner (Pembimbing 1/2, Penguji 1/2) and program type (TA/KP).
- Selecting a lecturer creates a `MahasiswaTa` with status `pending_approval`; the selected lecturer is notified.
- Lecturer approval page (`/approval`) now lists **lecturer attachment requests** (MahasiswaTa with `pending_approval` status) instead of pending user registrations.
- Lecturers can approve (assign role, set title/KP location, target sessions) or reject attachment requests.
- New `MahasiswaTa` statuses: `pending_approval` and `ditolak`.
- New `User` helpers: `isActive()` (email verified, no lecturer) and `isVerified()` (has an approved lecturer).
- Migration `set_mahasiswa_registration_status` backfills existing students: those with a lecturer → `verified`, others → `active`.

#### Inactive Student Cleanup
- New command `students:delete-inactive` — deletes `active` students (email verified, no lecturer attached) who have not become `verified` within 1 month.
- Scheduled daily at 03:30 (Asia/Jakarta) with `withoutOverlapping()`.
- Deletion runs in a transaction (MahasiswaTa cascade + user).

#### Security Hardening
- Rate limiting on auth endpoints: login (6/min), register (5/min), forgot-password (3/min), reset-password (5/min), verification resend (6/min).
- Forgot-password endpoint now returns a **uniform response** regardless of whether the email exists (prevents email enumeration).
- Login blocks `rejected` students and `pending` lecturers with clear error messages.
- PDF comment resolve/delete restricted to the actual entry reviewer via new `LogbookEntryPolicy::isReviewer()` (narrower than `view`, which also covers cross-link group access).

### Changed
- `RegisterController`: students register as `active` (email verification follows); lecturers remain `pending` (admin approval).
- `LoginController`: email verification check + rejected/pending account handling.
- `StudentApprovalController`: reworked from user-registration approval to **lecturer attachment approval** (`approve`/`reject` now operate on `MahasiswaTa`).
- `ProfileController`: added `selectDosen()` and `storeDosen()` methods.
- `DashboardController`: student dashboard shows status banners ("Pilih Dosen" / "Menunggu Persetujuan Dosen").
- `PdfCommentController`: uses `LogbookEntryPolicy::isReviewer()` for resolve/delete authorization.
- `routes/web.php`: added verification routes, `profile.select-dosen`/`profile.store-dosen`, throttling middleware, and updated approval route bindings to `MahasiswaTa`.
- `routes/console.php`: scheduled `students:delete-inactive` daily at 03:30.
- `MahasiswaTa`: added `STATUS_PENDING_APPROVAL` and `STATUS_DITOLAK` constants.
- Updated `README.md`, `docs/MODE-SPEC.md`, `docs/USER-GUIDE.md`, and `docs/USER-GUIDE-EN.md`.

### Tests
- Existing tests need updates to reflect the new registration/approval flow (pending → active/verified).

## [0.4.0] - 2026-08-04

### Added

#### System Admin (Super Admin) Role
- New `system_admin` role — the highest role that manages other admin accounts and system configuration.
- `User::isSystemAdmin()` helper and updated `User::isAdmin()` to include `system_admin` (all existing admin checks automatically apply).
- **Manage Admins** page (`/admin/system/admins`) — System Admins can create, reset passwords, and delete operational admin accounts.
- Plan/subscription settings moved to System Admin only (`/admin/system/users/{user}/plan`).
- System Admin has full access to all admin menus (users, dosen approvals, thesis data, bulk review, examinations, institution).
- Admin role cannot create, delete, or reset passwords of other admin/system_admin accounts.
- Admin users page hides the "Admin" role checkbox and "Plan" link for non-System-Admin users.
- "admin" and "system_admin" role labels are hidden from all profile pages.

#### Demo Account
- Added `systemadmin@example.com` (identifier `SYS001`) for System Admin demos.

### Changed
- `User` model: added `isSystemAdmin()`, updated `isAdmin()` to `hasAnyRole(['admin', 'system_admin'])`.
- `routes/web.php`: admin group now uses `role_or_permission:admin|system_admin`; new `admin/system` group for System Admin only.
- `AdminController`: added `systemAdmins()`, `storeSystemAdmin()`, `destroySystemAdmin()`, `resetSystemAdminPassword()`; protected `storeUser()`, `destroyUser()`, `resetPassword()`.
- `layouts/app.blade.php`: added "Kelola Admin" sidebar menu for System Admins.
- `admin/users.blade.php`: role display filtered, admin role option & plan link restricted to System Admins.
- `profile/show.blade.php` & `profile/index.blade.php`: filter out `admin` and `system_admin` role labels.
- Updated `README.md`, `docs/USER-GUIDE.md`, and `docs/USER-GUIDE-EN.md`.

### Tests
- All existing tests continue to pass.

## [0.3.1] - 2026-08-04

### Documentation
- Updated `README.md` with v0.3 features (seminar submission, finalization, separated dashboards, hidden admin label).
- Updated `docs/USER-GUIDE.md` (Bahasa Indonesia) with new features, role workflows, and FAQ.
- Updated `docs/USER-GUIDE-EN.md` (English) with new features, role workflows, and FAQ.

### Demo Accounts
- Added `dosen4@example.com` (NIDN 0004040404) as a second examiner for the demo TA.
- Demo TA (`mahasiswa@example.com`) now has phase `proposal` and examiners 1/2 assigned, enabling seminar submission & finalization demos.
- `dosen4@example.com` is linked to Universitas Nusantara and the demo lecturer group.

## [0.3.0] - 2026-08-04

### Added

#### Seminar/Examination Material Submission
- Students submit seminar/examination materials (invitation letter + presentation material) with date, time, and location.
- Seminar type is determined automatically from the current thesis/KP phase (Proposal Seminar, Results Seminar, Final Examination, KP Seminar).
- Material can be uploaded directly or selected from the student's workspace files.
- A "invitation as" option (Supervisor 1/2 or Examiner 1/2) for the invitation letter.
- Hardcopy note field that lecturers can update.
- Notifications to related lecturers (supervisors & examiners) when materials are sent.
- Students can edit a submission until it is converted to an examination record.
- Lecturers can convert a submission into an examination record (selecting examiner & result).
- New models: `SeminarSubmission`, and migrations for `seminar_submissions` + `institutions.seminar_hardcopy_note`.

#### Thesis/Internship Finalization
- Students submit finalization items (TA: abstract, keywords, cover, approval page, full PDF; KP: full PDF only).
- Approval workflow where each item must be approved by both supervisors (Supervisor 1 & 2).
- Per-item reject and unlock actions.
- Final grade input (0–100).
- Automatic phase advancement to "Achievement Unlocked" when all items are approved and the TA phase is `sidang`.
- New models: `ThesisFinalization`, `FinalizationApproval`, and `thesis_finalizations` tables + `finalization` review page.

#### Separated Admin & Lecturer Dashboards
- Users with both `admin` and `dosen` roles can now switch between the two dashboards via a segmented control in the profile picture dropdown.
- The selected dashboard mode is persisted per session (`session('dashboard_mode')`).
- New route `GET /dashboard/switch` and `DashboardController::switchDashboard`.

#### Hidden Admin Label
- The "admin" role label is never shown on any profile page (own or others'), keeping administrative status private.
- Updated `profile/show.blade.php` and `profile/index.blade.php` to filter out the `admin` role.

### Changed
- `DashboardController`: dual-role handling with session-based dashboard mode.
- `routes/web.php`: added `dashboard.switch` route.
- `layouts/app.blade.php`: added dashboard mode switch in the profile dropdown.
- Updated `README.md`, `docs/USER-GUIDE.md`, and `docs/USER-GUIDE-EN.md`.

### Tests
- All existing tests continue to pass.

## [0.2.1] - 2026-08-04

### Security & Access Control

- **Profile access restricted** — `ProfileController::show` now only allows viewing another user's profile when there is a direct relationship (supervisor/student, shared thesis, or shared group). Admin and self always allowed; otherwise `403`.
- **Global search filtered** — `UtilityController::globalSearch` now filters results by direct relationship:
  - Users: only those with a direct relationship to the searcher.
  - Entries: only from theses connected to the searcher (supervisor/examiner/member/direct relation).
  - Files: only from connected theses + the searcher's own personal workspace files.
- **Lecturer logbook list filtered** — `LogbookController::index` now includes theses where the lecturer is an examiner (not just supervisor) and theses from lecturers with a direct relationship (shared group/shared thesis) via the new `User::relatedDosenIds()` helper.
- **Chat between lecturers restricted** — `ChatController::authorizeChat` now allows a lecturer to chat with another lecturer only when there is a direct relationship (shared thesis or shared group). Lecturers without a relationship get `403`.

### Added
- `User::relatedDosenIds()` — returns IDs of lecturers with a direct relationship (same approved group or shared thesis).

### Tests
- All 24 tests pass (65 assertions).

## [0.2.0] - 2026-08-04

### Added

#### Organizational Directory
- Hierarchical directory with 4 levels: University → Faculty → Department → Study Program, with automatic deduplication (unique constraints prevent duplicate university names).
- New models: `University`, `Faculty`, `Department`, `StudyProgram` with full relationships.
- Multi-university support via `user_university` pivot table — a lecturer can be affiliated with multiple universities (`is_primary` marks the primary one).
- `users.nidn` column (unique) for lecturer identity.
- `OrganizationalDirectoryService` for "find or create" logic with case-insensitive deduplication.
- Lecturer registration form with NIDN + university selection/creation.
- Students automatically follow their supervisor's institution — when a lecturer invites or approves a student, the lecturer's university is copied to the student (no re-entry).

#### Lecturer Groups & Cross-linking
- `groups` and `group_members` tables for lecturer groups at university/faculty/department/program level.
- Group invitation workflow with approval: a lecturer creates a group, invites colleagues from the same university, and the invitee must approve before joining.
- Lecturer Groups page (`/grup`) with pending invitations, group creation, my groups, and available groups.
- Group invitation notifications to invited lecturers.

#### Access Control ("Direct Relation Only")
- `User::hasDirectRelation()` helper to determine whether two users have a direct relationship (shared thesis, shared group, or supervisor-student).
- Strengthened `LogbookEntryPolicy::view` and `MahasiswaTaPolicy::viewWorkspace` to only allow access when there is a direct relationship.

#### Lecturer Personal Workspace
- `workspace_files.user_id` (nullable) to support a lecturer's private workspace.
- "My Workspace" page (`/workspace-saya`) with file upload, chapter labels, notes, filter, and search.
- `StorageUsageService` to calculate storage usage (lecturer = personal workspace + supervised students' data).
- `lampiran_size` and `catatan_perbaikan_size` columns on `logbook_entries`.

#### Plan Foundation (not yet active)
- `plans`, `subscriptions`, and `user_plan_overrides` tables as a foundation for future Free/Donation plans.
- `Feature::has('export'/'import')` and `Feature::storageLimitMb()` gates (temporarily disabled — all users can export/import until plans are activated).
- Admin plan settings page (`admin/users/{user}/plan`) to set plan & per-user overrides.

#### Dashboard & UI
- Lecturer dashboard "Institution & Groups" card (university + NPSN, NIDN, group count).
- Student dashboard "University" card.
- Sidebar university badge.
- Profile page shows NIDN (lecturer) and university.
- Admin users table shows each user's university.

#### UX Improvements (Lecturer & Student)
- "Add Student Manually" (email only) — `POST /approval/invite` creates a pending student account.
- Improved approval page with modern design system + manual add form.
- Notification to lecturer when a student registers.
- Lecturer dashboard "Today's Actions" (review, registrations, attention) + quick actions.
- Student dashboard "My Actions" (draft, revision, action items).
- Highlight entries needing action in the logbook list.
- Consistent modern design system across logbook CRUD, workspace, KP daily logbook, and approval pages.
- All new pages are mobile-friendly.

#### Demo Accounts
- Added `dosen3@example.com` (NIDN 0003030303) for group/cross-link demos.
- Added NIDN to existing demo lecturers.
- Seeded demo university "Universitas Nusantara" (Fakultas Teknik → Departemen Teknik Informatika → S1 Teknik Informatika) and group "Dosen Teknik Informatika Universitas Nusantara".

### Changed
- `User` model: added `nidn`, `universities()` (multi), `primaryUniversity()`, `activePlan()`, `planOverride()`, `hasDirectRelation()`.
- `WorkspaceFile` model: added `user_id` and `owner()` relationship.
- `LogbookEntry` model: added `lampiran_size` and `catatan_perbaikan_size`.
- `Feature` support class: added plan-based feature gates (temporarily disabled).
- `DashboardController`: added university & group data for dashboards.
- `StudentApprovalController`: students automatically inherit the lecturer's university on invite/approve.
- `RegisterController`: lecturer registration with NIDN + university directory.
- `WorkspaceController`: personal workspace for lecturers + file authorization.
- `ExportController` / `UtilityController`: plan-based export/import gates (temporarily disabled).
- `AdminController`: plan settings & per-user overrides.
- Updated `README.md`, `docs/USER-GUIDE.md`, `docs/USER-GUIDE-EN.md`, and `docs/MODE-SPEC.md`.

### Fixed
- `OrganizationalDirectoryTest::test_dedup_university_by_name_case_insensitive` no longer depends on the total university count (seeder now adds demo data).

### Tests
- Added `OrganizationalDirectoryTest` (dedup, hierarchy, lecturer registration, student inherits university).
- Added `GroupTest` (create group, invite & approve, direct relation via shared group).
- Added `StudentApprovalTest` (invite by email, duplicate rejection, approve & assign role).
- All 24 tests pass (65 assertions).

[0.5.4]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.5.4
[0.5.3]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.5.3
[0.5.2]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.5.2
[0.5.1]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.5.1
[0.5.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.5.0
[0.4.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.4.0
[0.3.1]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.3.1
[0.3.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.3
[0.2.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.2.0

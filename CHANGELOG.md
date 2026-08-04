# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.4.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.4.0
[0.3.1]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.3.1
[0.3.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.3
[0.2.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.2.0

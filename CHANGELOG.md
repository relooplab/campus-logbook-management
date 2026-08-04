# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.2.0]: https://github.com/relooplab/thesis-logbook-management/releases/tag/v0.2.0
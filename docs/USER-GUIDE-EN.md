# User Guide — Thesis Logbook Management

Welcome to **Thesis Logbook Management**, an application for recording and monitoring the supervision of students' Final Projects (Tugas Akhir / Thesis). This guide explains all application features and the working mechanisms (workflows) for each user role.

---

## Table of Contents

1. [About the Application](#1-about-the-application)
2. [User Roles](#2-user-roles)
3. [Detailed Features Overview](#3-detailed-features-overview)
4. [Role-based Workflows](#4-role-based-workflows)
5. [Frequently Asked Questions (FAQ)](#5-frequently-asked-questions-faq)

---

## 1. About the Application

This application helps supervising/examining lecturers and students monitor the Final Project supervision process digitally. Key features include:

- **Supervision logbook recording** — students record each supervision session, including progress and obstacles.
- **Review workflow** — lecturers approve or request revisions on logbook entries.
- **Continuous revision** — improvements from review results are recorded as separate revision entries.
- **File workspace** — students store and share thesis documents (chapters, revisions, etc.) in one place.
- **Chat & communication** — direct messaging between lecturers and students.
- **Announcements** — official announcements sent to all students.
- **Examination records & examining history** — lecturers record examination/session results.
- **Notifications & supervision health tracking** — indicators of supervision regularity.

### Application Modes

The application runs in one of two modes (configured by the administrator):

| Mode | Description |
|---|---|
| 🌱 **Individual** (default) | Used by **a single lecturer** who records the supervision and examination of their own students. Students can self-register and are then approved by the lecturer. |
| 🏛️ **Institution** | Used by a study program/institution that manages **many lecturers**, students, supervisors, examiners, examination sessions, and official reports. |

The current mode is shown at the top of the sidebar menu, for example the **"Individual"** or **"Institution"** label.

---

## 2. User Roles

| Role | Description |
|---|---|
| **Admin** | Manages users, thesis data, bulk review, examination data, and institution settings. |
| **Lecturer (Dosen)** | Supervises and examines students, reviews logbooks, records examination sessions, and approves student registrations. |
| **Student (Mahasiswa)** | Records supervision logbooks, uploads revisions, manages the workspace, and monitors thesis progress. |

---

## 3. Detailed Features Overview

The following describes the features available in the application, regardless of role (per-role availability is explained in Section 4).

### 3.1 Authentication

- **Login** — Sign in with email and password.
- **Student self-registration** — Students can register themselves (name, email, password). The account is set to **pending** status until approved by a lecturer.
  - There is an **"I am also an examiner"** option — if checked, the student can record examination sessions of other students once approved.
- **Forgot password** — Reset your password via email.
- **Logout** — The logout button is available in the profile menu.

### 3.2 Dashboard

Each role has a summary dashboard:

- **Admin**: statistics for students, lecturers, thesis data, and entries awaiting review; Excel student import; list of recent thesis data.
- **Lecturer**: statistics for total supervision, in progress, completed, examined, and awaiting review; review queue; health indicators; thesis phase management.
- **Student**: unread announcements, supervision health status, milestone journey (phases), thesis title & supervisors, supervision progress, achievements, statistics & streaks, 12-month activity heatmap, and supervision timeline.

### 3.3 Supervision Logbook

A logbook entry records one supervision session.

- **Entry status**: `Draft` → `Submitted` → `Approved` or `Revision`.
- **Entry type**: `Logbook` (supervision session) and `Revision` (improvements from review).
- **Revision round limit**: maximum 3 rounds per entry (a warning appears when reached).
- **Auto-save draft**: logbook input is saved automatically in the browser (localStorage) every 5 seconds.
- **Filters**: status, type, date range, and keyword.

### 3.4 PDF Review & Annotation

- Attached documents (PDF) can be opened in a **PDF viewer**.
- Lecturers can **highlight areas** on the PDF and **add comments** (annotations) per page.
- Comments have an **open/resolved** status.
- Lecturers can **build feedback automatically** from unresolved comments.

### 3.5 File Workspace

- Students can **upload files** (PDF, DOC, DOCX, XLS, XLSX) — max 25 MB, up to 5 files at once, with *drag & drop*.
- Files are tagged with a **Chapter label** (optional) and **notes** (description).
- **Filters** by chapter, file type, and keyword.
- **Preview** for PDFs, **download** for all files.
- Students can **edit metadata** (chapter, notes) and **delete** files.
- **Storage usage** (MB) indicator is displayed.

### 3.6 Chat

- Direct messaging between lecturers and students.
- Lecturers can start a conversation from the **student detail page**.
- The conversation list shows the counterpart, number of unread messages, and the related thesis.
- Messages can be **attached** to related entities (attachment option).

### 3.7 Announcements

- Announcement creators (lecturer/admin) can create announcements sent to students.
- **Students** see unread announcements on the dashboard and can **mark them as read**.
- Creators can view the **report** (number sent, number read) and **remind** those who have not read.

### 3.8 Notifications

- Notifications are received for important activities (new entries, status changes, PDF comments, etc.).
- The **notification bell** in the header shows the unread count; a **dropdown** and **all-notifications page** are available.
- **Real-time notifications** (via Reverb/Pusher) show a toast when entry status changes or a PDF comment is added.

### 3.9 Examination Records & Examining History

- Lecturers record **examination sessions** (Proposal Seminar / Final Examination) for supervised students **or other students** (outside the system).
- Recorded data: student, session type, date, result (Pass / Pass + Revision / Repeat), and supervisor names (max 3).
- **PDF export** of examining history for portfolio/BKD purposes.
- A Final Examination with a **Pass / Pass + Revision** result automatically marks the student as **completed (tamat)**.

### 3.10 Supervision Scheduling

- The **"Schedule Supervision"** page displays a list of lecturers with their supervision schedule links.
- Click a lecturer card to open their schedule link (in a new tab).

### 3.11 Global Search (Cmd+K)

- Quick search across all data (students/lecturers, entries, workspace files).
- Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux), or click the search field in the header.

### 3.12 Profile & Settings

- Every user can update their **profile** (photo, WhatsApp/Telegram/LinkedIn contacts, etc.) and **password**.
- Other users' profile pages can be viewed (linked from various pages).

### 3.13 Supervision Health Indicator

Supervision regularity is calculated from the last supervision date:

| Status | Meaning |
|---|---|
| 🟢 **Healthy** | Last supervision < 15 days |
| 🟡 **Attention** | 15–40 days |
| 🔴 **Critical** | > 40 days or never supervised |

- Lecturers see this indicator on the dashboard (with filters).
- Students see their own supervision health status.
- Students who are inactive for too long may receive an **inactivity email** (marked with a ⚠ icon).

### 3.14 Milestone Journey & Achievements

- **Milestone Journey** shows the student's thesis phase trajectory: Proposal → Data Collection → Analysis → Results Seminar → Examination Draft → Examination → Achievement Unlocked.
- **Achievements** (badges) unlock when a student reaches certain milestones, displayed on the student dashboard.

### 3.15 Import & Export

- **Student import (Excel)** — admin (institution) can bulk-import student data (name, NIM, email, supervisor1_nidn, supervisor2_nidn) with a default supervisor.
- **Summary export** — supervision summary in **PDF** and **Excel** per student.
- **Examining history export** — PDF for lecturers.

### 3.16 Thesis Phase & Status Management

- The thesis **phase** can be updated by the lecturer (from the dashboard or the student detail page).
- **Thesis status**: `Active`, `Completed`, `Inactive` — managed by the admin.

### 3.17 Organizational Directory (University → Faculty → Department → Study Program)

The application has a **hierarchical organizational directory** to map user affiliations:

```
University (universities)
  └── Faculty (faculties)
        └── Department (departments)
              └── Study Program (study_programs)
```

- **Automatic deduplication** — a university name never appears twice; faculty/department/study program are unique within their parent (unique constraints).
- **Lecturer registration** — lecturers register with their **NIDN** and select/create their university (if it already exists, it is reused; if not, it is created).
- **Multi-university** — a lecturer can be affiliated with **multiple universities**.
- **Students automatically follow their supervisor's institution** — when a lecturer invites or approves a student, the lecturer's university is automatically copied to the student (no re-entry).
- **Display** — the university is shown on the dashboard (lecturer & student), sidebar, and profile page.

### 3.18 Lecturer Groups & Cross-linking

Lecturers can form **groups** for collaboration and cross-linking with other lecturers at the same university.

- **Group levels**: University, Faculty, Department, or Study Program.
- **Create group**: a lecturer creates a group and automatically becomes the owner.
- **Invite colleagues**: a lecturer invites other lecturers from the same university (data is not re-entered — selected directly from the directory).
- **Approval**: the invited lecturer must **approve** the invitation before becoming a member.
- **Pending invitations**: lecturers see pending invitations on the **Lecturer Groups** page and can accept/reject them.
- **"Direct relation only" access**: lecturers in the same group (or sharing a thesis) can view each other's supervision data — data is only accessible when there is a direct relationship.

### 3.19 Lecturer Workspace

In addition to the student workspace, lecturers also have a **personal workspace** via the **My Workspace** menu:

- Upload personal files (PDF, DOC, DOCX, XLS, XLSX) — max 25 MB, up to 5 files at once.
- Manage files with chapter labels and notes.
- Filter & search files.
- Only the lecturer concerned can access their personal workspace files.

### 3.20 Dashboard & UI (Institution & Groups)

- The **lecturer dashboard** shows the **"Institution & Groups"** card: university (NPSN), NIDN, and the number of groups joined.
- The **student dashboard** shows the **"University"** card.
- The **sidebar** shows the user's primary university badge.
- The **profile** shows the NIDN (lecturer) and university.

---

## 4. Role-based Workflows

### 4.1 Student Workflow

#### 4.1.1 Registration & Awaiting Approval

1. Open the **Register** page.
2. Fill in **name**, **email**, and **password**.
3. (Optional) Check **"I am also an examiner"** and fill in the supervisor names (max 3) if you want to record examination sessions of other students.
4. Submit → the account is created as **pending** (cannot log in fully yet).
5. Wait for **lecturer approval**. Once approved, you can log in and your thesis data becomes available.

#### 4.1.2 Recording a Supervision Logbook

1. Go to **Dashboard** → click **+ Logbook** (or the **Add Logbook** menu).
2. The **session** number is filled automatically (next session).
3. Fill in **Supervision Date**, **Supervision Topic**, and **Summary of Improvements** (progress & obstacles).
4. (Optional) Attach a file (PDF, etc.).
5. Choose:
   - **Save Draft** — saves as a draft; can be edited and submitted later.
   - **Send to lecturer** — sends directly to the lecturer for review.
6. Input is saved automatically (auto-save) every 5 seconds; the draft is restored if the page is closed.

#### 4.1.3 Responding to Lecturer Review

1. When the lecturer requests a revision, the entry status becomes **Revision**.
2. Open the entry details → click **Create Revision from This Feedback**.
3. Fill in the improvement summary and upload the revision (you can upload a **Correction Notes** file).
4. Send to the lecturer → the lecturer will review again.

#### 4.1.4 Managing the Workspace

1. Open the **Workspace** menu.
2. Click the upload zone (or drag & drop files) — max 5 files, 25 MB per file.
3. Fill in the **Chapter** label (optional) and **Notes**.
4. Click **Upload** → the files appear in the list, grouped by chapter.
5. Use **filters** (chapter, type, keyword) or **search** to find files.
6. Click the **edit** icon to change metadata, or **delete** to remove a file.

#### 4.1.5 Communicating & Monitoring

- **Chat** with your supervising lecturer.
- Read **announcements** and **notifications** regularly.
- Monitor your **milestone journey**, **supervision progress**, and **health indicator** on the dashboard.
- Download your **PDF/Excel summary** of supervision.

#### 4.1.6 (If an Examiner) Recording Examination Sessions

If your account is permitted to be an examiner, you can record examination sessions of other students through the **Record Examination** menu (see the lecturer workflow in 4.2.5).

---

### 4.2 Lecturer Workflow

#### 4.2.1 Approving Student Registrations

1. Open the **Approvals** menu (in the sidebar).
2. View the list of students awaiting approval (status **Pending**).
3. To approve, fill in:
   - **Thesis Title**
   - **Your role** for that student (Supervisor 1 / Supervisor 2 / Examiner 1 / Examiner 2)
   - **Target Sessions**
   - (If the student is marked "wants to be an examiner") check **Allow to be an examiner**
4. Click **Approve & Assign** → the student account is activated and thesis data is created.
5. Alternatively, click **Reject** to reject the registration.

#### 4.2.2 Reviewing Logbooks

1. On the **Dashboard**, check the **Review Queue** (number of entries awaiting review).
2. Click **Review** on an entry, or use the **Quick Review** menu to review them one by one.
3. On the entry:
   - Open **PDF & Annotation** to read the document and add comments to PDF areas.
   - Choose **Approve** to approve, or
   - Fill in **feedback** (required, min 20 characters) then **Request Revision**.
4. When approving with an unopened PDF attachment, the application asks for confirmation first.

#### 4.2.3 Quick Review

A fast mode to review the queue one by one:

1. Open the **Quick Review** menu.
2. Read the entry summary, previous feedback, and previous round annotations.
3. Use the **last feedback** for that student (click to reuse) or **feedback templates**.
4. Click **"Build from Comments"** to generate feedback from unresolved PDF comments.
5. Choose **Approve & Next** or **Revision & Next** to proceed to the next entry.
6. Save feedback as a **template** for reuse.

#### 4.2.4 Managing Phases & Supervision Health

1. On the dashboard, use the **Supervision Health Indicator** to monitor each student's supervision regularity (filter Healthy/Attention/Critical).
2. Use **Thesis Phase Management** to update each student's phase (e.g., from Proposal to Data Collection).
3. Students inactive in supervision can be flagged (⚠) and receive an inactivity email.

#### 4.2.5 Recording Examination Sessions / Examining History

1. Open the **Record Examination** menu.
2. Select a student from the supervision list, or type the **name of a student outside the system**.
3. Fill in **Type** (Proposal Seminar / Final Examination), **Date**, and **Result** (Pass / Pass + Revision / Repeat).
4. (Optional) Fill in the supervisor names of the examined student (max 3).
5. Click **Save Examination** → recorded in the examining history.
6. Use **Export PDF** to download the examining history.

#### 4.2.6 Communicating & Announcing

- **Chat** with supervised students (start from the student detail page).
- Create **announcements** and monitor their read reports; send reminders to those who have not read.
- Monitor **notifications** for new entries and PDF comments.

#### 4.2.7 Main Supervision Workflow (Summary)

```
Student registers → Lecturer approves & assigns
   → Student records a logbook (draft)
   → Student submits to lecturer (submitted)
   → Lecturer reviews: approve OR request revision
   → If revision: Student creates a revision entry → submits again → lecturer reviews
   → Each approval adds an approved session
   → Lecturer monitors health & phase → until completed
```

---

### 4.3 Admin Workflow

#### 4.3.1 Managing Users

1. Open the **Users** menu.
2. **Search/filter** users by name, email, identifier, or role.
3. **Add a user**: fill in name, email, identifier (NIM/NIDN), password, and select a role (admin/lecturer/student).
4. **Reset a user's password** (click **Reset PW**).
5. **Delete** a user when necessary.

#### 4.3.2 Managing Thesis Data

1. Open the **Thesis Data** menu.
2. **Create thesis data**: select a student (without a thesis), fill in the title, and set supervisors 1/2, examiners 1/2, and target sessions.
3. **Edit & assign** thesis data per student (title, supervisors, examiners, target sessions, status).
4. **Bulk action**: check several rows → select a lecturer → **Assign Supervisor 1** in bulk.
5. **Import students (Excel)** from the dashboard: upload a file (name, NIM, email, supervisor1_nidn, supervisor2_nidn) and choose a default supervisor.

#### 4.3.3 Bulk Entry Review

1. Open the **Bulk Review** menu.
2. **Filter** entries by status, type, and keyword.
3. Check the selected entries.
4. Choose a bulk action: **Approve**, **Mark as Revision**, or **Delete**.

#### 4.3.4 Managing Examination Data

1. Open the **Examinations** menu.
2. **Add examination data**: select a student, examining lecturer, type, date, and result.
3. **Delete** examination data when necessary.
4. Note: A Final Examination with a **Pass / Pass + Revision** result automatically marks the student as **completed**.

#### 4.3.5 Managing the Institution Profile

1. Open the **Institution** menu.
2. Fill in institution information: application name, institution name, faculty, study program, address, city, phone, email, website, document footer note, and logo.
3. Configure **upload & template settings**: correction notes template link, max upload size (MB), and allowed file types.
4. Configure **email settings (SMTP)**: mailer, host, port, encryption, username, password, from-address, and from-name.
5. **Send a test email** to verify the SMTP configuration.

#### 4.3.6 Monitoring the Dashboard

- The admin dashboard shows statistics for **students, lecturers, thesis data**, and **awaiting review**.
- A list of **recent thesis data** is displayed for quick monitoring.

---

## 5. Frequently Asked Questions (FAQ)

**Q: What does the "419 Page Expired" error mean?**
A: This error occurs when the session security token (CSRF) is invalid or expired. Solution: reload the login page, and avoid leaving the page open for too long. If it persists, contact the administrator to check the session configuration.

**Q: I am a student, but I cannot log in after registering.**
A: Your account is in **pending** status and awaits lecturer approval. Please contact your lecturer to approve the account.

**Q: How do I edit or submit a draft logbook?**
A: Open the draft entry from the **Logbook** page, click **Edit** to modify it, then **Send to lecturer** to submit.

**Q: I am a lecturer. How do I comment on a PDF?**
A: Open the entry (status **Submitted**) → click **Review PDF & Annotate** → open the PDF, highlight an area, and add a comment. Comments can be turned into automatic feedback.

**Q: What is the file size limit in the workspace?**
A: Max 25 MB per file, up to 5 files at once, in PDF, DOC, DOCX, XLS, XLSX formats.

**Q: How is a student marked as "completed"?**
A: Automatically when a **Final Examination** is recorded with a **Pass** or **Pass + Revision** result, or manually set by the admin.

**Q: What is the health indicator?**
A: An indicator of supervision regularity: 🟢 Healthy (<15 days), 🟡 Attention (15–40 days), 🔴 Critical (>40 days). It helps lecturers and students monitor supervision consistency.

---

## Notes

- Use **Cmd+K / Ctrl+K** for global search.
- Use the **dark/light mode button** in the header to change the theme.
- The sidebar can be collapsed/expanded with the button on its left edge.
- To report issues or suggest improvements, use the **"Send Feedback"** menu.
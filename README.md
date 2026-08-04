<!--
  meta-keywords: laravel, logbook, thesis, mentoring, thesis-management,
  final-project, pdf-annotation, dompdf, reverb, laravel-excel, spatie-permission,
  tailwindcss, docker, mysql, redis, web-application, education
  meta-description: ReLoop Logbook — A comprehensive Laravel 11 web application for
  recording and monitoring undergraduate thesis mentoring sessions with PDF annotations,
  real-time collaboration, and both individual and institutional deployment modes.
-->

# Thesis Logbook Management

![Laravel](https://img.shields.io/badge/Laravel-11-red?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-blue?logo=php&logoColor=white)
![License](https://img.shields.io/github/license/hafizhul/thesis-logbook-management)
![Docker](https://img.shields.io/badge/Docker%20Compose-Ready-blue?logo=docker&logoColor=white)

A comprehensive web application for recording and monitoring undergraduate thesis mentoring sessions between students, supervisors, and examiners. Built to streamline the academic thesis process with modern tooling, real-time collaboration, and flexible deployment options.

**Built on** **Laravel 11** (PHP 8.4 FPM Alpine) + **MySQL 8.4** + **Redis** + **Nginx**, fully containerized with **Docker Compose** (7 microservices).

**Frontend stack:** Blade templates + Tailwind CSS CDN (dark mode first), PDF.js for annotation-capable PDF viewing, Laravel Reverb for real-time updates, and DomPDF + Laravel Excel for document exports. *No Node build step required to run the application.*

### UI & Design System

The application follows a cohesive design system optimized for thesis mentoring workflows:

- **Design tokens** — CSS variables in `public/css/global.css` support dynamic light/dark switching via `.dark` class selector. Includes semantic tokens:
  - Backgrounds: `bg-{base,surface,panel,hover}`
  - Text: `text-{primary,secondary}`
  - Accent colors (rotate per context): `accent-{blue,orange,teal,purple}`
  - Status indicators: `status-{success,danger,info,pending}`
  - Spacing: `p-5`–`p-6`, `gap-5`–`gap-6`
  - Border radius: `rounded-card: 20px`, `rounded-full` for avatars
- **Components** — Stat cards with icon + delta badges, dot-label status badges, and generous whitespace for readability.
- Token definitions in `tailwind.config.js` and runtime CSS are kept in sync for consistency.

---

## Table of Contents

- [Features](#features)
  - [Submission & Review Workflows](#submission--review-workflows)
  - [Dashboards & Monitoring](#dashboards--monitoring)
  - [Deployment Modes](#deployment-modes)
  - [Administration & Access Control](#administration--access-control)
  - [Real-Time Communication](#real-time-communication)
  - [Productivity Features](#productivity-features)
- [Tech Stack](#tech-stack)
- [User Roles & Capabilities](#user-roles--capabilities)
- [Quick Start](#quick-start)
- [Production Deployment with Docker](#production-deployment-with-docker)
- [Configuration](#configuration)
- [Testing & Verification](#testing--verification)
- [Design Principles & Constraints](#design-principles--constraints)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

---

## Features

### Submission & Review Workflows

- **Two submission types**
  - **Logbook entry** — date, session topic, progress notes, and optional PDF attachment for each mentoring session.
  - **Revision entry** — summary of revisions, mandatory PDF files (revision draft + revision notes), and a revision submission timestamp.
- **Multi-stage approval workflow** — Draft → Submitted → Approved (immutable) or Revision requested. Students can edit only draft/revision entries; submitted and approved entries are permanently locked and cannot be modified.
- **PDF viewer with area annotations** — PDF.js renders to interactive canvas with DOM overlay (similar to Google Drive comments). Annotations are stored separately as **W3C Web Annotation JSON** format, independent of the PDF file. Optional **burn-in** feature (FPDI) generates downloadable PDFs with embedded annotations, color-coded by status, commenter info, and auto-generated comment summary.
- **Student workspace** — Individual file management area per student (mini Google Drive equivalent). Students control add/edit/delete; supervisors have read-only + download access. Supports PDF, DOC, DOCX, XLS, XLSX; max 25 MB per file, up to 5 files per batch upload.
- **Quick review mode** — Streamlined review queue with "Approve & Next" / "Request Revision & Next" buttons to process submissions efficiently.
- **Feedback templates & history** — Reusable feedback snippets to accelerate reviews; system displays prior feedback for context.
- **Inline comments to feedback** — Aggregate all unresolved PDF annotations into a draft feedback summary for the student.
- **Student action items** — Break feedback down into a checklist; students track completion and receive "ready to resubmit" hints when all items are done.

### Dashboards & Monitoring

- **Student dashboard** — Gamified experience with activity timeline, milestone tracking (thesis phase progression), 8 achievement badges (auto-unlocked), stat cards with streak counter, and a GitHub-style contribution heatmap (12-month view).
- **Supervisor dashboard** — Interactive stat cards (Total supervised, In progress, Graduated, Examined, Awaiting review), per-student health indicator (green/yellow/red, computed from mentoring frequency, cached 6h), and prioritized review queue.
- **Admin dashboard** — Full system management: user administration, thesis record CRUD, bulk review actions, thesis defense history (for academic reporting), institution profile settings, Excel import for student onboarding, and global search (Cmd+K).

### Deployment Modes

The application supports **two operational modes** (see `docs/MODE-SPEC.md` for full architecture):

#### Individual Mode (default)

Single supervisor manages their own cohort of students. Students self-register via email; the supervisor approves and assigns roles (supervisor 1/2, examiner 1/2). Supervisors can also manually record thesis defenses for external students (student name + up to 3 external examiners).

#### Institution Mode

Multi-tenant deployment with centralized administration. Features institution-level settings, bulk Excel import for student onboarding, coordinator roles, and institution-wide reports. Enabled via `APP_MODE=institution` in `.env`.

#### Mode Comparison

| Aspect | Individual Mode | Institution Mode |
|--------|----------------|------------------|
| `institution_id` | `NULL` (personal data) | Assigned (multi-tenant) |
| Tenant scope | Not active | Active (filtered per institution) |
| Bulk Excel import | ❌ | ✅ |
| Student registration | Auto-approved | Requires supervisor/admin approval |
| Institution-wide reports | ❌ | ✅ |
| Multi-supervisor / multi-institution | ❌ | ✅ |
| Sidebar badge | "Individual" | "Institution" |

**Implementation details:**

- Feature detection via `app/Support/Feature.php` (gates like `Feature::isInstitution()`, `Feature::has('bulk_import')`)
- Tenant isolation via `app/Models/Scopes/InstitutionScope.php` (Laravel Global Scope)
- Student self-registration at `/register`; supervisor approval at `/approval`
- Thesis defense records at `/dosen-sidang` (NIDN/NIP-based)

**Migration from individual to institution mode:**

```bash
php artisan ta:adopt-personal-data \
  --dosen=<user_id> \
  --institution=<institution_id> \
  --dry-run                        # Preview changes without applying
  --include-users                  # Also migrate user.institution_id
  --only=<ta_id>                   # Migrate single thesis (optional)
  --force                          # Skip confirmation prompt
```

All migrations are logged to the audit channel for compliance and rollback traceability.

### Administration & Access Control

- **Roles** — `admin` (system administrator), `dosen` (supervisor/lecturer), `mahasiswa` (student). Multi-role support enabled (e.g., admin + supervisor).
- **Thesis supervision** — Each thesis has up to 2 supervisors (`pembimbing_1`, `pembimbing_2`) and up to 2 examiners (`penguji_1`, `penguji_2`). Examiners can view thesis details and student workspace but cannot approve submissions—approval rights are supervisor-only.
- **Thesis lifecycle** — Three statuses:
  - `aktif` (active) — ongoing thesis work
  - `tamat` (graduated) — marked when final defense is recorded as passed
  - `nonaktif` (inactive) — paused or withdrawn
- **Thesis defense records** — Admin manages thesis defense history (for academic records and compliance reporting). PDF export per supervisor for documentation.
- **User management** — Manual user CRUD, batch import via Excel, admin-initiated password resets, and multi-field profile data.
- **User profiles** — All users can update: name, profile photo, WhatsApp, Telegram, LinkedIn. Supervisors additionally maintain: Google Scholar, ORCID, SINTA, ResearchGate links. Cross-user profile viewing enabled.

### Organizational Directory & Lecturer Groups

- **Hierarchical directory (4 levels)** — Perguruan Tinggi → Fakultas → Departemen → Program Studi, with automatic deduplication (a university name never appears twice).
- **Lecturer registration with NIDN** — lecturers register with their NIDN and select/create their university (with case-insensitive dedup).
- **Multi-university support** — a lecturer can be affiliated with multiple universities.
- **Students automatically follow their supervisor's institution** — when a lecturer invites/approves a student, the student's university is copied from the lecturer (no re-entry).
- **Lecturer groups & cross-linking** — lecturers create groups (university/faculty/department/program level), invite colleagues from the same university, and the invitee must approve before joining.
- **"Direct relation only" access policy** — data is only accessible when there is a direct relationship (shared thesis, shared group, or supervisor-student).
- **Lecturer personal workspace** — each lecturer has their own private file workspace (`/workspace-saya`).

### Real-Time Communication

Two integrated modules provide 1:1 messaging and broadcast announcements.

#### 1:1 Chat

- **Participants** — Supervisor ↔ student (scoped to thesis), or admin ↔ any user. Conversations auto-created on first message; `(user_one_id, user_two_id)` stored in ascending order for deterministic pairing.
- **File attachments** — No file upload; messages reference existing **Workspace files** or **Logbook entries** via polymorphic `attachable` relation. Access control is re-verified at send and view time against existing Workspace/logbook policies—no message link bypasses file permissions.
- **Message editing** — 15-minute edit window; edited messages are flagged with `edited_at` timestamp. Edit locked with `403 Forbidden` after timeout.
- **Real-time delivery** — Powered by Laravel Reverb broadcasting (`MessageSent` event on `conversation.{id}` + `user.{id}` channels). Fallback polling (~15s) ensures chat remains functional if WebSocket disconnects.

#### Announcements

- **Broadcasting** — Supervisors/admins send announcements to their supervised students (all or manual selection per institution mode).
- **Student UX** — Sticky banner on dashboard + database notification + email alert.
- **Reporting** — Senders see read status ("12 of 15 read") with per-recipient `read_at` timestamp. "Remind unread" action re-sends notifications without duplicating the announcement.

#### Access Control

- **Admins** — Chat with any user (scoped to institution in `institution` mode).
- **Supervisors** — Chat with supervised/examined students or admins. Two supervisors cannot directly message each other.
- **Students** — Chat with assigned supervisors/examiners or admins. Students cannot message other students.
- **File attachments** — Re-authorized per thesis: only admins, the file owner (student), or assigned supervisors/examiners can attach. Receiver permissions follow existing Workspace/logbook policies.

> Full route reference, controller mapping, and database schema for the chat and announcement modules are documented in [docs/API.md](docs/API.md).

### Productivity Features

- **Intelligent form auto-fill** — New logbook entries auto-populate next session number and prior topic. Form data auto-saves to localStorage every 5 seconds with recovery on revisit.
- **Password recovery** — Self-service email-based password reset flow.
- **Smart notifications** — Multi-channel: database + email for key events (approval, revision requested, PDF comments, submissions). Real-time dropdown, weekly digest summary, and inactivity reminders with built-in spam prevention.
- **File lifecycle management** — Replaced files are orphaned but retained; scheduled `files:prune-orphans` job removes orphans older than 30 days. PDF comments on replaced files auto-resolve and log the resolution for audit trails.

---

## Tech Stack

| Component | Choice |
|---|---|
| Framework | Laravel 11 |
| PHP | 8.4 (FPM Alpine) |
| Database | MySQL 8.4 |
| Cache / Queue | Redis (+ Predis) |
| Web server | Nginx |
| Mail (dev) | Mailpit |
| Auth | Laravel Sanctum + session login |
| Authorization | Spatie Laravel Permission |
| Exports | DomPDF + Laravel Excel |
| Frontend | Blade + Tailwind CDN (`darkMode:'class'`) |
| PDF viewer | PDF.js via CDN |
| Realtime | Laravel Reverb |
| Timezone | Asia/Jakarta |

---

## User Roles & Capabilities

| Role | Primary Responsibilities |
|---|---|
| **Student (Mahasiswa)** | Submit logbook entries and revisions, manage workspace files, review supervisor feedback, convert feedback into action items/checklist. |
| **Supervisor (Dosen)** | Review submissions in priority queue, approve or request revisions, annotate PDF files, track thesis phase progression, view student workspace, record examination history. |
| **Administrator (Admin)** | User account management, thesis-supervisor assignment, bulk import/export, defense record CRUD, institution settings, system-wide search and reporting. |

---

## Quick Start

### Prerequisites
- PHP ≥ 8.2
- Composer
- Node.js + npm (for PDF viewer assets)

### Local Setup (Without Docker)

```bash
# 1. Install dependencies and build frontend
composer install
npm install && npm run build
php artisan storage:link

# 2. Configure environment and database
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed

# 3. Start the server
php artisan serve
# Application runs at http://127.0.0.1:8000
```

**Faster setup:** Run `./setup-local.sh [port] [host]` to automate all steps above. See script comments for options.

### Demo Accounts (Local/Testing Only)

These accounts are created only when `APP_ENV` is `local` or `testing`. The
production seeder never creates them. Never use the demo password in a
production deployment.

The database seeder creates the following accounts for testing:

| Role | Email | Password |
|---|---|---|
| Admin + Supervisor (NIDN 0001010101) | `admin@example.com` | `password` |
| Administrator Sistem | `administrator@example.com` | `password` |
| Supervisor 2 (NIDN 0002020202) | `dosen2@example.com` | `password` |
| Supervisor 3 (NIDN 0003030303) | `dosen3@example.com` | `password` |
| Student — TA (NIM 200401001) | `mahasiswa@example.com` | `password` |
| Student — KP (pemilik kelompok, NIM 200401002) | `mahasiswa_kp@example.com` | `password` |
| Student — KP (anggota kelompok, NIM 200401003) | `mahasiswa_kp2@example.com` | `password` |

Demo akun berikut terhubung ke **Universitas Nusantara** (Fakultas Teknik → Departemen Teknik Informatika → S1 Teknik Informatika) dan tergabung dalam grup **"Dosen Teknik Informatika Universitas Nusantara"**: `admin@example.com`, `dosen2@example.com`, `dosen3@example.com`.

---

## Production Deployment with Docker

### Prerequisites

**Critical:** Set `APP_KEY` and ensure `APP_KEY`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` are available to Docker Compose:
- Without `APP_KEY`, Laravel fails to boot
- Without Reverb keys, commands crash during container startup
- Docker Compose intentionally fails when these values or database passwords are missing.
- Do not use development defaults or publish Mailpit ports to the internet.

```bash
# Generate and set APP_KEY if needed
grep -q '^APP_KEY=base64' .env || php artisan key:generate
```

### Deployment Steps

```bash
cd <project-root>

# 1. Transfer source code (baked into image, not bind-mounted for security)
tar czf - app config database resources routes bootstrap nginx \
    Dockerfile docker-entrypoint.sh docker-compose.yml \
    composer.json composer.lock package.json artisan .env \
    | ssh deploy@<server> "cd <app-path> && tar xzf -"

# 2. Build application image
ssh deploy@<server> 'cd <app-path> && docker compose build app'

# 3. Start services (app, queue, scheduler, nginx)
ssh deploy@<server> 'cd <app-path> && \
  docker compose up -d --force-recreate app queue scheduler nginx'

# 4. Run migrations
ssh deploy@<server> 'docker exec logbook-ta-app php artisan migrate --force'
# `db:seed` is optional; in APP_ENV=production it does not create demo users.

# 5. Verify all services
ssh deploy@<server> 'cd <app-path> && docker compose ps'
```

### Service Endpoints

| Service | Port | URL |
|---|---|---|
| Application (Nginx) | 8280 | `http://<server-ip>:8280` |
| Mailpit Web UI | 8225 | `http://127.0.0.1:8225` (local only) |
| Mailpit SMTP | 8226 | Local mail testing only |

### Troubleshooting

```bash
# List all application routes
docker exec logbook-ta-app php artisan route:list

# View recent errors
docker exec logbook-ta-app sh -c "grep ERROR storage/logs/laravel.log | tail -20"
```

### Critical Configuration: Nginx & Public Assets

**Shared volume `public_pp`:**
- Nginx and app container share the `public_pp` named volume (defined in `docker-compose.yml`)
- Essential for serving frontend assets (`/build/*`, `/pdfjs/*`, `/css/*`)
- Without it, PDF viewer and CSS fail with 404 errors
- `docker-entrypoint.sh` auto-populates volume from `/public-dist` on first startup, then creates `storage:link`

**PDF.js Worker (Production-critical):**

1. **MIME type mapping** — `nginx/default.conf` maps `.mjs` to `application/javascript`. Without this, browsers reject the PDF worker with MIME-type error.

2. **Cache busting** — Worker served with `?v=<build-timestamp>` (auto-set by `npm run build` via `VITE_WORKER_VERSION`) + `Cache-Control: no-cache`. Prevents stale workers after deploy. *Hard-refresh browser after updates.*

3. **Blade templating** — Viewer config uses `@json(...)` (not `{{ }}`) to inject URLs unescaped into `<script>` blocks. Double-encoding would break JavaScript.

---

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
# Deployment
APP_ENV=production            # Set to 'local' for development
APP_DEBUG=false                # Never enable in production
APP_URL=http://your-domain     # Application base URL

# Application mode
APP_MODE=individual            # 'individual' (default) | 'institution'

# External resource links (customizable per institution)
APP_JADWAL_URL=https://...     # External guidance scheduling system
APP_TEMPLATE_URL=https://...   # External revision template link

# Database
DB_CONNECTION=mysql
DB_HOST=logbook-ta-db        # Docker service name or host IP
DB_PORT=3306
DB_DATABASE=logbook_ta
DB_USERNAME=logbook
DB_PASSWORD=<strong-password>

# Cache & session (Redis)
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=logbook-ta-redis
REDIS_PORT=6379

# Queue & real-time (Redis + Reverb)
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=lbta-prod
REVERB_APP_KEY=<generate-with-artisan>
REVERB_APP_SECRET=<generate-with-artisan>

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com   # Your mail provider
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@example.com
```

**Notes:**
- External links (`APP_JADWAL_URL`, `APP_TEMPLATE_URL`) are accessed via `config('app.jadwal_url')` and `config('app.template_url')`, allowing per-institution customization
- Generate Reverb credentials with `php artisan reverb:install`
- In Docker deployments, use service names (e.g., `logbook-ta-db`) for host names

---

## Testing & Verification

### Manual API Tests

```bash
# Test login (expect 302 redirect to dashboard, then 200 on dashboard)
TOKEN=$(grep -oP 'name="_token" value="\K[^"]+' <<< "$(curl -s localhost:8000/login)")
curl -c cookies.txt -X POST localhost:8000/login \
  -d "_token=$TOKEN" -d "email=mahasiswa@example.com" -d "password=password"

# Test logbook submission (expect 302 redirect after POST)
curl -b cookies.txt -X POST localhost:8000/logbook \
  -F "_token=$TOKEN" \
  -F "tanggal_bimbingan=2026-08-15" \
  -F "topik=Chapter 3 Analysis" \
  -F "progres_kendala=Progress notes here" \
  -F "lampiran=@thesis-chapter3.pdf"

# Test submission approval (expect 302)
curl -b cookies.txt -X POST localhost:8000/logbook/1/approve -d "_token=$TOKEN"

# Test PDF inline viewing (expect 'application/pdf' content-type)
curl -b cookies.txt -I localhost:8000/logbook/1/pdf

# Test PDF annotation creation (expect 201)
curl -b cookies.txt -X POST localhost:8000/logbook/1/pdf/comments \
  -H "Content-Type: application/json" -H "X-CSRF-TOKEN: $TOKEN" \
  -d '{
    "file_type": "draft",
    "page_number": 1,
    "pos_x": 0.1, "pos_y": 0.2, "x2": 0.4, "y2": 0.3,
    "comment": "Clarify this section"
  }'

# Test exports
curl -b cookies.txt -o export.pdf localhost:8000/logbook/export/pdf/1
curl -b cookies.txt -o export.xlsx localhost:8000/logbook/export/excel/1

# View scheduled jobs
php artisan schedule:list

# Run scheduled tasks manually
php artisan logbook:send-reminders --inactive-days=7 --queue-days=3
php artisan ta:weekly-digest
php artisan files:prune-orphans
```

---

## Design Principles & Constraints

- **UI Language** — User-facing text is in **Bahasa Indonesia**. Dark mode is the default theme with `dark:` CSS variants applied to all views.

- **Data Model** — Partial fields are nullable. Revision entries intentionally lack mentoring date/topic/supervisor (revision submission date is used instead).

- **External Scheduling** — No built-in thesis appointment scheduling. All "Jadwal Bimbingan" links point to external systems (`target="_blank"`), allowing institutions to bring their own scheduling system.

- **File Access Control** — Chat file attachments are re-authorized against existing Workspace/logbook policies. Message links never bypass permission checks.

- **Chat Message Editing** — Edit window is 15 minutes. After timeout, `403 Forbidden`. Students cannot chat with each other—only with assigned supervisors/examiners or admins.

- **Docker Image Strategy** — Application source is baked into the image (not bind-mounted) for security. Every source change requires `docker compose build app` + recreate services.

- **Production Settings** — Always set `APP_DEBUG=false` in production. Default timezone is `Asia/Jakarta`; adjust in `.env` if needed.

- **Email Delivery** — Use Mailpit (included in Docker Compose) for local/development testing. For production, configure `MAIL_*` env vars to use your SMTP provider.

---

## Documentation

| Document | Purpose |
|---|---|
| [MODE-SPEC.md](docs/MODE-SPEC.md) | Full architecture of individual vs. institution deployment modes |
| [API.md](docs/API.md) | Route, controller, and database reference for the chat/announcement module |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Development setup, coding standards, and pull request process |
| [CHANGELOG.md](CHANGELOG.md) | Version history and release notes |
| [SECURITY.md](SECURITY.md) | Security policy and vulnerability disclosure |

---

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on the development workflow, coding standards, and how to submit a pull request.

Ways to contribute:

- Report bugs or suggest features via [GitHub Issues](https://github.com/hafizhul/thesis-logbook-management/issues)
- Improve documentation
- Submit bug fixes or new features via pull request

---

## Security

If you discover a security vulnerability, please **do not** open a public issue. Follow the responsible disclosure process described in [SECURITY.md](SECURITY.md).

---

## License

This project is licensed under the **MIT License** — see [LICENSE](LICENSE) for details.

---

## Acknowledgments

Built with [Laravel](https://laravel.com), [PDF.js](https://mozilla.github.io/pdf.js/), [Tailwind CSS](https://tailwindcss.com), and other open-source projects listed in [composer.json](composer.json) and [package.json](package.json).

---

## Support

- **Bug reports & feature requests:** [GitHub Issues](https://github.com/hafizhul/thesis-logbook-management/issues)
- **Questions & discussion:** [GitHub Discussions](https://github.com/hafizhul/thesis-logbook-management/discussions)

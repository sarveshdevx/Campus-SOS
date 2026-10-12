# CampusSOS — Student Project Expo Edition

> **Campus Safety, Incident Reporting & Rapid Emergency Response System**

CampusSOS is a web application designed for campus environments to provide immediate emergency response, confidential safety reporting, and coordinated responder dispatch.

---

## 1. Project Architecture

The application is structured into decoupled, maintainable layers:

```
CampusSOS/
├── frontend/             # React 18, TypeScript, Vite, Lucide Icons, Warm Ivory Design
│   ├── public/           # Static assets & brand icons
│   └── src/
│       ├── assets/       # Visual assets
│       ├── components/   # Common components (FloatingSosButton, Navbar, Footer)
│       ├── features/     # Feature-scoped logic & AuthContext
│       ├── hooks/        # Custom React hooks (useAuth)
│       ├── pages/        # Route pages (Home, Report, Tracker, Admin, Responders, Auth)
│       ├── services/     # API client & domain services (Auth, Incidents, SOS)
│       ├── types/        # TypeScript domain models & API envelopes
│       ├── utils/        # Formatters & validation utilities
│       ├── App.tsx       # Main application component & layout wiring
│       ├── index.css     # Warm ivory design system & elevation styles
│       └── main.tsx      # React DOM client entrypoint
├── backend/              # PHP 8.2+ Modular REST API
│   ├── public/
│   │   └── index.php     # Front controller & request router entrypoint
│   ├── config/           # Database PDO, CORS, and Session configuration
│   ├── routes/           # REST Route definitions & regex URL router
│   ├── controllers/      # Health, Auth, Incident, SOS, Responder, Admin, Location
│   ├── models/           # User, Incident, Location, SosAlert, Responder, AuditLog
│   ├── services/         # Deterministic SOS dispatch, Incident matcher, JSON responses
│   └── bootstrap.php     # PSR-4 autoloader, error handlers, and session init
├── database/             # Relational MySQL / MariaDB (phpMyAdmin in XAMPP)
│   ├── schema.sql        # 13 relational tables, constraints, indexes & safe delete rules
│   ├── seed.sql          # Idempotent demo seeds (landmarks, staff profiles, candidates)
│   └── README.md         # Detailed phpMyAdmin import & troubleshooting guide
├── docs/
│   └── architecture.md   # Architectural design, security rules & roadmap
├── .gitignore            # Git exclusions for dependencies, env files, and builds
└── README.md             # This setup guide
```

---

## 2. Technology Stack

- **Frontend:** React 18, TypeScript, Vite, Lucide React.
- **Styling:** Custom Warm Ivory Design System (`#FAF8F5`) with Cobalt Blue, Coral, Mint, and Lavender accents.
- **Backend:** PHP 8.2+ with a Modular REST API architecture.
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (`campussos`) administered through **phpMyAdmin** in XAMPP on macOS.
- **Database Access:** PHP PDO with native prepared statements (`ATTR_EMULATE_PREPARES => false`).
- **Authentication:** PHP Sessions with secure cookies (`HttpOnly`, `SameSite=Lax`).
- **Urgent Emergency SOS:** **Deterministic instant dispatch** that never depends on external AI models.

---

## 3. Quick Start Guide (Local Development on macOS)

### Step 1: Set Up MySQL in XAMPP & Import Database
1. Open **XAMPP Control Panel** (`/Applications/XAMPP/manager-osx.app`) and start **MySQL Database** and **Apache**.
2. Open your browser to **phpMyAdmin**: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
3. Create a new database named `campussos` with collation `utf8mb4_unicode_ci`.
4. Click the **Import** tab, choose `database/schema.sql`, and click **Go** / **Import** (creates all 13 tables).
5. (Optional) Repeat the Import tab for `database/seed.sql` to load campus landmarks and demonstration profiles.

*(Refer to [database/README.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/database/README.md) for full instructions and troubleshooting).*

### Step 2: Configure & Run the PHP Backend
1. Copy the environment configuration:
   ```bash
   cp backend/.env.example backend/.env
   ```
2. Start the local PHP development server:
   ```bash
   cd backend
   php -S localhost:8000 -t public
   ```
3. Test database connectivity via the health-check endpoint:
   ```bash
   curl -s http://localhost:8000/api/health/db
   ```
   *Expected response:* `{"success": true, "status": "healthy", "database": "campussos", ...}`.

### Step 3: Run the React Frontend
In a separate terminal window:
```bash
cd frontend
npm install
npm run dev
```

The frontend will start at: [http://localhost:5173/](http://localhost:5173/).

API requests to `/api` from Vite are automatically proxied to `http://localhost:8000` via [frontend/vite.config.ts](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/frontend/vite.config.ts).

---

## 4. Key Relational Tables (Phase 2)

| Table | Purpose |
|---|---|
| `users` | Students, Staff Responders, and Administrators with bcrypt password hashes |
| `emergency_contacts` | Next-of-kin contacts for high-priority SOS emergency notifications |
| `locations` | Standardized campus buildings and landmarks (GPS coordinates optional) |
| `responders` | Responder duty status (`available`, `busy`, `offline`) and category |
| `incidents` | Safety reports with category, location, and privacy controls |
| `sos_alerts` | Real-time life-safety emergency signals with deterministic dispatch |
| `sos_assignments` | Multi-team assignment tracking for active SOS alerts |
| `incident_assignments` | Multi-responder assignment tracking for routine cases |
| `incident_attachments` | Evidence photo attachments (capped at 2 photos per incident) |
| `incident_matches` | Candidate duplicate reports identified via spatial/temporal proximity |
| `incident_updates` | Responder actions, notes, and chronological lifecycle status changes |
| `notifications` | Real-time alerts and incident updates for students and dispatchers |
| `audit_logs` | Immutable audit trail capturing user action, IP address, and timestamp |

---

## 5. Demonstration Seed Accounts (For Project Expo)

All default demo seed accounts use password: `Password@2026`

- **Administrator:** `admin@campussos.edu`
- **Medical Responder:** `medical@campussos.edu`
- **Security Responder:** `security@campussos.edu`
- **Fire Marshal:** `fire@campussos.edu`
- **Maintenance Staff:** `maintenance@campussos.edu`
- **Anti-Ragging Squad:** `antiragging@campussos.edu`
- **ICC Confidential:** `icc@campussos.edu`
- **Student Account:** `student@campussos.edu`

---

## 6. Phase 3: Secure Authentication, CSRF & CLI Admin Provisioning

### REST Authentication Endpoints
- `GET /api/auth/csrf`: Generates or returns active session CSRF token.
- `GET /api/auth/me`: Returns authenticated safe user profile or HTTP 401. Never exposes `password_hash`.
- `POST /api/auth/login`: Authenticates credentials with `password_verify()`, regenerates session ID, and sets `HttpOnly` cookie.
- `POST /api/auth/register`: Public registration strictly provisions `student` role accounts. Rejects client-supplied role overrides.
- `POST /api/auth/logout`: Invalidates session server-side and clears session cookie.

### CSRF Protection
All state-changing endpoints (`POST`, `PUT`, `PATCH`, `DELETE`) require a valid `X-CSRF-Token` header or `_csrf` body field validated with `hash_equals()`. The React frontend automatically fetches and attaches the CSRF token on every mutating request.

### Local CLI Administrator Provisioning
To provision campus administrator accounts safely without an unauthenticated web bootstrap endpoint:
```bash
# Interactive prompt
php backend/bin/create-admin.php

# Or specify flags with auto-generated secure password
php backend/bin/create-admin.php --email="chief.safety@campussos.edu" --name="Dr. Sarah Jenkins"
```

### Running Phase 3 Tests
```bash
# Execute backend validator & CSRF test suite
/Applications/XAMPP/xamppfiles/bin/php test_auth_phase3.php

# Execute frontend TypeScript verification & production build
cd frontend && npm run typecheck && npm run build
```

---

## 7. Phase 5: Responder Dashboard, Admin Dashboard & Incident Management

### SOS Lifecycle State Model
The SOS alert workflow enforces a deterministic, auditable 5-state lifecycle:
1. `triggered`: Alert created by student or campus member; triggers immediate deterministic responder broadcast.
2. `acknowledged`: Dispatcher or responder marks alert seen and begins active coordination.
3. `dispatched`: Units assigned to specific responder teams (`sos_assignments` created).
4. `resolved`: Danger mitigated, verified by responders and timestamped with resolution notes.
5. `false_alarm`: Handled and cancelled where an accidental trigger occurred.

### Key Endpoints Added
- `GET /api/admin/metrics`: Real-time MySQL-backed counts by status and category.
- `GET /api/admin/audit-logs`: Immutable logging of administrative dispatch events.
- `GET /api/admin/matches`: Deterministic candidate duplicate reports for human review.
- `POST /api/admin/matches/{id}/review`: Merge or dismiss duplicate candidates.
- `POST /api/incidents/{id}/assign`: Assign incidents to authorized responders with audit tracking.
- `POST /api/incidents/{id}/acknowledge`: Responders acknowledge dispatched calls.
- `GET /api/responders/my-assignments`: Responders view cases assigned to their category or unit.
- `PUT /api/responders/status`: On-duty status toggles (`available`, `busy`, `offline`).
- `GET /api/notifications` & `PUT /api/notifications/{id}/read`: Database-backed notification feed.

### Running Phase 5 Tests
```bash
# Execute Phase 5 security, role-authorization & logic test suite
/Applications/XAMPP/xamppfiles/bin/php test_phase5.php
```

---

## 8. Phase 6: Incident Matching, Optional Gemini AI & Verification

### Deterministic Incident Matching Engine
To eliminate duplicate dispatcher clutter without introducing black-box unpredictability, CampusSOS implements a transparent, weighted similarity engine (`IncidentMatcherService`):
- **Category Match (0.25):** Strict comparison across standard categories (`medical`, `security`, `fire`, `maintenance`, `anti_ragging`, `sexual_harassment`, `other`).
- **Spatial Proximity & Coordinates (0.35):** Evaluates exact landmark strings, substring containment, and Haversine geographic distance for coordinate pairs (sub-50m proximity scores highest).
- **Temporal Proximity (0.20):** Scores incidents occurring within 30 to 180 minutes of each other.
- **Lexical Overlap (0.20):** Tokenizes title and description text, filters out common stop words, and computes Jaccard similarity.
- **Threshold & Candidates:** Incidents scoring $\ge 0.60$ are flagged as duplicate candidates for dispatcher review (`incident_matches`).
- **Integrity & Check Constraints:** Enforces `min(incident_a_id, incident_b_id) < max(incident_a_id, incident_b_id)` matching the MySQL table constraint `chk_incident_pair_order`. Prevents reversed duplicate rows `(A, B)` vs `(B, A)` and strictly rejects self-matching `(A, A)`.
- **Confidential Isolation:** Reports marked `confidential` or sensitive disciplinary categories are completely isolated from automated public cross-matching.

### Optional Gemini AI Classification & Resilient Fallback
- **Safety Rule:** Emergency SOS dispatch **NEVER** calls Gemini or any AI model. Urgent life-safety dispatch executes deterministically in sub-millisecond local code.
- **Backend-Only Architecture:** The Gemini API key is configured strictly in `backend/.env` (`GEMINI_API_KEY`) and is never exposed to the frontend or version control.
- **Privacy Guardrail:** Confidential reports (such as Sexual Harassment/ICC or Anti-Ragging) automatically bypass external AI calls and are classified using the local deterministic engine, protecting student confidentiality.
- **Graceful Fallback:** If `GEMINI_ENABLED=false`, `GEMINI_API_KEY` is not provided, the API times out (3.0s ceiling), or quota is exhausted, the system automatically falls back to the deterministic keyword heuristic without throwing errors or blocking submissions.

### Running Phase 6 Tests
```bash
# Run comprehensive Phase 6 test suite (12 tests)
/Applications/XAMPP/xamppfiles/bin/php test_phase6.php
```

---

## 9. Phase 7: Expo Readiness, QA & Demonstration Seeding

### CLI Demonstration Data Tool
CampusSOS provides an idempotent development seeder CLI script (`backend/bin/seed-demo-data.php`):
```bash
# Seed or verify standard demonstration records (locations, roles, candidate duplicates)
/Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --seed

# Check current count of demo records
/Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --status

# Safely clean up ONLY demo-tagged records without affecting real users
/Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --reset-demo-only
```
- **Safety Rule:** Strictly disabled if `APP_ENV=production`.
- **Integrity Rule:** Uses reserved identities (`@campussos.edu`), never touches or deletes real student records.

### Running Phase 7 End-to-End QA & Security Test Suite
```bash
# Run comprehensive Phase 7 end-to-end verification (18 tests)
/Applications/XAMPP/xamppfiles/bin/php test_phase7_e2e.php
```

---

## 10. Official Expo Demonstration Guide

For the complete 5–7 minute presentation walkthrough, scenario scripts, and troubleshooting protocols, refer to:
👉 **[docs/expo-demo-guide.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/expo-demo-guide.md)**

---

## 11. Phase 9: Cross-Feature Integration & Final Acceptance Report

### Running Acceptance Tests
```bash
# Execute Phase 9 Cross-Feature Acceptance Suite (20 tests)
/Applications/XAMPP/xamppfiles/bin/php test_phase9_acceptance.php
```

### Acceptance Documentation
For the full feature-parity matrix, end-to-end integration audits, and production-readiness evaluation, see:
👉 **[docs/final-acceptance-report.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/final-acceptance-report.md)**

---

## 12. Phase 10: Release Candidate Freeze & Database Backup Utility

### Release Candidate Specifications
- **Release Version:** `v1.0.0-RC1`
- **Total Automated Regression Tests:** 94 tests across 6 test suites (100% Passing)

### Pre-Demonstration Database Backup Command
```bash
# Generate clean, timestamped SQL dump in database/backups/
/Applications/XAMPP/xamppfiles/bin/php backend/bin/backup-db.php
```

### Running Release Candidate Verification
```bash
# Execute Phase 10 Release Candidate Verification Suite (23 tests)
/Applications/XAMPP/xamppfiles/bin/php test_phase10_rc.php
```

---

## 13. Phase 11: Project Expo Presentation Package & Technical Defense

A complete presentation, live demonstration, and technical defense kit tailored for the Student Project Expo 2026:

### Presentation Deliverables
1. **Interactive Neo-Brutalist HTML Deck:**
   👉 **[docs/presentation-deck.html](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/presentation-deck.html)**
   *Zero-dependency, offline-ready presentation deck with 12 slides, keyboard navigation (`Arrow keys` / `PageDown`), slide counter, and toggleable speaker notes drawer (`N`).*

2. **Native PowerPoint Deck:**
   👉 **`docs/campussos-expo-presentation.pptx`**
   *16:9 widescreen presentation file with full OpenXML slide layouts, Neo-Brutalist cards, and integrated presenter speaker notes.*

3. **Slide Deck Outline & Presenter Script:**
   👉 **[docs/presentation-slides.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/presentation-slides.md)**
   *Complete slide compositions, visual tokens, and exact 30–50 second presenter delivery scripts.*

4. **Timed Live Demonstration Protocol:**
   👉 **[docs/expo-presentation.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/expo-presentation.md)**
   *Step-by-step 5–7 minute walkthrough across 7 segments with exact clicks, expected outcomes, and recovery contingencies.*

5. **Technical Defense & Judges' Q&A Guide:**
   👉 **[docs/judges-qa.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/judges-qa.md)**
   *18 deeply grounded technical answers covering architecture, privacy guarantees, PDO prepared statements, Gemini fallback, check constraints, database offline handling, and 40k student scaling.*

6. **Visual Architecture Diagrams:**
   👉 **[docs/architecture-diagram.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/architecture-diagram.md)**
   *Mermaid flowcharts, ERD schemas, and sequence diagrams illustrating system tiering, dual-pathway dispatch, and incident deduplication.*

### Verification Command (Full 94-Test Suite)
```bash
/Applications/XAMPP/xamppfiles/bin/php test_auth_phase3.php && \
/Applications/XAMPP/xamppfiles/bin/php test_phase5.php && \
/Applications/XAMPP/xamppfiles/bin/php test_phase6.php && \
/Applications/XAMPP/xamppfiles/bin/php test_phase7_e2e.php && \
/Applications/XAMPP/xamppfiles/bin/php test_phase9_acceptance.php && \
/Applications/XAMPP/xamppfiles/bin/php test_phase10_rc.php
```







# CampusSOS — Student Project Expo Demonstration Guide

> **Official 5–7 Minute Presentation Playbook, System Startup & Evaluation Protocol**
>
> *Safety Notice: All demonstration scenarios execute locally within isolated test environments. Emergency SOS dispatch actions in this demonstration are strictly simulated and never contact real-world public safety or emergency dispatch services.*

---

## 1. System Requirements & Environment Setup

### Required Software
- **Operating System:** macOS (or Linux / Windows with equivalent stack)
- **Local Server Stack:** XAMPP for macOS (Apache + MySQL / MariaDB + phpMyAdmin)
- **PHP Runtime:** PHP 8.2+ (`/Applications/XAMPP/xamppfiles/bin/php` or system `php`)
- **Database Engine:** MySQL 8.0+ or MariaDB 10.4+
- **Frontend Runtime:** Node.js 18+ and npm 9+

---

## 2. Step-by-Step System Startup Instructions

### Step 1: Start XAMPP MySQL & Apache
1. Open **XAMPP Control Panel** (`/Applications/XAMPP/manager-osx.app`).
2. Start **MySQL Database** and **Apache Web Server**.
3. Confirm phpMyAdmin is accessible at: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).

### Step 2: Database Initialization & Safe Backup
1. In phpMyAdmin, create a database named `campussos` with collation `utf8mb4_unicode_ci`.
2. Import `database/schema.sql` (creates all 13 InnoDB relational tables with foreign keys and check constraints).
3. Populate demonstration data using the CLI seeder:
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --seed
   ```
   *Expected Output: `[OK] 10 campus locations, 8 demonstration roles, 6 responder categories, 4 demo incidents seeded.`*
4. Create an idempotent baseline backup:
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php backend/bin/backup-db.php
   ```
   *Creates a timestamped snapshot in `database/backups/campussos_backup_YYYY-MM-DD_HHMMSS.sql`.*

### Step 3: Configure Backend Environment
Ensure `backend/.env` exists (copied from `backend/.env.example`):
```bash
cp backend/.env.example backend/.env
```
Default evaluation configuration:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=campussos
DB_USER=root
DB_PASS=

APP_ENV=development
API_BASE_URL=http://localhost:8000
FRONTEND_ORIGIN=http://localhost:5173

GEMINI_ENABLED=false
GEMINI_API_KEY=
GEMINI_MODEL=gemini-1.5-flash
```

### Step 4: Start the PHP Backend API Server
In Terminal Window 1:
```bash
cd backend
/Applications/XAMPP/xamppfiles/bin/php -S localhost:8000 -t public
```

### Step 5: Verify Backend Health
In Terminal Window 2:
```bash
# Check API router health
curl -s http://localhost:8000/api/health

# Check MySQL PDO connection and latency
curl -s http://localhost:8000/api/health/db
```
*Expected Response:* `{"success": true, "status": "healthy", "database": "campussos", "tables_found": 13, ...}`

### Step 6: Start the React Frontend Development Server
In Terminal Window 2:
```bash
cd frontend
npm install
npm run dev
```
Open your browser to: **[http://localhost:5173/](http://localhost:5173/)**

---

## 3. Demonstration Accounts Reference

All demonstration accounts use the password: **`Password@2026`**

| Role / Category | Email Identity | Access Scope |
|---|---|---|
| **Campus Administrator** | `admin@campussos.edu` | Full administrative console, metrics, audit logs, duplicate match review |
| **Medical Responder** | `medical@campussos.edu` | Medical emergency queue, paramedic dispatch notes, triage |
| **Security Responder** | `security@campussos.edu` | Campus security patrol, rapid response team, quick reaction logs |
| **Fire Marshal** | `fire@campussos.edu` | Evacuation management, hazard & chemical alarms |
| **Facilities Maintenance**| `maintenance@campussos.edu`| Electrical, plumbing, and structural safety tickets |
| **Anti-Ragging Squad** | `antiragging@campussos.edu` | Confidential proctorial complaints & student welfare |
| **Confidential ICC / Title IX**| `icc@campussos.edu` | Strictly confidential sexual harassment counseling intake |
| **Student Member** | `student@campussos.edu` | Routine reporting, anonymous concerns, report tracking |

---

## 4. The 5–7 Minute Presentation Script & Walkthrough

### Minute 1: The Vision & Architectural Dual Pathways (Homepage)
- **Visual Presentation:** Display the Landing Page (`/`).
- **Key Talking Points:**
  - "Campus safety requires a clear distinction between routine infrastructure concerns and immediate life-safety emergencies."
  - Point out the **Approved Neo-Brutalist Design System**: warm cream paper background, oversized editorial typography, cobalt blue accents, coral emergency actions, neon lime underlines, hand-drawn annotations, and solid black drop shadows.
  - Explain the **Dual Pathway Architecture**:
    - *Pathway A:* Urgent Emergency SOS (zero-latency deterministic dispatch).
    - *Pathway B:* Routine safety intake (with multi-factor duplicate detection & privacy controls).

---

### Minute 2: Student Routine & Confidential Intake
- **Actions:**
  1. Click **"Report Concern"** in the top navigation.
  2. Demonstrate the three reporting tiers:
     - **Standard Report:** Identity linked to authenticated student.
     - **Anonymous Report:** Identity decoupled; `reporter_user_id` stored and returned as `NULL`.
     - **Confidential Report:** Locked exclusively to designated investigative bodies (ICC / Chief Proctor).
  3. Submit an example incident:
     - Title: `Damaged electrical box sparking in corridor`
     - Category: `Facility Maintenance`
     - Location: `Science Complex - Chemistry Labs Wing`
     - Description: `Sparks observed near Room 204 electrical conduit.`
  4. Submit and observe the success screen with generated number `INC-2026-XXXX`.
  5. Click **"View in Incident Tracker"** to showcase real-time status tracking (`reported` $\to$ `under_review`).

---

### Minute 3: Safe Development Emergency SOS Activation
- **Actions:**
  1. Navigate to any internal portal page (e.g. Incident Tracker).
  2. Notice the **Persistent Floating Emergency SOS Button** with pulsing animated indicator.
  3. Click **"EMERGENCY SOS"**:
     - Modal opens requesting one-click confirmation and location.
     - Show the safety reassurance note: *“Deterministic instant dispatch enabled (No AI delay)”*.
  4. Click **"ACTIVATE SOS DISPATCH"**:
     - High-visibility red emergency banner immediately flashes across the top of the interface.
     - Sub-millisecond local dispatch simultaneously notifies on-duty Campus Security and Medical Paramedics.
- **Key Talking Points:**
  - "In a real emergency, every second counts. Life-safety SOS alerts **never** depend on external AI or network LLMs. Dispatch is 100% deterministic and instantaneous."

---

### Minute 4: Responder Operations Console
- **Actions:**
  1. Click **"Responders"** in the navbar (or log in as `maintenance@campussos.edu`).
  2. Showcase:
     - **Active Duty Status Toggle:** Change status between `Available`, `Busy`, and `Offline` with instant visual badge updates.
     - **Category-Scoped Queue:** Responders only see incidents relevant to their discipline (`maintenance`).
     - Click **"Acknowledge Assignment"** on an open ticket and demonstrate that status transitions in real time.

---

### Minute 5: Administrator Console & Duplicate Incident Matching
- **Actions:**
  1. Click **"Admin"** in the navbar (log in as `admin@campussos.edu`).
  2. **Tab 1 — Operations Overview:** Review real-time metrics cards, category distribution, active SOS signals, and recent activity.
  3. **Tab 4 — Candidate Matches:**
     - Point out candidate duplicate pair `INC-2026-DEMO-01` and `INC-2026-DEMO-02`.
     - Explain the transparent multi-factor matching score ($0.88$):
       - Category Match ($0.25$)
       - Spatial Proximity ($0.35$)
       - Temporal Window ($0.20$)
       - Lexical Overlap ($0.20$)
     - Explain the database check constraint: MySQL table `incident_matches` enforces `incident_a_id < incident_b_id`, preventing duplicate reversed pairs $(A, B)$ vs $(B, A)$ and self-matches $(A, A)$.
     - Click **"Confirm Duplicate"** or **"Dismiss"**: Human-in-the-loop review ensures algorithms assist dispatchers without ever making unreviewed destructive deletions.
  4. **Tab 5 — Audit Logs:** Point out the immutable audit trail capturing every login, dispatch, and review action.

---

### Minute 6: AI Integration Philosophy & Resilient Fallback
- **Key Talking Points:**
  - "We have integrated optional Google Gemini AI for smart triage and classification, but built it with an enterprise safety-first approach:"
    1. **Strict Privacy Boundary:** Confidential Title IX and Anti-Ragging reports automatically bypass external AI, using the local deterministic engine.
    2. **Resilient Local Heuristic Fallback:** If `GEMINI_API_KEY` is not provided, the API times out (3.0s ceiling), or quota is exhausted, the system immediately uses local keyword heuristics.
    3. **Zero SOS Dependency:** Emergency SOS alerts never touch the AI service.

---

### Minute 7: Verification & Q&A
- Show that all test suites pass with 100% success rate:
  ```bash
  /Applications/XAMPP/xamppfiles/bin/php test_phase10_rc.php
  # 23 Passed, 0 Failed (or run all suites: 94 Passed, 0 Failed)
  ```

---

## 5. Troubleshooting & Frequently Asked Questions

### Issue: "Database connection failed (Code: 2002)"
- **Fix:** Open XAMPP Control Panel and ensure MySQL is started. In macOS, verify that TCP port 3306 is listening. Ensure `DB_HOST=127.0.0.1` in `backend/.env`.

### Issue: "CORS error or session cookie not saving"
- **Fix:** Ensure requests originate from `http://localhost:5173` matching `FRONTEND_ORIGIN` in `backend/.env`. Note that `SESSION_SECURE_COOKIE` must be `false` on local HTTP.

### Issue: "How do I reset demonstration data after testing?"
- **Command:**
  ```bash
  /Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --reset-demo-only
  /Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --seed
  ```
  *This cleans up only demo-tagged records without wiping real users or existing data.*

### Backup Plan if Gemini API is Unavailable
- The application is **fully operational without Gemini**. With `GEMINI_ENABLED=false`, the built-in deterministic classification engine classifies fire, medical, security, and facility incidents instantly.


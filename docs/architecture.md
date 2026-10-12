# CampusSOS — System Architecture & Implementation Foundation

## 1. Executive System Overview

**CampusSOS** is an enterprise-grade campus safety and emergency reporting platform built for high reliability, rapid responder coordination, and privacy-preserving student assistance.

```
┌────────────────────────────────────────────────────────────────────────┐
│                   Vite + React + TypeScript Frontend                   │
│         (Warm Ivory Theme, Lucide Icons, Modular Component Tree)       │
└─────────────────────────────────┬──────────────────────────────────────┘
                                  │ HTTP JSON REST API
                                  │ Session Cookies (HttpOnly, SameSite)
                                  ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      PHP 8.2+ Modular REST API                         │
│  - Router & Request Dispatcher         - Session Auth Middleware       │
│  - Deterministic SOS Engine (No AI)    - Incident Matcher Engine       │
│  - Audit Logging & Validation          - Database Health Monitor       │
│  - PDO Prepared Statements             - Locations & Directory API     │
└─────────────────────────────────┬──────────────────────────────────────┘
                                  │ PDO MySQL Driver (Native Prepared Queries)
                                  ▼
┌────────────────────────────────────────────────────────────────────────┐
│                MySQL / MariaDB Database (`campussos`)                  │
│       (Administered via phpMyAdmin on macOS XAMPP Environment)         │
│  - 13 Relational Tables (InnoDB)       - Safe Deletion Foreign Keys    │
│  - Pair-Ordering Duplicate Safeguards   - Strict utf8mb4 Collation      │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Separation of Concerns & Technology Stack

1. **Frontend Presentation Tier**:
   - Framework: React 18, TypeScript, and Vite.
   - Styling: Custom Design System based on warm ivory (`#FAF8F5`), cobalt blue (`#1E40AF`), coral (`#E11D48`), mint (`#059669`), lavender (`#7C3AED`), and amber yellow (`#D97706`).
   - Icons: Lucide React.
   - Decoupled from backend via typed API client services (`api.ts`, `authService.ts`, `incidentService.ts`, `sosService.ts`).

2. **Backend Application Tier**:
   - Runtime: PHP 8.2+ running on Apache (XAMPP macOS) or built-in server.
   - Architecture: Modular REST API adhering to single-responsibility controllers, data models, and business logic services.
   - Database Abstraction: PHP PDO with native prepared statements (`ATTR_EMULATE_PREPARES => false`).
   - Health Monitoring: Dedicated `GET /api/health/db` endpoint reporting connectivity and query latency without leaking credentials or stack traces.
   - Authentication: Server-side PHP sessions utilizing secure `HttpOnly` and `SameSite=Lax` cookie flags.

3. **Data Tier**:
   - Engine: MySQL 8.0+ / MariaDB 10.4+ administered via phpMyAdmin in XAMPP.
   - Database Name: `campussos`.
   - Engine: `InnoDB` across all 13 tables.

---

## 3. Two Distinct Incident Pathways

A core product requirement is the architectural and visual distinction between routine safety intake and urgent life-safety activation:

### Pathway A: Urgent Emergency SOS Dispatch
- **Activation Vector:** Persistent, compact floating SOS action button (`FloatingSosButton`) rendered at the application root and visible across all student portal views.
- **Rule:** **No duplicate static SOS button** is placed on the main campus page.
- **Workflow:** One-click confirmation dialog requesting user confirmation and current location landmark.
- **Dispatch Engine:** **Strictly deterministic zero-latency dispatch.** Urgent SOS dispatch **never depends on an AI model, external LLM API, or external classification service.**
- **Routing:** Instant concurrent alert broadcast to on-duty **Campus Security** and **Medical Paramedic** teams, with multi-unit status tracked in `sos_assignments`.

### Pathway B: Routine Incident Reporting
- **Intake Vector:** "Report a Safety Concern" intake form (`ReportIncidentPage`).
- **Data Captured:** Category, title, description, location landmark (optionally referencing `locations`), timestamp, privacy level (Standard, Confidential, Sensitive), and anonymity flag.
- **Triage:** Optional AI-assisted categorization with an automated **deterministic rule-based offline fallback**.
- **Privacy Enforcement:**
  - **Anonymous Reports (`is_anonymous=1`):** Reporter identity is decoupled. Queries explicitly return `NULL` for `reporter_user_id` to prevent inadvertent disclosure through API responses.
  - **Confidential Reports (`is_confidential=1`):** Restricted strictly to authorized investigation roles (e.g. Title IX / ICC for sexual harassment, Chief Proctor for anti-ragging). General responders and campus administrators without authorization cannot access the full narrative.

---

## 4. The 6 Authorized Responder Categories

Campus safety responders are strictly partitioned into 6 dedicated categories:

| Category Code | Discipline | Default Assigned Unit | Priority Level |
|---|---|---|---|
| `medical` | Medical Emergency | Campus Health Clinic & Paramedic Ambulance | Critical / High |
| `security` | Campus Security | Campus Safety, Gate Patrol, and Rapid Reaction | High |
| `fire` | Fire & Chemical Hazard | Disaster Management & Fire Safety Squad | Critical |
| `maintenance` | Facility Maintenance | Civil Engineering & Electrical Maintenance | Medium / Low |
| `anti_ragging` | Anti-Ragging Cell | Proctorial Board & Hostel Wardens | High |
| `sexual_harassment`| Confidential Support | Internal Complaints Committee (ICC / Title IX) | Strictly Confidential |

---

## 5. Similar-Incident Matching with Human-in-the-Loop Review

To eliminate redundant emergency dispatches while maintaining absolute record integrity:

1. When an incident is logged, `IncidentMatcherService` computes spatial proximity (within 500m) and temporal proximity (within 30 minutes).
2. Lexical keyword overlap is evaluated against open incident records.
3. Candidate duplicates are inserted into `incident_matches` with `status = 'pending_review'`.
4. **Pair Ordering Constraint:** Schema enforces `CHECK (incident_a_id < incident_b_id)` and `UNIQUE KEY (incident_a_id, incident_b_id)` to ensure no duplicate or self-matching records exist.
5. **Human Review Safeguard:** Reports are **never automatically merged or deleted**. An authorized campus dispatcher must review candidate reports side-by-side in the Admin Console and explicitly choose:
   - **Confirm & Link:** Groups reports under a primary incident while maintaining individual reporter logs.
   - **Dismiss:** Marks reports as distinct events.

---

## 6. Application Security Architecture

- **SQL Injection Prevention:** Every query is executed through PDO prepared statements with parameterized binds. Native prepared statements are enforced (`ATTR_EMULATE_PREPARES => false`).
- **Session Security:** Cookies configured with `httponly = true` (mitigating XSS extraction), `samesite = Lax` (CSRF protection), and strict session ID regeneration upon login.
- **CORS Hardening:** Configured to restrict origins specifically to the Vite development server (`http://localhost:5173`) while allowing credentialed requests.
- **Zero Secrets in Frontend or Repository:** No database credentials or secrets are stored in client code, SQL DDL files, or committed `.env` files.
- **Audit Logging:** Security-critical operations write immutable records to `audit_logs` capturing user action, resource identifier, client IP address, and timestamp. Passwords, session tokens, and API keys are strictly excluded from audit payloads.

---

## 7. Phased Implementation Roadmap

- **Phase 1 (Completed):** Project initialization, workspace structure, React/TS frontend skeleton with warm ivory styling, PHP 8.2 backend skeleton with modular routing, and initial development foundation.
- **Phase 2 (Completed):** Complete relational MySQL/MariaDB schema (`campussos`) with 13 tables, safe deletion rules, phpMyAdmin import workflows, idempotent seed data, reusable PHP PDO connection layer, and `/api/health/db` health-check verification.
- **Phase 3 (Completed):** Secure PHP session authentication, CSRF protection, CLI administrator provisioning tool (`backend/bin/create-admin.php`), and automated auth test suite (`test_auth_phase3.php`).
- **Phase 4 (Completed):** UI integration reproduction of approved reference design with photo cutouts, internal floating SOS button, category badges, and responsive layouts.
- **Phase 5 (Completed):** Responder dashboard, Admin console, multi-disciplinary assignments, chronological incident updates, in-app notification feed, and Phase 5 verification test suite (`test_phase5.php`).
- **Phase 6 (Completed):** Deterministic incident matching service, optional Gemini AI classification with privacy protection and instant heuristic fallback, human review workflow, and comprehensive test suite (`test_phase6.php`).
- **Phase 7 (Completed):** Final expo stabilization, idempotent CLI demo seeder (`seed-demo-data.php`), accessibility & reduced-motion enhancements, 18-test end-to-end security verification (`test_phase7_e2e.php`), and official expo presentation guide (`docs/expo-demo-guide.md`).
- **Phase 9 (Completed):** Cross-feature acceptance testing, full-stack integration verification, 20-test acceptance suite (`test_phase9_acceptance.php`), and official final acceptance report (`docs/final-acceptance-report.md`).
- **Phase 10 (Completed):** Release candidate freeze (`v1.0.0-RC1`), database backup utility (`backup-db.php`), 94-test regression verification, and final expo authorization.
- **Phase 11 (Completed):** Project expo presentation package, 12-slide interactive Neo-Brutalist HTML deck (`docs/presentation-deck.html`), native PowerPoint OpenXML deck (`docs/campussos-expo-presentation.pptx`), timed demonstration protocol (`docs/expo-presentation.md`), technical defense & judges Q&A (`docs/judges-qa.md`), and comprehensive system architecture diagrams (`docs/architecture-diagram.md`).

---

## 8. Visual System Architecture & Data Flow

For detailed Mermaid and ERD diagrams supporting project presentation defense, see:
👉 **[docs/architecture-diagram.md](file:///Users/sarveshsharma/Documents/SF/Projects/Webdev_projects/Project_And_Startup_Expo_CampusSOS/docs/architecture-diagram.md)**

---

## 9. Incident Matching & AI Safety Architecture (Phase 6)

### 8.1 Multi-Factor Deterministic Incident Matching Engine
The system employs a transparent, mathematical multi-factor scoring model to detect candidate duplicate incident reports without black-box opacity:
$$\text{Score} = w_{\text{category}} \cdot S_{\text{cat}} + w_{\text{location}} \cdot S_{\text{loc}} + w_{\text{temporal}} \cdot S_{\text{time}} + w_{\text{lexical}} \cdot S_{\text{lex}}$$
- **Weights:**
  - $w_{\text{category}} = 0.25$: Exact category match.
  - $w_{\text{location}} = 0.35$: Haversine coordinate proximity ($\le 50\text{m} \to 0.35$, $\le 200\text{m} \to 0.28$) or landmark string containment.
  - $w_{\text{temporal}} = 0.20$: Time delta ($\le 30\text{ mins} \to 0.20$, $\le 60\text{ mins} \to 0.16$, $\le 180\text{ mins} \to 0.08$).
  - $w_{\text{lexical}} = 0.20$: Jaccard similarity of tokenized title and description after stop-word filtering.
- **Threshold:** Pairs with $\text{Score} \ge 0.60$ are marked as candidate duplicates (`isCandidate = true`).
- **Database Ordering Rule:** Insertions into `incident_matches` enforce `incident_a_id < incident_b_id` matching table check constraint `chk_incident_pair_order`, preventing reversed duplicate records and self-matching.
- **Strict Confidentiality Isolation:** Confidential reports (`is_confidential = 1` or `privacy_level != 'standard'`) and sensitive disciplinary categories (`sexual_harassment`, `anti_ragging`) are completely isolated from automated public cross-matching.

### 8.2 Optional Gemini AI Integration & Resilient Fallback
- **Safety Policy:** Urgent Emergency SOS alerts **never** invoke Gemini or any AI service. Life-safety dispatch executes via instant deterministic code in sub-millisecond time.
- **Backend-Only Storage:** API keys reside solely in `backend/.env` (`GEMINI_API_KEY`). Client apps never receive or invoke the key.
- **Student Privacy Filter:** All confidential reports and Title IX/Anti-Ragging matters strictly bypass external AI, automatically triaged by the local deterministic engine.
- **3-Second Timeout & Resilient Fallback:** The cURL connector enforces a 3.0-second timeout ceiling. If the API key is missing, network fails, or quota is exhausted, the backend falls back instantaneously to the local deterministic keyword heuristic classifier. Submission never blocks or throws an uncaught error.


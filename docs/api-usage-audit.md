# CampusSOS — Complete API Usage Audit

**Document Version:** 1.0.0  
**Audit Date:** October 12, 2026  
**Audited System:** CampusSOS (Student Safety & Emergency Response Platform)  
**Scope:** Complete codebase inspection covering React Frontend, PHP REST Backend, MariaDB Database, and Third-Party External Integrations.

---

## Executive Summary

This audit provides a comprehensive investigation of all Application Programming Interfaces (APIs), third-party integrations, internal REST endpoints, database interactions, and network communications across the CampusSOS platform.

### Key Audit Findings:
1. **External Third-Party APIs Called:** **1** — The **Google Gemini API** (`generativelanguage.googleapis.com`) is the only external network API integrated into CampusSOS. No external payment gateways, mapping providers, SMS gateways, or cloud notification APIs are used.
2. **Internal REST API Endpoints:** **34** distinct endpoints registered across 9 domain controllers (`Health`, `Location`, `Auth`, `Incident`, `Sos`, `Responder`, `Notification`, `Admin`, and `Contact`).
3. **Frontend HTTP Client Architecture:** 100% of frontend HTTP traffic routes through a centralized singleton client (`ApiClient` in `frontend/src/services/api.ts`) built on native browser `fetch`. No Axios or external HTTP libraries are bundled.
4. **Emergency SOS Independence:** The Emergency SOS dispatch pipeline (`POST /api/sos/trigger`) is **100% deterministic, zero-latency, and operates entirely independently of Gemini or external network services**.
5. **Privacy Guardrails for AI:** Confidential incident reports (sexual harassment, anti-ragging, or `privacy_level=confidential`) **strictly bypass Gemini** through an automated guardrail in `GeminiClassificationService.php` and rely on local deterministic rules.
6. **Database Access:** Direct PDO with prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`) connecting to 14 relational tables in MariaDB.

---

## 1. System Architecture & Request Flows

### 1.1 Standard Internal REST Request Flow

The following diagram illustrates how standard user actions travel from the React UI to MariaDB:

```
[React UI Component] 
       │ (User Action, e.g. View Assigned Incidents)
       ▼
[Domain Service] (e.g., responderPortalService.getMyAssignments())
       │ 
       ▼
[ApiClient (api.ts)]
       │ • Attaches Accept & Content-Type: application/json
       │ • Injects X-CSRF-Token on mutating requests (POST/PUT/DELETE)
       │ • Injects credentials: 'include' (PHP session cookie)
       ▼
[Vite Development Proxy] (Port 5173 -> Port 8000)
       ▼
[PHP Front Controller] (backend/public/index.php)
       │
       ▼
[Bootstrap Pipeline] (backend/bootstrap.php)
       │ • PSR-4 Class Autoloader
       │ • Loads backend/.env configuration
       │ • Global Exception & Error Handlers
       │ • Strict CORS Origin Allowlist Check (Config/Cors.php)
       │ • Session Initialization (Config/App.php)
       │ • CSRF Middleware Token Validation (Middleware/CsrfMiddleware.php)
       ▼
[Router Engine] (backend/routes/Router.php via routes/api.php)
       │ • Regex URL matching & HTTP method validation
       ▼
[Controller] (e.g., ResponderController::myAssignments())
       │ • Authentication & Role Verification (AuthMiddleware / RoleMiddleware)
       │ • Input Sanitization & Validation
       ▼
[Domain Model] (e.g., IncidentAssignment::findByResponder())
       │ • PDO Singleton Connection (Config/Database.php)
       │ • Parameterized SQL Execution (MySQL/MariaDB)
       ▼
[MariaDB Database] (campussos)
       │
       ▼
[ResponseService] (backend/services/ResponseService.php)
       │ • Standardized JSON Envelope: { success: true, data: [...], message: "..." }
       ▼
[Client Response] (Promise resolution in React state hook)
```

---

### 1.2 Gemini Incident Classification Flow

When an incident report is submitted through `POST /api/incidents`, the triage classification flow executes as follows:

```
[Student Incident Form] (ReportIncidentPage.tsx)
       │
       ▼
[incidentService.createIncident()]
       │
       ▼
[POST /api/incidents] (IncidentController::create())
       │
       ├──► 1. Location Validation (LocationService::validateAndFormat())
       │
       ├──► 2. AI Triage Intake (GeminiClassificationService::classifyIncident())
       │         │
       │         ├──► [Privacy Guardrail Check]
       │         │       Is privacy_level === 'confidential' OR category IN ('sexual_harassment', 'anti_ragging')?
       │         │       ├──► YES: Bypass External AI! 
       │         │       │         Execute classifyDeterministic() locally.
       │         │       │         Source: 'deterministic_privacy_rule'
       │         │       │
       │         │       └──► NO: Proceed to Gemini Check
       │         │
       │         ├──► [Configuration Check]
       │         │       Is GEMINI_ENABLED=true AND GEMINI_API_KEY present?
       │         │       └──► If NO: Fallback to classifyDeterministic() locally.
       │         │
       │         └──► [External cURL Request]
       │                 • POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
       │                 • Connect Timeout: 2.0s | Total Timeout: 4.0s
       │                 • Payload: Truncated Title & Description (Strictly NO PII)
       │                 • If Success (HTTP 200 & valid JSON): Return Gemini classification.
       │                 • If Error / Timeout / Rate-limit: Catch exception, log warning,
       │                   and execute classifyDeterministic() fallback.
       │
       ├──► 3. Incident Persistence (Incident::create() -> PDO insert into `incidents`)
       │         Stores ai_classified (1/0), ai_confidence, severity.
       │
       ├──► 4. Timeline Update (IncidentUpdate::create() -> `incident_updates`)
       │
       ├──► 5. Audit Logging (AuditLog::log() -> `audit_logs`)
       │
       ├──► 6. Duplicate Detection (IncidentMatcherService::detectAndPersistMatches() -> `incident_matches`)
       │         Purely local deterministic lexical + spatial + temporal similarity scoring.
       │
       ▼
[HTTP 201 Response] (Includes classification, candidate matches, and incident number)
```

---

### 1.3 Deterministic Emergency SOS Dispatch Flow

Emergency SOS response is life-critical and deliberately engineered to operate without external network dependencies:

```
[SOS Emergency Trigger] (FloatingSosButton.tsx or HomePage.tsx)
       │
       ▼
[sosService.triggerSos()]
       │
       ▼
[POST /api/sos/trigger] (SosController::trigger())
       │
       ▼
[SosDispatchService::triggerSos()]
       │ • Formats emergency location & optional room number
       │ • Generates deterministic SOS tracking code (e.g. SOS-2026-XXXX)
       │ • Sanitizes emergency type & custom details
       │ • ZERO calls to Gemini or external network APIs
       ▼
[Database Persistence]
       │ • Writes to `sos_alerts` via SosAlert::create()
       │ • Writes to `audit_logs` via AuditLog::log()
       ▼
[Sub-15ms HTTP 201 Response]
       │ • Confirms immediate dispatch to Security QRT & Paramedic Unit
       ▼
[Real-Time State Update] (Alerts displayed on Responder & Admin dashboards)
```

---

## 2. Complete Internal REST Endpoint Inventory

Below is the verified inventory of all **34 internal REST endpoints** implemented in `backend/routes/api.php` and handled by backend controllers:

| # | HTTP Method | Endpoint URL | PHP Controller & Action | Primary Purpose | Frontend Caller(s) | Auth / RBAC Requirements | Database Tables Involved | Uses External API? |
|---|:---:|---|---|---|---|---|---|:---:|
| 1 | `GET` | `/api/health` | `HealthController::apiHealth` | Basic API health & PHP version check | Monitoring / Diagnostic tests | Public (None) | None | No |
| 2 | `GET` | `/api/health/db` | `HealthController::dbHealth` | Database connectivity & latency check | Monitoring / Diagnostic tests | Public (None) | `campussos` connection | No |
| 3 | `GET` | `/api/locations` | `LocationController::index` | List 25 campus locations (general & buildings) | Diagnostic tests (UI uses local constants) | Public (None) | None (Static service) | No |
| 4 | `GET` | `/api/auth/csrf` | `AuthController::csrf` | Initialize or refresh session CSRF token | `ApiClient.refreshCsrfToken()`, `AuthContext` | Public (Session cookie) | None (`$_SESSION`) | No |
| 5 | `GET` | `/api/auth/me` | `AuthController::me` | Retrieve authenticated user profile | `authService.getMe()`, `AuthContext` | Authenticated session | `users`, `responders` | No |
| 6 | `POST` | `/api/auth/login` | `AuthController::login` | Authenticate user, issue session, set role | `authService.login()`, `LoginPage` | Public (Requires valid credentials) | `users`, `responders` | No |
| 7 | `POST` | `/api/auth/register` | `AuthController::register` | Self-register new student account | `authService.register()`, `RegisterPage` | Public (Auto-assigned `student` role) | `users` | No |
| 8 | `POST` | `/api/auth/logout` | `AuthController::logout` | Terminate session & clear cookies | `authService.logout()`, `Navbar` | Authenticated session | None (`$_SESSION`) | No |
| 9 | `GET` | `/api/incidents` | `IncidentController::index` | Scoped list of incidents by role & category | `incidentService.getIncidents()`, `AdminDashboardPage`, `ResponderDashboardPage`, `IncidentTrackingPage` | Scoped by role: Public sees standard, Responders see active department, Admin sees all | `incidents`, `users`, `incident_assignments` | No |
| 10 | `POST` | `/api/incidents` | `IncidentController::create` | Create new report, triage AI classification, detect duplicates | `incidentService.createIncident()`, `ReportIncidentPage` | Public / Student / Responder / Admin | `incidents`, `incident_updates`, `audit_logs`, `incident_matches` | **Yes (Gemini API for standard reports)** |
| 11 | `GET` | `/api/incidents/{id}` | `IncidentController::show` | Get detailed incident report with timeline & assignments | `incidentService.getIncidentById()`, `AdminDashboardPage`, `ResponderDashboardPage` | Confidential reports restricted to Admin, Reporter, or Category Responder | `incidents`, `incident_updates`, `incident_assignments`, `users` | No |
| 12 | `PUT` | `/api/incidents/{id}/status` | `IncidentController::updateStatus` | Update incident status (e.g. resolve, review) | `incidentService.updateStatus()`, `AdminDashboardPage`, `ResponderDashboardPage` | Restricted: `admin` or authorized `responder` | `incidents`, `incident_updates`, `incident_assignments`, `notifications`, `audit_logs` | No |
| 13 | `POST` | `/api/incidents/{id}/assign` | `IncidentController::assign` | Assign incident to designated responder | `incidentService.assignIncident()`, `AdminDashboardPage` | Restricted: `admin` role required | `incident_assignments`, `incidents`, `incident_updates`, `notifications`, `audit_logs` | No |
| 14 | `POST` | `/api/incidents/{id}/acknowledge` | `IncidentController::acknowledgeAssignment` | Responder acknowledges assigned incident call | `incidentService.acknowledgeAssignment()`, `ResponderDashboardPage` | Restricted: Assigned `responder` | `incident_assignments`, `incidents`, `incident_updates`, `audit_logs` | No |
| 15 | `POST` | `/api/sos/trigger` | `SosController::trigger` | Instant deterministic emergency SOS dispatch | `sosService.triggerSos()`, `FloatingSosButton`, `HomePage` | Public / Any authenticated user | `sos_alerts`, `audit_logs` | No |
| 16 | `GET` | `/api/sos/active` | `SosController::active` | Fetch active/open emergency SOS calls | `sosService.getActiveAlerts()`, `AdminDashboardPage`, `ResponderDashboardPage` | Public / Responder / Admin | `sos_alerts` | No |
| 17 | `POST` | `/api/sos/{id}/acknowledge` | `SosController::acknowledge` | Acknowledge active emergency SOS call | `sosService.acknowledgeSos()`, `AdminDashboardPage` | Restricted: `admin` or `responder` | `sos_alerts`, `audit_logs` | No |
| 18 | `POST` | `/api/sos/{id}/assign` | `SosController::assign` | Assign SOS call to responder unit | `sosService.assignSos()` (Service defined) | Restricted: `admin` role required | `sos_alerts`, `sos_assignments`, `notifications`, `audit_logs` | No |
| 19 | `POST` | `/api/sos/{id}/resolve` | `SosController::resolve` | Mark SOS as resolved, advance linked incidents | `sosService.resolveSos()`, `AdminDashboardPage`, `ResponderDashboardPage` | Restricted: `admin` or `responder` | `sos_alerts`, `incidents`, `incident_updates`, `incident_assignments`, `audit_logs` | No |
| 20 | `GET` | `/api/sos/contacts` | `SosController::contacts` | Static emergency helpline numbers | `sosService.getEmergencyContacts()` | Public (None) | None (Static array) | No |
| 21 | `GET` | `/api/responders` | `ResponderController::index` | Complete on-duty responder directory & status | `adminService.getResponders()`, `AdminDashboardPage`, `ResponderDashboardPage` | Authenticated session | `responders`, `users` | No |
| 22 | `GET` | `/api/responders/category/{cat}` | `ResponderController::byCategory` | List responders filtered by category | Diagnostic tests | Authenticated session | `responders`, `users` | No |
| 23 | `GET` | `/api/responders/my-assignments` | `ResponderController::myAssignments` | Retrieve active assigned incidents for current responder | `responderPortalService.getMyAssignments()`, `ResponderDashboardPage` | Restricted: `responder` role only | `incident_assignments`, `incidents` | No |
| 24 | `PUT` | `/api/responders/status` | `ResponderController::updateStatus` | Update responder availability (`available`, `offline`) | `responderPortalService.updateDutyStatus()`, `ResponderDashboardPage` | Restricted: `responder` role only | `responders`, `audit_logs` | No |
| 25 | `GET` | `/api/notifications` | `NotificationController::index` | Fetch current user notifications | `notificationService.getNotifications()` | Authenticated session | `notifications` | No |
| 26 | `PUT` | `/api/notifications/{id}/read` | `NotificationController::markRead` | Mark single notification as read | `notificationService.markRead()` | Authenticated recipient | `notifications` | No |
| 27 | `PUT` | `/api/notifications/read-all` | `NotificationController::markAllRead` | Mark all user notifications as read | `notificationService.markAllRead()` | Authenticated recipient | `notifications` | No |
| 28 | `GET` | `/api/admin/metrics` | `AdminController::dashboardMetrics` | Real-time counts (SOS, Incidents, Responders, Categories) | `adminService.getMetrics()`, `AdminDashboardPage` | Restricted: `admin` role required | `sos_alerts`, `incidents`, `responders`, `incident_assignments` | No |
| 29 | `GET` | `/api/admin/audit-logs` | `AdminController::auditLogs` | Retrieve security & operational audit trail | `adminService.getAuditLogs()`, `AdminDashboardPage` | Restricted: `admin` role required | `audit_logs`, `users` | No |
| 30 | `GET` | `/api/admin/matches` | `AdminController::matches` | Fetch pending candidate duplicate incidents | `adminService.getCandidateMatches()`, `AdminDashboardPage` | Restricted: `admin` role required | `incident_matches`, `incidents` | No |
| 31 | `POST` | `/api/admin/matches/{id}/review`| `AdminController::reviewMatch` | Human decision on duplicate (merge or dismiss) | `adminService.reviewMatch()`, `AdminDashboardPage` | Restricted: `admin` role required | `incident_matches`, `incidents`, `audit_logs` | No |
| 32 | `GET` | `/api/contacts` | `ContactController::index` | Retrieve official responder department contact list | `contactService.getContacts()`, `ContactPage`, `ResponderDashboardPage` | Public (None) | `department_contacts` | No |
| 33 | `GET` | `/api/contacts/{id}` | `ContactController::show` | Retrieve contact details for single department | `contactService.getContactById()` | Public (None) | `department_contacts` | No |
| 34 | `PUT` | `/api/contacts/{id}` | `ContactController::update` | Update department contact info & credentials | `contactService.updateContact()`, `ContactPage` (Admin edit modal) | Restricted: `admin` role required | `department_contacts`, `audit_logs` | No |

---

## 3. Deep Dive: Google Gemini API Integration

### 3.1 File Architecture & Initialization
The Google Gemini integration is strictly encapsulated in a single backend service file:
- **Implementation File:** `backend/services/GeminiClassificationService.php`
- **Class:** `CampusSOS\Services\GeminiClassificationService`
- **Frontend Isolation:** The frontend never connects to Google Gemini directly. No Gemini SDK or client-side keys exist in `frontend/`.

### 3.2 Configuration & Secret Loading
Environment variables are parsed during system bootstrap (`backend/bootstrap.php`) from `backend/.env`:
```ini
GEMINI_ENABLED=true
GEMINI_API_KEY=AIzaSy... (Masked)
GEMINI_MODEL=gemini-3.5-flash-lite
```
- **Key Retrieval:** `GeminiClassificationService::getApiKey()` reads `getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '')`.
- **Enabled Check:** `GeminiClassificationService::isGeminiEnabled()` verifies both that `GEMINI_ENABLED` evaluates to true and that a non-empty key is present.
- **Model Configuration:** Configurable via `GEMINI_MODEL`, defaulting to `gemini-3.5-flash-lite`.

### 3.3 HTTP Request Specification
When standard incident reports are submitted, the backend makes a direct REST call using PHP cURL:
- **Endpoint URL:** `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={apiKey}`
- **HTTP Method:** `POST`
- **HTTP Headers:** `Content-Type: application/json`
- **Timeouts:**
  - Connection Timeout: `2.0 seconds` (`CURLOPT_CONNECTTIMEOUT => 2`)
  - Execution Timeout: `4.0 seconds` (`CURLOPT_TIMEOUT => 4`)
  - SSL Verification: Explicitly enforced (`CURLOPT_SSL_VERIFYPEER => true`)

### 3.4 Payload Sanitization & System Prompt
To prevent student data leakage, input text is truncated and stripped of personal metadata before transmission:
- **Data Sent:**
  - Truncated Title: `mb_substr($title, 0, 150)`
  - Truncated Description: `mb_substr($description, 0, 500)`
- **Data Excluded:** Reporter names, emails, phone numbers, student ID cards, room numbers, and user IDs are **NEVER included in the prompt payload**.
- **System Instruction:**
  ```text
  You are a campus safety incident triage assistant. Analyze the following incident title and description.
  Output JSON with EXACTLY these fields:
  "suggestedCategory" (must be one of: medical, security, fire, maintenance, anti_ragging, sexual_harassment, other),
  "urgency" (must be one of: low, medium, high, critical),
  "confidence" (float between 0.0 and 1.0),
  "rationale" (short one-sentence explanation).
  ```
- **Generation Parameters:**
  - `responseMimeType: "application/json"`
  - `temperature: 0.1` (low temperature for deterministic classification)
  - `maxOutputTokens: 200`

### 3.5 Response Processing & Database Storage
The JSON response is parsed and validated against allowed categories:
- Validated Category: Mapped to one of 7 supported unit categories (`medical`, `security`, `fire`, `maintenance`, `anti_ragging`, `sexual_harassment`, `other`).
- Validated Urgency: Mapped to `low`, `medium`, `high`, or `critical`.
- Persistence: Stored in the `incidents` table:
  - `incidents.ai_classified` = `1` (or `0` if deterministic)
  - `incidents.ai_confidence` = float (e.g. `0.95`)
  - `incidents.severity` = urgency level
- Audit Trail: Logged to `audit_logs` under action `INCIDENT_CREATED` with classification source and confidence score.

### 3.6 Error Handling & Deterministic Fallback Engine
If Gemini encounters any of the following failure scenarios:
- Missing or disabled API key (`GEMINI_ENABLED=false`)
- Network unreachable or DNS resolution failure
- Connection timeout (> 2.0s) or execution timeout (> 4.0s)
- Google API rate-limit (HTTP 429) or quota exhaustion
- Invalid or malformed JSON returned by the model

The service executes `catch (\Throwable $e)`, logs a warning without leaking secrets (`error_log('[CampusSOS Gemini Service Warning]...')`), and immediately invokes the **Local Deterministic Classifier** (`GeminiClassificationService::classifyDeterministic()`):
- **Keyword Heuristic Dictionaries:**
  - **Fire:** `fire`, `smoke`, `flame`, `blaze`, `gas leak`, `explosion` -> Urgency: `critical`, Confidence: `0.95`
  - **Medical:** `ambulance`, `collapsed`, `bleeding`, `unconscious`, `seizure`, `injury` -> Urgency: `critical`, Confidence: `0.92`
  - **Security:** `assault`, `weapon`, `knife`, `theft`, `burglar`, `break-in` -> Urgency: `high`/`critical`, Confidence: `0.88`
  - **Maintenance:** `leak`, `pipe`, `elevator stuck`, `power outage`, `blackout` -> Urgency: `medium`, Confidence: `0.85`
  - **Anti-Ragging / Harassment:** `ragging`, `hazing`, `bullied`, `intimidation` -> Urgency: `high`, Confidence: `0.90`
- **Result:** Classification continues with zero user-facing downtime and zero external dependencies.

### 3.7 Confidential Intake & Strict Privacy Bypass
Student privacy is protected by an architectural hard-lock:
- If a report has `privacy_level === 'confidential'` OR belongs to sensitive categories (`sexual_harassment` or `anti_ragging`), `GeminiClassificationService::classifyIncident()` **aborts external transmission immediately** before any network socket is opened.
- The report is processed strictly via local deterministic rules, and returns `source = 'deterministic_privacy_rule'`.

---

## 4. Where External APIs Are NOT Used

A critical strength of CampusSOS is that external APIs are **not** over-engineered into features that require high reliability, data privacy, or deterministic speed.

| Feature Area | Implementation Approach | Reason External API is NOT Used |
|---|---|---|
| **Emergency SOS Dispatch** | `SosDispatchService.php` (Deterministic rules & direct PDO insert) | Emergency response must be deterministic (< 15ms) and cannot risk external network latency, timeouts, or API outages. |
| **Duplicate Incident Detection** | `IncidentMatcherService.php` (Local TF-IDF lexical, Haversine spatial, and temporal decay algorithms) | Cross-matching student incidents using third-party embeddings or LLMs would risk PII exposure and recurring inference costs. |
| **Campus Location Mapping** | `constants/locations.ts` & `LocationService.php` (25 predefined campus locations) | CampusSOS operates on a private university campus where GPS mapping APIs (e.g. Google Maps SDK) are unnecessary and prone to indoor inaccuracy. |
| **User Authentication & RBAC** | `AuthService.php` (Native bcrypt password hashes, PHP sessions, HTTP-only cookies) | Self-hosted campus authentication eliminates reliance on external identity providers (Auth0/Firebase) during campus network isolation. |
| **Operational Metrics & Reporting** | `AdminController::dashboardMetrics` (Direct SQL aggregate queries) | Real-time counts are computed directly from the local MariaDB database with sub-millisecond query execution. |
| **Confidential Incident Reports** | `classifyDeterministic()` (Deterministic keyword engine) | External AI models are legally and ethically prohibited from ingesting sensitive sexual harassment and ragging reports under campus privacy guidelines. |
| **Push Notifications / Dispatch** | Internal `notifications` table & polling | Operating over internal relational tables avoids third-party SMS (Twilio) or push notification service dependencies for campus kiosks. |

---

## 5. Security & Reliability Verification

### 5.1 Confirmed Security Strengths

1. **API Key Isolation:**
   - The `GEMINI_API_KEY` is loaded exclusively into backend memory via `putenv` and `$_ENV`.
   - Inspection of all frontend bundles (`dist/assets/*.js`) confirms **zero leakage of the Gemini API key, database credentials, or backend salts**.
2. **CORS Allowlist Security:**
   - `CampusSOS\Config\Cors` enforces a strict origin allowlist (`http://localhost:5173`, `http://127.0.0.1:5173`, `http://localhost:3000`, `http://127.0.0.1:3000`, or `FRONTEND_ORIGIN`).
   - Wildcard (`*`) origins are explicitly forbidden when credentials (`Access-Control-Allow-Credentials: true`) are enabled.
3. **Double-Submit CSRF Protection:**
   - `CsrfMiddleware::validate()` verifies tokens on all state-changing HTTP verbs (`POST`, `PUT`, `PATCH`, `DELETE`).
   - `ApiClient` in React automatically fetches fresh tokens (`/api/auth/csrf`), attaches `X-CSRF-Token`, and retries seamlessly upon token rotation.
4. **Credential & Stack Trace Sanitization:**
   - Global exception handling in `backend/bootstrap.php` catches unhandled exceptions and returns a generic `500 INTERNAL_SERVER_ERROR` without exposing database connection strings, paths, or stack traces.
5. **Role-Based Access Control (RBAC):**
   - Admin routes (`/api/admin/*`, `/api/contacts/{id}` PUT, `/api/incidents/{id}/assign` POST) enforce `RoleMiddleware::requireAdmin()`.
   - Responder routes (`/api/responders/my-assignments`, `/api/responders/status`) enforce responder authentication and category ownership.
   - Confidential reports enforce departmental isolation (e.g. medical responders cannot view confidential ICC or anti-ragging files).

### 5.2 Potential Improvements & Non-Critical Observations

1. **Static Location Duplication:**
   - `LocationController.php` exposes `GET /api/locations`, but the frontend currently references `frontend/src/constants/locations.ts`.
   - *Recommendation:* Keep as is for zero-latency UI rendering, or wire `ReportIncidentPage` to dynamically fetch `/api/locations` if campus buildings are frequently added by administrators.
2. **Unused Notification Polling in UI:**
   - The backend provides a complete notification API (`GET /api/notifications`, `PUT /api/notifications/{id}/read`, `PUT /api/notifications/read-all`), and `notificationService` is scaffolded in TypeScript, but no frontend component currently polls or renders an in-app notification dropdown.
   - *Recommendation:* Add an unread notification bell icon in `Navbar.tsx` for logged-in responders and administrators in a future sprint.
3. **Unused Route (`/api/responders/category/{cat}`):**
   - Endpoint `GET /api/responders/category/{category}` exists in `api.php` and is tested, but the UI consumes `GET /api/responders` and filters on the client or in state.
   - *Recommendation:* Retain for external dispatch integrations.

---

## 6. Audit Summary

| Metric / Question | Value | Details |
|---|:---:|---|
| **Total Internal Endpoints** | **34** | Verified in `backend/routes/api.php` across 9 controllers. |
| **External APIs Called** | **1** | Google Gemini API (`generativelanguage.googleapis.com`). |
| **Features Using Gemini** | **1** | Standard incident triage & category/urgency suggestion in `POST /api/incidents`. |
| **Features Operating Without Gemini** | **All Others** | Emergency SOS, duplicate matching, auth, responder roster, metrics, tracking, contacts, and confidential intakes. |
| **Primary API Client File** | `frontend/src/services/api.ts` | Centralized `ApiClient` utilizing native `fetch`, CSRF injection, and credentials handling. |
| **Primary AI Service File** | `backend/services/GeminiClassificationService.php` | Encapsulates Gemini cURL request, timeout management, privacy bypass, and keyword fallback. |
| **Unused Endpoints / Scaffolded Services** | **2** | `GET /api/locations` (UI uses static constants), `NotificationController` endpoints (UI doesn't poll notifications). |
| **Security Status** | **VERIFIED** | Gemini key isolated to backend; strict CORS & CSRF enforced; confidential cases bypass AI; zero credential leakage. |

---

*End of CampusSOS API Usage Audit Report.*


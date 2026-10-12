# CampusSOS — Architecture & System Design Diagrams

> **Student Project Expo 2026 // Technical Architecture Guide**  
> Comprehensive visual diagrams for judges, presentation defense, and system documentation.

---

## 1. High-Level System Architecture

CampusSOS follows a tiered, modular, and privacy-first architectural model. Presentation, application logic, persistence, and triage services are decoupled.

```mermaid
graph TD
    subgraph PresentationTier ["1. Presentation Tier (Client)"]
        UI_Student["Student Portal<br/>(React 18 + TS + Vite)"]
        UI_Responder["Responder Console<br/>(Discipline-Scoped Views)"]
        UI_Admin["Admin Management<br/>(Triage & Audit Dashboard)"]
        UI_SOS["Persistent Floating SOS<br/>(Global Modal Component)"]
    end

    subgraph SecurityGateway ["2. Transport & Security Middleware"]
        HTTPS["TLS / HTTPS Transport"]
        CORS["Strict CORS Allowlist"]
        CSRF["64-Char CSRF Token Validation"]
        SESSION["HttpOnly + SameSite=Lax Sessions<br/>(Regenerated on Auth)"]
        ROLE_CHECK["Role Authorization Guard<br/>(student | responder | admin)"]
    end

    subgraph APILogic ["3. Application API Tier (PHP 8.2+)"]
        ROUTER["REST Front Controller & Router<br/>(backend/public/index.php)"]
        CONTROLLERS["Domain Controllers<br/>(Auth, Incidents, SOS, Responders, Admin, Matching)"]
        MODELS["Data Access Objects / Models<br/>(Native PDO Prepared Statements)"]
        SAN_EXP["Exception Sanitizer & JSON Responder<br/>(Zero Credential/Stack Trace Leaks)"]
    end

    subgraph PersistenceEngine ["4. Persistence Engine (MySQL / MariaDB)"]
        DB[(campussos Database<br/>13 InnoDB Relational Tables<br/>Foreign Keys + CHECK Constraints)]
    end

    subgraph TriageServices ["5. Triage & Intelligence Services"]
        HEURISTIC["Deterministic Matching Engine<br/>(Weighted 4-Factor Heuristics: < 1ms)"]
        GEMINI["Optional Sidecar AI<br/>(Gemini 1.5 Flash - 3.0s Timeout)<br/>[Strict Local Fallback]"]
    end

    UI_Student --> HTTPS
    UI_Responder --> HTTPS
    UI_Admin --> HTTPS
    UI_SOS --> HTTPS

    HTTPS --> CORS --> CSRF --> SESSION --> ROLE_CHECK --> ROUTER
    ROUTER --> CONTROLLERS
    CONTROLLERS --> MODELS
    CONTROLLERS --> HEURISTIC
    CONTROLLERS -.->|Non-sensitive only| GEMINI
    MODELS --> DB
    CONTROLLERS --> SAN_EXP
```

---

## 2. Dual-Pathway Workflow Architecture

CampusSOS strictly isolates **Emergency Life-Safety Dispatch** from **Routine Concern Governance**.

```mermaid
flowchart TD
    START((User Enters Portal)) --> DECIDE{Urgency Level?}

    %% Emergency Branch
    DECIDE -->|Urgent Emergency| SOS_CLICK["Student Triggers Floating SOS Button"]
    SOS_CLICK --> SOS_MODAL["1-Click Building / Landmark Selection"]
    SOS_MODAL --> SOS_DIRECT["Direct Sub-4ms Local Database Commit"]
    SOS_DIRECT --> SOS_DISPATCH["Simultaneous Push Alert Generation"]
    SOS_DISPATCH --> QRT["Campus Security Quick Reaction Team"]
    SOS_DISPATCH --> MED["Campus Medical Paramedic Unit"]
    SOS_DISPATCH --> BANNER["High-Visibility Siren Banner on All Portals"]

    %% Routine Branch
    DECIDE -->|Non-Emergency Concern| REPORT_FORM["Structured Incident Intake Form"]
    REPORT_FORM --> PRIV_SELECT{Privacy Tier?}
    
    PRIV_SELECT -->|Identified| R_IDENT["Store reporter_user_id<br/>Personal Tracker Enabled"]
    PRIV_SELECT -->|Anonymous| R_ANON["Sanitize reporter_user_id = NULL<br/>Display: 'Anonymous Student'"]
    PRIV_SELECT -->|Confidential| R_CONF["Isolate Narrative to ICC / Proctor<br/>Bypass AI to Protect Privacy"]

    R_IDENT --> TRIAGE["Incident Matching Engine"]
    R_ANON --> TRIAGE
    R_CONF --> TRIAGE

    TRIAGE --> SCORE{"Match Score >= 0.60?"}
    SCORE -->|Yes| PENDING["Flag as 'pending_review'<br/>Display Side-by-Side in Admin"]
    SCORE -->|No| QUEUE["Queue for Responder Assignment"]
```

---

## 3. Deterministic Incident Matching Engine

The multi-factor incident deduplication algorithm provides transparent, auditable scoring without black-box machine learning dependencies.

### Mathematical Formulation
$$\text{Total Score} = 0.25 \cdot S_{\text{category}} + 0.35 \cdot S_{\text{location}} + 0.20 \cdot S_{\text{time}} + 0.20 \cdot S_{\text{lexical}}$$

```mermaid
flowchart LR
    NEW[New Incident Submitted] --> EVAL[Triage Evaluator]
    
    subgraph DimensionScoring ["4 Scoring Dimensions"]
        EVAL --> C[Category Match<br/>Weight: 0.25<br/>Exact safety type]
        EVAL --> L[Location Proximity<br/>Weight: 0.35<br/>Landmark + Haversine sub-50m]
        EVAL --> T[Temporal Proximity<br/>Weight: 0.20<br/>Decay over 30m - 180m]
        EVAL --> K[Lexical Overlap<br/>Weight: 0.20<br/>Jaccard on non-stop words]
    end

    C --> SUM[Weighted Sum Calculation]
    L --> SUM
    T --> SUM
    K --> SUM

    SUM --> THRESHOLD{Score >= 0.60?}
    THRESHOLD -->|True| FLAG[Create candidate record in incident_matches<br/>status: pending_review]
    THRESHOLD -->|False| INDEPENDENT[No correlation flagged]

    FLAG --> HUMAN[Dispatcher Review Console]
    HUMAN --> MERGE[Dispatcher confirms duplicate]
    HUMAN --> DISMISS[Dispatcher dismisses match]
```

---

## 4. Entity-Relationship Diagram (13 Relational Tables)

All tables use MySQL/MariaDB InnoDB with foreign key constraints, compound indexes, and database-level check constraints.

```mermaid
erDiagram
    users ||--o{ incidents : "submits"
    users ||--o{ responders : "has_profile"
    users ||--o{ audit_logs : "triggers"
    users ||--o{ notifications : "receives"
    users ||--o{ push_subscriptions : "registers"

    incidents ||--o{ incident_updates : "has_status_history"
    incidents ||--o{ incident_assignments : "assigned_to"
    incidents ||--o{ incident_attachments : "contains"
    incidents ||--o{ incident_matches : "matched_as_A"
    incidents ||--o{ incident_matches : "matched_as_B"
    incidents ||--o{ sos_alerts : "promoted_from"

    responders ||--o{ incident_assignments : "undertakes"
    responders ||--o{ incident_updates : "posts"

    users {
        int id PK
        varchar email UK
        varchar password_hash
        varchar full_name
        varchar role "student | responder | admin"
        varchar phone
        datetime created_at
    }

    incidents {
        int id PK
        varchar tracking_number UK
        int reporter_user_id FK "NULL if anonymous"
        varchar title
        varchar category
        varchar status "reported|under_review|dispatched|in_progress|resolved"
        varchar priority "low|medium|high|critical"
        varchar privacy_level "identified|anonymous|confidential"
        varchar location_name
        decimal latitude
        decimal longitude
        datetime created_at
    }

    incident_matches {
        int id PK
        int incident_a_id FK
        int incident_b_id FK
        decimal similarity_score
        varchar match_reasons
        varchar status "pending_review|confirmed_duplicate|dismissed"
        int reviewed_by FK
        datetime created_at
    }

    sos_alerts {
        int id PK
        varchar alert_code UK
        int user_id FK
        varchar location_name
        decimal latitude
        decimal longitude
        varchar status "triggered|acknowledged|dispatched|resolved|false_alarm"
        int acknowledged_by FK
        datetime created_at
    }

    responders {
        int id PK
        int user_id FK
        varchar badge_number UK
        varchar discipline "security|medical|fire|maintenance|counseling|proctor"
        varchar current_status "available|dispatched|busy|off_duty"
    }

    audit_logs {
        int id PK
        int user_id FK
        varchar action
        varchar entity_type
        int entity_id
        json old_values
        json new_values
        datetime created_at
    }
```

---

## 5. Security & Privacy Enforcement Architecture

```mermaid
sequenceDiagram
    autonumber
    actor Student as Student Client
    participant Auth as Session & CSRF Guard
    participant API as PHP REST Controller
    participant DB as MySQL InnoDB
    participant AI as Gemini 1.5 Flash (Optional)

    Note over Student, Auth: Registration & Login Flow
    Student->>Auth: POST /api/auth/register (role: 'student')
    Note over Auth: Hardened Role Whitelist:<br/>Role is unconditionally forced to 'student'.<br/>Client-supplied role parameters are discarded.
    Auth->>DB: INSERT INTO users (role = 'student')
    Student->>Auth: POST /api/auth/login
    Auth->>Auth: session_regenerate_id(true)
    Auth-->>Student: Set-Cookie: PHPSESSID (HttpOnly, SameSite=Lax) + 64-char CSRF Token

    Note over Student, DB: Anonymous Incident Reporting Flow
    Student->>API: POST /api/incidents (is_anonymous: true, category: 'theft')
    Note over API: Privacy Scrubbing:<br/>reporter_user_id is overwritten with NULL
    API->>DB: INSERT INTO incidents (reporter_user_id = NULL, is_anonymous = 1)
    DB-->>API: Incident ID Generated
    API-->>Student: Returns tracking_number (Identity never exposed)

    Note over Student, AI: Confidential Anti-Ragging Flow
    Student->>API: POST /api/incidents (category: 'anti_ragging')
    Note over API: AI Privacy Guard Triggered:<br/>Sensitive categories NEVER call Gemini API
    API--xAI: [External AI Call Suppressed]
    API->>API: Run Local Deterministic Keyword Heuristics
    API->>DB: Commit Incident (Flagged Confidential)
    API-->>Student: 201 Created (Strictly Isolated)
```

---

## 6. Architecture Highlights for Technical Defense

| Dimension | Architectural Implementation | Benefit / Protection |
|---|---|---|
| **SQL Injection** | Native PDO Prepared Statements (`ATTR_EMULATE_PREPARES => false`) | Total immunity from SQL parameter tampering |
| **Privilege Escalation** | Rigid role enum checks in controller middleware | Students cannot access responder queues or admin tables |
| **Session Hijacking** | `HttpOnly`, `SameSite=Lax`, `session_regenerate_id(true)` | Protects session tokens from XSS theft and fixation |
| **Data Leakage** | Anonymous reports set `reporter_user_id = NULL` at database level | Impossible for staff to retrieve student ID via queries |
| **Denial of Service (AI)** | 3.0s cURL timeout with sub-2ms deterministic fallback | Emergency triage never stalls due to cloud network drops |
| **Duplicate Collisions** | MySQL check constraint `CHECK (incident_a_id < incident_b_id)` | Prevents duplicate cycles ($A \leftrightarrow B$) and self-matching |


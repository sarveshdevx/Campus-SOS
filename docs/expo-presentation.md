# CampusSOS — Live Project Expo Demonstration Script

> **Official 5–7 Minute Timed Walkthrough & Technical Narration Protocol**
>
> *Safety Notice: All demonstration actions execute strictly in a local development environment. Emergency SOS alerts demonstrate campus-internal alert routing to simulated on-duty campus security and medical teams; they do not contact real emergency services or telecom carriers.*

---

## 1. Demonstration Setup & Timing Overview

| Segment | Timing | Presenter Focus | Active Role |
|---|---|---|---|
| **Segment 1** | 0:00 – 1:00 (60s) | Opening Introduction & Dual-Pathway Architecture | Public Landing Page |
| **Segment 2** | 1:00 – 2:15 (75s) | Student Authentication & Identified Incident Reporting | Student (`student@campussos.edu`) |
| **Segment 3** | 2:15 – 3:30 (75s) | Anonymous Intake & Safe Development SOS Activation | Student Portal |
| **Segment 4** | 3:30 – 4:45 (75s) | Responder Console, Duty Toggle & Acknowledgement | Responder (`medical@campussos.edu`) |
| **Segment 5** | 4:45 – 6:00 (75s) | Administrator Console & Multi-Factor Duplicate Review | Admin (`admin@campussos.edu`) |
| **Segment 6** | 6:00 – 6:45 (45s) | Gemini Triage Philosophy & Deterministic Fallback | Architectural Defense |
| **Segment 7** | 6:45 – 7:15 (30s) | Closing Statement & Transition to Judges Q&A | Summary |

---

## 2. Timed Step-by-Step Demonstration Script

### Segment 1: Opening Introduction (0:00 – 1:00)
- **Visual:** Display CampusSOS Landing Page (`http://localhost:5173/`).
- **Presenter Action:** Point to the editorial Neo-Brutalist hero composition, the campus photo cutouts, and the two clear entry points.
- **Presenter Script:**
  > *"Respected judges and faculty, welcome to our live demonstration of CampusSOS. Universities face a dual safety challenge: immediate life-safety crises require sub-second dispatch, while routine campus hazards and sensitive grievances require structured, privacy-preserving intake.*
  >
  > *CampusSOS solves this through two distinct pathways shown here on our landing page: the emergency action for urgent distress, and structured reporting for safety concerns across 6 campus disciplines."*
- **Expected Result:** Landing page renders cleanly with cream background, bold typography, photo elements, and active navigation.
- **Recovery If Fails:** If browser does not connect, verify Vite is running on port 5173 (`npm run dev` in `frontend/`).

---

### Segment 2: Student Login & Routine Incident Reporting (1:00 – 2:15)
- **Presenter Action:**
  1. Click **"Sign In"** in the top navigation bar.
  2. Enter demo credentials:
     - **Email:** `student@campussos.edu`
     - **Password:** `Password@2026`
  3. Click **"Log In"**.
  4. Once redirected, click **"Report Concern"** in the navigation bar.
  5. Fill in the routine safety concern form:
     - **Title:** `Damaged electrical conduit sparking near Lab 204`
     - **Category:** `Facility Maintenance`
     - **Location:** `Science Complex - Chemistry Labs Wing`
     - **Description:** `Sparks observed on ceiling conduit box creating an electrical hazard.`
  6. Click **"SUBMIT SAFETY REPORT"**.
- **Presenter Script:**
  > *"We begin by logging in as a student member. When our student encounters a safety issue—in this case, sparking electrical wires in the Science Complex—they open our structured reporting portal. Notice the clear categories and location landmarks. Upon submission, our PHP backend validates the input, executes our deterministic classification rules, and generates an official tracking number: INC-2026-XXXX."*
- **Expected Result:** Success card displays ticket number `INC-2026-XXXX` with buttons to track status or submit another concern.
- **MySQL Persistence Verification:** The record is inserted into the `incidents` table with `status = 'reported'` and `is_anonymous = 0`.
- **Recovery If Fails:** If submission times out or errors, verify PHP server is running on port 8000 (`/Applications/XAMPP/xamppfiles/bin/php -S localhost:8000 -t public` in `backend/`).

---

### Segment 3: Anonymous Reporting & Development Emergency SOS (2:15 – 3:30)
- **Presenter Action:**
  1. Click **"Incident Tracker"** in the navbar to show the live ticket in the student timeline.
  2. Point to the **Persistent Floating Emergency SOS Button** at the bottom-right corner.
  3. Click **"EMERGENCY SOS"**.
  4. Demonstrate the confirmation modal:
     - Show the current location selector (`Central Library - Floor 1`).
     - Point to the safety reassurance note: *“Deterministic instant dispatch enabled (No AI delay)”*.
  5. Click **"ACTIVATE SOS DISPATCH"**.
  6. Observe the immediate red emergency banner appearing across the top of the portal.
- **Presenter Script:**
  > *"Notice that throughout the student portal, a single, persistent Emergency SOS button is always accessible. If a student is in acute danger, they do not navigate menus or wait on AI processing. A single confirmation triggers our deterministic dispatch engine in less than 4 milliseconds.*
  >
  > *Immediately, a high-visibility emergency banner alerts the user, and concurrent alert records are routed to on-duty Campus Security and Medical teams in our database. It is important to clarify that this SOS alert represents internal campus quick-reaction dispatch, not external 911 police calling."*
- **Expected Result:** Modal closes instantly; red alert banner displays `#SOS-XXXX dispatched to Campus Security & Medical`; `sos_alerts` record created with status `triggered`.
- **Recovery If Fails:** If alert does not trigger, check browser developer tools network tab for CSRF token freshness (`/api/auth/csrf`).

---

### Segment 4: Responder Console & Discipline Scoping (3:30 – 4:45)
- **Presenter Action:**
  1. Click **"Responders"** in the top navigation bar.
  2. (Optional: Log in as `medical@campussos.edu` or view roster as responder).
  3. Point to the **Active Duty Status Toggle**:
     - Click **"BUSY"**, then **"AVAILABLE"**, observing the live badge update.
  4. Point to the assigned incident list:
     - Responders only see tickets matching their discipline (e.g. Medical responders only see medical calls).
  5. Click **"Acknowledge Assignment"** on an open ticket.
- **Presenter Script:**
  > *"Now let us switch to the perspective of campus first responders. In the Responders Console, safety officers can toggle their on-duty status between Available, Busy, and Offline. Notice that responders are partitioned into 6 specialized disciplines—medical, security, fire, maintenance, anti-ragging, and confidential ICC.*
  >
  > *When a responder acknowledges an assignment, the status transitions in real time to 'in_progress', and chronological notes are recorded into our immutable audit history."*
- **Expected Result:** Duty status badge updates immediately; incident assignment updates to `in_progress`; database updates `incident_updates` and `audit_logs`.
- **Recovery If Fails:** If unauthorized error occurs, log in via `/login` with credentials `medical@campussos.edu` / `Password@2026`.

---

### Segment 5: Administrator Console & Duplicate Incident Matching (4:45 – 6:00)
- **Presenter Action:**
  1. Navigate to **"Admin"** (log in as `admin@campussos.edu` / `Password@2026` if prompted).
  2. Point to the **Operations Metrics Overview**:
     - Real-time counts of active emergencies, open concerns, and category breakdown.
  3. Click the **"Candidate Matches"** tab:
     - Point to candidate duplicate pair: `INC-2026-DEMO-01` and `INC-2026-DEMO-02`.
     - Highlight the transparent similarity score ($0.88$) and rationale breakdown:
       - Category Match: Maintenance ($0.25$)
       - Spatial Proximity: Science Complex ($0.35$)
       - Temporal Window: within 45 minutes ($0.20$)
       - Overlapping keywords: "electrical", "corridor", "sparks" ($0.08$)
  4. Explain the check constraint safeguard:
     - Table constraint `incident_a_id < incident_b_id` prevents duplicate reversed pairs.
  5. Click **"Confirm Duplicate"** or **"Dismiss"**.
- **Presenter Script:**
  > *"In the Administrator Console, campus safety directors have complete oversight. A frequent operational headache is multiple students reporting the exact same hazard. Here, our multi-factor matching engine has identified two reports in the Science Complex occurring within 45 minutes of each other with an 88% similarity score.*
  >
  > *Most importantly, CampusSOS enforces a strict human-in-the-loop policy. The algorithm never automatically deletes or merges reports. The dispatcher reviews the evidence side-by-side and makes the final determination, which is immutably logged for campus compliance."*
- **Expected Result:** Candidate pair displays side-by-side comparison; clicking review updates candidate status to `confirmed_duplicate` and logs reviewer ID to `incident_matches`.
- **Recovery If Fails:** Run demo seeding script in terminal if demo pairs are missing: `/Applications/XAMPP/xamppfiles/bin/php backend/bin/seed-demo-data.php --seed`.

---

### Segment 6: AI Philosophy & Deterministic Fallback (6:00 – 6:45)
- **Presenter Action:**
  1. Open terminal window or show architectural slide.
  2. Briefly explain the Gemini classification service code:
     - 3.0s cURL timeout ceiling.
     - Automatic privacy bypass for Title IX / Anti-Ragging.
     - Zero AI invocation for Emergency SOS.
     - Instant local keyword fallback.
- **Presenter Script:**
  > *"Before we conclude, we must address our AI architecture. While we have integrated optional Google Gemini AI for smart category and urgency triage, we engineered it with strict safety boundaries:*
  >
  > *First, Emergency SOS never touches AI. Second, confidential complaints regarding sexual harassment or hazing strictly bypass external AI to guarantee student privacy. Third, if our Gemini API key is missing, rate-limited, or offline, our built-in keyword heuristic engine classifies the incident in under 2 milliseconds. The application never fails or blocks a student submission."*
- **Expected Result:** Clear architectural reassurance demonstrating defense-in-depth and engineering maturity.

---

### Segment 7: Closing Statement & Transition to Q&A (6:45 – 7:15)
- **Presenter Script:**
  > *"In summary, CampusSOS is not a static mockup—it is a functional, tested application backed by 94 passing automated tests and a robust relational database. It provides instant emergency response, ironclad student privacy, and accountable dispatcher triage.*
  >
  > *Thank you for your time, and we now welcome any questions from the panel."*

---


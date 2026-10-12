# CampusSOS — Database Architecture, phpMyAdmin Setup & PDO Integration (Phase 2)

Complete guide for administering the **CampusSOS** relational database (`campussos`) using **phpMyAdmin in XAMPP on macOS**, establishing the PHP PDO connection layer, and verifying system health.

---

## 1. Database Specifications

- **Database Engine:** MySQL 8.0+ or MariaDB 10.4+
- **Table Engine:** `InnoDB` (ACID compliance, foreign key cascade/restrict guarantees, row-level locking)
- **Character Set:** `utf8mb4` (Full multilingual and Unicode character support)
- **Collation:** `utf8mb4_unicode_ci` (Accurate sorting and case-insensitive equality)
- **Primary Keys:** Unsigned auto-incrementing integers (`INT UNSIGNED AUTO_INCREMENT`)
- **Foreign Keys:** Explicit constraints with safe deletion rules:
  - Emergency historical records (`incidents`, `sos_alerts`, `audit_logs`) use `ON DELETE SET NULL` to ensure emergency and security history is never erased when accounts are closed.
  - Tightly coupled dependent records (`emergency_contacts`, `incident_attachments`, `incident_updates`, `notifications`) use `ON DELETE CASCADE`.

---

## 2. Relational Schema Architecture (13 Tables)

```
                       ┌────────────────────────┐
                       │        locations       │
                       └───────────┬────────────┘
                                   │ 1:N
┌──────────────────────┐           ▼            ┌────────────────────────┐
│        users         ├───────────────────────►│       incidents        │
└──────────┬───────────┘ 1:N                    └───────────┬────────────┘
           │ 1:1                                            │ 1:N
           ▼                                                ├──► incident_updates
┌──────────────────────┐                                    ├──► incident_assignments
│      responders      │                                    ├──► incident_attachments
└──────────┬───────────┘                                    └──► incident_matches (N:M pair)
           │ 1:N
           ▼
┌──────────────────────┐
│   sos_assignments    │◄─── sos_alerts (1:N)
└──────────────────────┘
```

### Table Reference:

| # | Table Name | Purpose & Cardinality | Privacy & Safety Implementation |
|---|---|---|---|
| 1 | `users` | Students, Responders, and Administrators | Bcrypt hashes (cost 12); passwords never stored in plaintext; soft `account_status` (`active`, `suspended`, `pending`). |
| 2 | `emergency_contacts` | User next-of-kin contacts (1:N with `users`) | Accessible only to authorized emergency dispatchers and account owner. `ON DELETE CASCADE`. |
| 3 | `locations` | Standardized campus buildings and landmarks | Optional coordinates allow indoor landmarks and room-level locations without requiring GPS triangulation. |
| 4 | `responders` | Authorized safety personnel duty profiles (1:1 with `users`) | Partitioned into 6 categories: `medical`, `security`, `fire`, `maintenance`, `anti_ragging`, `sexual_harassment`. |
| 5 | `incidents` | Core safety concern intake and tracking | `reporter_user_id` is nullable; for anonymous reports (`is_anonymous=1`), API responses strip reporter identity. `is_confidential=1` restricts viewing to authorized investigators. `ON DELETE SET NULL`. |
| 6 | `sos_alerts` | Urgent life-safety emergency signals | Instant deterministic dispatch. Tracks `triggered_at`, `acknowledged_at`, and `resolved_at`. Retains history via `ON DELETE SET NULL`. |
| 7 | `sos_assignments` | Multi-team dispatch to an emergency SOS alert (N:M) | Tracks individual unit acknowledgement, en-route, and arrival timestamps for multi-department responses. |
| 8 | `incident_assignments`| Multi-responder assignment for routine cases (N:M) | Connects specialized personnel to complex incidents (e.g., electrical maintenance + campus security). |
| 9 | `incident_attachments`| Evidence photos (Max 2 photos per incident) | Strictly private attachments accessible only to assigned investigators and authorized proctors. |
| 10 | `incident_matches` | Candidate duplicate reports for human review | Pair ordering constraint `CHECK (incident_a_id < incident_b_id)` prevents duplicate reversed pairs `(A,B)` vs `(B,A)` and prevents self-matching. Unique key `uq_incident_pair`. |
| 11 | `incident_updates` | Chronological status transitions and investigator notes | Immutable audit log of lifecycle status changes with `actor_user_id` attribution (`ON DELETE SET NULL`). |
| 12 | `notifications` | In-app alerts for students and responders | Concise summary notifications without leaking confidential details or sensitive reporter identities. |
| 13 | `audit_logs` | Immutable security audit trail | Records user action, resource, IP address, and timestamp. **NEVER stores passwords, session tokens, or API secrets.** |

---

## 3. Step-by-Step phpMyAdmin Import Instructions (XAMPP on macOS)

### Method A: Create Database in phpMyAdmin First (Recommended)

1. **Start Services in XAMPP:**
   - Open `/Applications/XAMPP/manager-osx.app`.
   - On the **Manage Servers** tab, start **MySQL Database** and **Apache Web Server**.
2. **Access phpMyAdmin:**
   - In your web browser, open:
     ```
     http://localhost/phpmyadmin/
     ```
3. **Inspect Existing Databases:**
   - Check the left sidebar. If `campussos` already exists with important data, perform a backup first (see Section 6).
4. **Create the `campussos` Database:**
   - Click **New** in the left sidebar.
   - Enter Database name: `campussos`
   - Select Collation: `utf8mb4_unicode_ci`
   - Click **Create**.
5. **Import Schema DDL:**
   - Click on `campussos` in the left sidebar.
   - Click the **Import** tab in the top navigation bar.
   - Under **File to import**, click **Choose File** and select `database/schema.sql`.
   - Ensure the Format is set to **SQL**.
   - Click **Import** (or **Go**) at the bottom.
   - phpMyAdmin will display: *"Import has been successfully finished, XX queries executed."*
6. **(Optional) Import Safe Demonstration Seed Data:**
   - While still on the `campussos` database, click the **Import** tab again.
   - Click **Choose File** and select `database/seed.sql`.
   - Click **Import** (or **Go**).
7. **Verify Table Creation:**
   - Confirm that all 13 tables are present in the left panel.
   - Click the **Structure** tab to confirm table engine `InnoDB` and collation `utf8mb4_unicode_ci`.

---

### Method B: Command-Line MySQL CLI Import (Fast Alternative)

If MySQL binaries are available in your shell:

```bash
# 1. Create database and import schema
mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS campussos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -h 127.0.0.1 campussos < database/schema.sql

# 2. (Optional) Load demonstration seed data
mysql -u root -h 127.0.0.1 campussos < database/seed.sql
```

---

## 4. PHP Database Configuration & Health-Check Verification

### 1. Configure Local Environment (`backend/.env`)

Copy the configuration template to `backend/.env`:

```bash
cp backend/.env.example backend/.env
```

Ensure your `backend/.env` file matches your local XAMPP credentials:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=campussos
DB_USER=root
DB_PASS=
```

> **Note on XAMPP Defaults:** Default XAMPP installations on macOS use user `root` with an **empty** password (`DB_PASS=`). If you set a root password in phpMyAdmin User Accounts, set `DB_PASS=your_password`.

### 2. Start the PHP Backend Server

```bash
cd backend
php -S localhost:8000 -t public
```

### 3. Verify Database Health Check

Test the database health-check endpoint via browser or curl:

```bash
curl -s http://localhost:8000/api/health/db
```

**Expected JSON Response (HTTP 200 OK):**
```json
{
  "success": true,
  "data": {
    "connected": true,
    "status": "healthy",
    "database": "campussos",
    "driver": "mysql",
    "latency_ms": 1.45
  },
  "message": "Database connection verified.",
  "meta": {
    "timestamp": "2026-10-11T10:50:00Z"
  }
}
```

**If MySQL is stopped or credentials are wrong (HTTP 503 Service Unavailable):**
```json
{
  "success": false,
  "message": "Unable to establish database connection. Verify MySQL service in XAMPP.",
  "meta": {
    "timestamp": "2026-10-11T10:50:00Z"
  },
  "errorCode": "DATABASE_UNAVAILABLE"
}
```

---

## 5. Troubleshooting Common XAMPP & MySQL Issues

| Issue | Root Cause | Solution |
|---|---|---|
| `Connection refused (2002)` | MySQL service in XAMPP is not running | Open XAMPP Control Panel and start MySQL Database. Check `/Applications/XAMPP/xamppfiles/logs/mysql_error.log`. |
| `Access denied for user 'root'@'localhost'` | Password mismatch in `.env` | Default XAMPP root password is blank (`DB_PASS=`). If a password was created in phpMyAdmin, update `DB_PASS` in `backend/.env`. |
| Port 3306 already in use | Conflicting local Homebrew MySQL or MariaDB instance | Run `lsof -i :3306` to identify the conflicting process, or configure XAMPP to port 3307 and set `DB_PORT=3307` in `backend/.env`. |
| UNIX Socket connection failure on macOS | `localhost` resolves to missing `/tmp/mysql.sock` | Use `DB_HOST=127.0.0.1` to force TCP/IP networking, or set `DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`. |
| `CHECK constraint failed: chk_incident_pair_order` | Inserting duplicate candidate matches with `incident_a >= incident_b` | Ensure the smaller incident ID is placed in `incident_a_id` and the larger in `incident_b_id`. |
| phpMyAdmin file upload limit exceeded | `upload_max_filesize` in `php.ini` is lower than SQL dump | `schema.sql` is lightweight (< 20 KB), but for large dumps, increase `upload_max_filesize = 64M` in `/Applications/XAMPP/xamppfiles/etc/php.ini`. |

---

## 6. Database Safety & Pre-Migration Backup Procedure

Before applying future schema migrations or modifying production structures:

### Backup via Command Line:
```bash
mysqldump -u root -h 127.0.0.1 campussos > database/backups/campussos_backup_$(date +%Y%m%d_%H%M%S).sql
```

### Backup via phpMyAdmin:
1. Click on the `campussos` database in phpMyAdmin.
2. Select the **Export** tab.
3. Choose **Quick** export method and Format **SQL**.
4. Click **Export** to download the timestamped `.sql` backup file.

---

## 7. Provisioning Administrator Accounts (CLI Script)

To provision administrative accounts safely into the `users` table without unauthenticated HTTP endpoints:

```bash
# Run CLI script in terminal
php backend/bin/create-admin.php

# Or with automated random password generation
php backend/bin/create-admin.php --email="admin@campussos.edu" --name="Campus Safety Admin"
```
Password hashes are computed via `password_hash(..., PASSWORD_DEFAULT, ['cost' => 12])` and an immutable record is automatically entered into `audit_logs`.


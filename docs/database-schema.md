# Aeterna — Database Schema (`database-schema.md`)

MySQL 8 · InnoDB · `utf8mb4_unicode_ci`
Entities are those listed in §4 of the cahier des charges. No entity outside that list is added,
except the association tables required by the N,N relations and the two support tables justified
in §4 below (`permissions`, `payments`).

---

## 1. MCD — Conceptual model

**Entities**

| Entity | Role |
|---|---|
| Organization | Tenant of the platform |
| User | Platform / organization account |
| Role | Set of permissions |
| Alumni | Former student of an organization |
| Event | Event organized by an organization |
| Campaign | Email campaign of an organization |
| Subscription | Subscription of an organization to a plan |
| Plan | Commercial offer with limits and pricing |

**Relations**

| Relation | Cardinality |
|---|---|
| Organization → Alumni | 1,N |
| Organization → User | 1,N |
| Organization → Event | 1,N |
| Organization → Campaign | 1,N |
| Organization → Subscription | 1,N |
| Plan → Subscription | 1,N |
| Role → User | 1,N |
| Organization → Role | 1,N (organization roles; system roles have no organization) |
| Alumni ↔ Event | N,N — *registration*, carries registration status and attendance |
| Alumni ↔ Campaign | N,N — *recipient*, carries delivery status |
| Role ↔ Permission | N,N |
| Subscription → Payment | 1,N |

---

## 2. MLD — Relational model

```
organizations (id, name, slug, email, phone, address, logo_path, is_active, created_at, updated_at, deleted_at)

plans (id, name, slug, description, price_monthly, price_yearly,
       max_alumni, max_events, max_campaigns, max_users,
       paddle_price_monthly_id, paddle_price_yearly_id, is_active, is_custom,
       created_at, updated_at)

subscriptions (id, #organization_id, #plan_id, billing_cycle, status,
               price, starts_at, ends_at, canceled_at, created_at, updated_at)

payments (id, #subscription_id, #organization_id, amount, currency, status,
          provider, provider_reference, paid_at, created_at, updated_at)

permissions (id, name, slug, module, created_at, updated_at)

roles (id, #organization_id, name, slug, description, is_system, guard, created_at, updated_at, deleted_at)

permission_role (#permission_id, #role_id)

users (id, #organization_id, #role_id, name, email, password, is_active,
       email_verified_at, remember_token, created_at, updated_at, deleted_at)

alumni (id, #organization_id, first_name, last_name, email, phone, degree,
        graduation_year, company, job_title, city, created_at, updated_at, deleted_at)

events (id, #organization_id, #created_by, title, description, location,
        starts_at, ends_at, capacity, status, created_at, updated_at, deleted_at)

alumni_event (id, #alumni_id, #event_id, status, registered_at, attended, attended_at,
              created_at, updated_at)

campaigns (id, #organization_id, #created_by, subject, content, status,
           recipients_count, sent_at, created_at, updated_at, deleted_at)

alumni_campaign (id, #alumni_id, #campaign_id, status, sent_at, error_message,
                 created_at, updated_at)
```

`#` = foreign key.

---

## 3. Multi-tenancy rule

Tables carrying `organization_id` (isolated data): `users`, `roles`, `alumni`, `events`,
`campaigns`, `subscriptions`, `payments`.

Tables **without** `organization_id` (global): `organizations`, `plans`, `permissions`.

Pivots `alumni_event` and `alumni_campaign` inherit isolation from their parents — both sides
always belong to the same organization; this must be validated at application level.

`users.organization_id` and `roles.organization_id` are **nullable**: `NULL` identifies the Super
Administrator and the platform system roles.

---

## 4. Justification of the two support tables

- **`permissions` + `permission_role`** — required by §3.2 *"Attribution des rôles et permissions"*
  and §2.2 *"Staff: accès limité selon les permissions attribuées"*. Permissions cannot be granted
  without being stored.
- **`payments`** — required by §6 *"Intégration d'un système de paiement en ligne"*.
- **`organizations.logo_path`** — a column, not a table. The product is sold to
  establishments whose staff use it daily; showing them the vendor's mark rather
  than their own is a presentation defect. Nullable, and the Aeterna mark stays
  the fallback, so an organization that uploads nothing sees what it saw before.
  The file lives on the `public` disk; only its path is stored, because a BLOB
  in the row would be read by every query that touches the organization.

No other **domain** table is introduced. In particular, the *recent activity* of the dashboard
(§3.7) is computed from the latest `alumni`, `events` and `campaigns` rows — no activity log table.

### 4.1 Infrastructure tables (outside the rule)

The rule above governs the **entities of the MCD**. It does not govern tables the framework or a
required integration needs in order to function, none of which model a business concept:

- Laravel: `migrations`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
  `password_reset_tokens`, `notifications`. The queue tables are required by §3.5 (campaign sending is queued) and
  `password_reset_tokens` by §3.2 (password reset by email). `notifications` is Laravel's own
  table, required by F10; it carries no `organization_id` and needs none, because every row hangs
  off a notifiable and the only notifiable is an alumni, whose own row is tenant-scoped.
- Paddle (`laravel/cashier-paddle`, required by §6): `paddle_customers`, `paddle_subscriptions`,
  `paddle_subscription_items`, `paddle_transactions` — Cashier's own tables, renamed with a
  `paddle_` prefix so they do not collide with the `subscriptions` entity of §5.3.
  **They are a mirror of Paddle's state, not the domain model.** `subscriptions` and `payments`
  remain the source of truth and are written from Cashier's webhook events.

### 4.2 The two `plans` Paddle columns

`plans.paddle_price_monthly_id` and `plans.paddle_price_yearly_id` (§5.2) are required because §3.6
lets the Super Administrator define plans at runtime: each plan must carry the identifier of its
Paddle price, and a static configuration file cannot be edited by a user. They are nullable — a
plan with no Paddle price simply cannot be purchased online.

### 4.3 `plans.is_custom` — bespoke subscriptions

§3.6 lets the Super Administrator negotiate limits with a single organization. Rather than add a
second table of per-organization limits, a bespoke subscription creates an ordinary `plans` row
flagged `is_custom = 1` and `is_active = 0`, slugged `sur-mesure-{organization-slug}`.

Everything downstream then works unchanged: `PlanLimitService` reads `max_*` from the plan the
subscription points at, and knows nothing about how that plan was born. The two flags keep the
catalogue clean:

- `is_active = 0` — never offered to another organization, and never listed on the pricing page.
- `is_custom = 1` — hidden from the plans catalogue screen, and refused by the plan picker when
  assigning a subscription (`Plan::offered()`).

Its price is not typed in. `PricingCalculator` derives it from the chosen limits using the rates in
`config/pricing.php`: a base fee plus, per resource, a price per block of N (a part block is charged
whole) or a flat unlimited fee. The Super Administrator may still override the computed figure with
a negotiated one; the other cycle keeps its computed value so the row stays coherent.


### 4.4 Why alumni authenticate on their own guard

An alumni with a portal account (docs/project.md F9) signs in against the
`alumni` table on an `alumni` guard, not as a `users` row. They are a different
actor: they hold no role, must not appear on the Utilisateurs screen, and must
not count against `max_users`. A `users` row would break all three —
`users.role_id` is NOT NULL, and PlanLimitService counts that table.

So `alumni` gains a nullable `password` and the columns around it (§5.9).
NULL is the normal state: an account is the exception, and an organization on a
plan without the portal has none at all.

`login_token` (hashed, with its own expiry) serves "mot de passe oublie".
Laravel's password broker is deliberately not used: its token table is keyed on
the e-mail address alone, which collides for someone who is an alumni of two
establishments — exactly what the unique index on (organization_id, email)
allows.
---

## 5. Tables

### 5.1 `organizations`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| name | VARCHAR(150) | NOT NULL |
| slug | VARCHAR(160) | UNIQUE, NOT NULL |
| email | VARCHAR(150) | NOT NULL |
| phone | VARCHAR(30) | NULL |
| address | VARCHAR(255) | NULL |
| logo_path | VARCHAR(255) | NULL — path on the `public` disk; NULL falls back to the Aeterna mark |
| is_active | BOOLEAN | DEFAULT 1 |
| created_at / updated_at / deleted_at | TIMESTAMP | NULL |

### 5.2 `plans`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| name | VARCHAR(80) | NOT NULL |
| slug | VARCHAR(80) | UNIQUE |
| description | VARCHAR(255) | NULL |
| price_monthly | DECIMAL(10,2) | DEFAULT 0 |
| price_yearly | DECIMAL(10,2) | DEFAULT 0 |
| max_alumni | INT UNSIGNED | NULL = unlimited |
| max_events | INT UNSIGNED | NULL = unlimited |
| max_campaigns | INT UNSIGNED | NULL = unlimited |
| max_users | INT UNSIGNED | NULL = unlimited |
| paddle_price_monthly_id | VARCHAR(50) | NULL — Paddle price id for the monthly cycle |
| paddle_price_yearly_id | VARCHAR(50) | NULL — Paddle price id for the yearly cycle |
| is_active | BOOLEAN | DEFAULT 1 — on the catalogue, offered to every organization |
| is_custom | BOOLEAN | DEFAULT 0 — built for one organization (§4.3) |
| alumni_portal | BOOLEAN | DEFAULT 0 — the plan includes the alumni portal (§4.4) |

### 5.3 `subscriptions`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| plan_id | BIGINT UNSIGNED | FK → plans, RESTRICT |
| billing_cycle | ENUM('monthly','yearly') | NOT NULL |
| status | ENUM('pending','active','expired','canceled') | DEFAULT 'pending' |
| price | DECIMAL(10,2) | NOT NULL |
| starts_at | DATE | NOT NULL |
| ends_at | DATE | NOT NULL |
| canceled_at | TIMESTAMP | NULL |

Only one subscription per organization may have `status = 'active'` (enforced in application).

### 5.4 `payments`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| subscription_id | BIGINT UNSIGNED | FK → subscriptions, CASCADE |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| amount | DECIMAL(10,2) | NOT NULL |
| currency | CHAR(3) | DEFAULT 'USD' |
| status | ENUM('pending','paid','failed','refunded') | DEFAULT 'pending' |
| provider | VARCHAR(50) | NOT NULL |
| provider_reference | VARCHAR(191) | NULL, UNIQUE |
| paid_at | TIMESTAMP | NULL |

### 5.5 `permissions`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| name | VARCHAR(100) | NOT NULL |
| slug | VARCHAR(100) | UNIQUE |
| module | VARCHAR(50) | NOT NULL |

### 5.6 `roles`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | NULL, FK → organizations, CASCADE |
| name | VARCHAR(80) | NOT NULL |
| slug | VARCHAR(80) | NOT NULL |
| description | VARCHAR(255) | NULL |
| is_system | BOOLEAN | DEFAULT 0 |
| guard | VARCHAR(20) | DEFAULT 'web' — `web` for staff roles, `alumni` for the portal's (added 2026-09-17) |
| created_at / updated_at / deleted_at | TIMESTAMP | NULL |

UNIQUE (`organization_id`, `slug`) — so every organization may own a role called
`org-admin`, `staff` or `alumni` of its own.

Rows with `organization_id = NULL` and `is_system = 1` are **platform rows**:

- `super-admin` is held by the Super Administrator and by nobody else.
- `org-admin`, `staff` and `alumni` are **templates**. They are not held by
  anyone: each organization is provisioned with its own copies at creation
  (RoleProvisioner), so it can rename them and change their permissions without
  affecting any other tenant. The copies carry the organization's id and
  `is_system = 0`.
- The `alumni` copy (`guard = 'alumni'`) is held by EVERY alumni of the
  organization — the alumni table has no `role_id`; `Alumni::hasPermission()`
  reads the organization's one alumni role. Its permissions are the `portal.*`
  rows and nothing else (docs/project.md §5.4).

An organization therefore only ever sees and edits its own roles; the platform
rows are invisible from inside a tenant.

Roles are soft-deleted like the other tenant data (docs/project.md §8): an
organization's role carries a name, a description and a permission set it
configured, so it goes to the corbeille and can be restored. A role still held
by anybody — including a user sitting in the corbeille — cannot be deleted at
all, because `users.role_id` is RESTRICT and restoring that user would need the
role back.

### 5.7 `permission_role`

| Column | Type | Constraints |
|---|---|---|
| permission_id | BIGINT UNSIGNED | FK → permissions, CASCADE |
| role_id | BIGINT UNSIGNED | FK → roles, CASCADE |

PK (`permission_id`, `role_id`).

### 5.8 `users`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | NULL (NULL = super admin), FK → organizations, CASCADE |
| role_id | BIGINT UNSIGNED | FK → roles, RESTRICT |
| name | VARCHAR(120) | NOT NULL |
| email | VARCHAR(150) | UNIQUE (login is global) |
| password | VARCHAR(255) | NOT NULL, bcrypt |
| is_active | BOOLEAN | DEFAULT 1 |
| email_verified_at | TIMESTAMP | NULL |
| remember_token | VARCHAR(100) | NULL |

### 5.8b `job_applications`

What an alumni saved or applied to on the job board (docs/project.md F11).

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| alumni_id | BIGINT UNSIGNED | FK → alumni, CASCADE |
| external_id | VARCHAR(100) | emploi.ma's own id for the offer |
| title | VARCHAR(200) | snapshot |
| company | VARCHAR(150) | NULL — snapshot |
| location | VARCHAR(150) | NULL — snapshot |
| source | VARCHAR(100) | NULL — snapshot |
| link | TEXT | snapshot; validated http(s) only |
| status | ENUM('saved','applied') | DEFAULT 'saved' |
| applied_at | TIMESTAMP | NULL |

UNIQUE (`alumni_id`, `external_id`) · INDEX on (`organization_id`, `alumni_id`).

The offer fields are a **snapshot**, not a foreign key: offers live in emploi.ma's
index and in our cache, never in this table, and a listing that disappears must
not take the alumni's record of it with it. There is no `job_offers` table for
the same reason — the free key's 500-request lifetime means offers are cached,
not stored.

### 5.8c `alumni_imports`

One upload of an alumni spreadsheet (docs/project.md F12).

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| user_id | BIGINT UNSIGNED | FK → users, NULL ON DELETE — who uploaded it |
| original_name | VARCHAR(255) | the name the file arrived under |
| path | VARCHAR(255) | on the PRIVATE disk; never reachable by URL |
| size_bytes | INT UNSIGNED | |
| status | ENUM('pending','imported','undone') | DEFAULT 'pending' |
| rows_total / rows_valid / rows_invalid | INT UNSIGNED | the analysis |
| rows_imported / rows_over_limit | INT UNSIGNED | the outcome |
| errors | JSON | NULL — per-row rejections: line number and reason |
| imported_at / undone_at | TIMESTAMP | NULL |

`alumni.import_id` (FK → alumni_imports, **NULL ON DELETE**) records which
upload created each row. That is what lets an undo remove exactly the alumni one
file created and nothing else. NULL on delete rather than cascade: removing the
record of an import must never cascade into deleting people.

The uploaded file sits on the private disk because it is a list of real people's
names, addresses and telephone numbers.

### 5.9 `alumni`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| first_name | VARCHAR(80) | NOT NULL |
| last_name | VARCHAR(80) | NOT NULL |
| email | VARCHAR(150) | NOT NULL |
| phone | VARCHAR(30) | NULL |
| degree | VARCHAR(120) | NULL |
| graduation_year | SMALLINT UNSIGNED | NULL |
| company | VARCHAR(120) | NULL |
| job_title | VARCHAR(120) | NULL |
| city | VARCHAR(80) | NULL |
| password | VARCHAR(255) | NULL = no portal account (§4.4) |
| remember_token | VARCHAR(100) | NULL |
| must_change_password | BOOLEAN | DEFAULT 1 — the mailed password is provisional |
| credentials_sent_at | TIMESTAMP | NULL |
| password_changed_at | TIMESTAMP | NULL — set when the alumni chooses their own |
| last_login_at | TIMESTAMP | NULL |
| portal_access | BOOLEAN | DEFAULT 1 — revocable for one person |
| login_token | VARCHAR(64) | NULL — SHA-256 of the reset token, indexed |
| login_token_expires_at | TIMESTAMP | NULL |
| import_id | BIGINT UNSIGNED | NULL — FK → alumni_imports, the upload that created this row (§5.8c) |

UNIQUE (`organization_id`, `email`) · INDEX on `graduation_year`, `company`, `last_name`,
`login_token`.

The e-mail is the portal login, and it is unique PER ORGANIZATION rather than
globally, which is why the portal's anonymous screens are addressed per
organization (docs/project.md §4, rule 7).

### 5.10 `events`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| created_by | BIGINT UNSIGNED | NULL, FK → users, SET NULL |
| title | VARCHAR(150) | NOT NULL |
| description | TEXT | NULL |
| location | VARCHAR(180) | NULL |
| starts_at | DATETIME | NOT NULL |
| ends_at | DATETIME | NULL |
| capacity | INT UNSIGNED | NULL = unlimited |
| status | ENUM('draft','published','closed','canceled') | DEFAULT 'draft' |

### 5.11 `alumni_event` (registration + attendance)

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| alumni_id | BIGINT UNSIGNED | FK → alumni, CASCADE |
| event_id | BIGINT UNSIGNED | FK → events, CASCADE |
| status | ENUM('registered','canceled') | DEFAULT 'registered' |
| registered_at | TIMESTAMP | NOT NULL |
| attended | BOOLEAN | DEFAULT 0 |
| attended_at | TIMESTAMP | NULL |

UNIQUE (`alumni_id`, `event_id`).

### 5.12 `campaigns`

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| organization_id | BIGINT UNSIGNED | FK → organizations, CASCADE |
| created_by | BIGINT UNSIGNED | NULL, FK → users, SET NULL |
| subject | VARCHAR(180) | NOT NULL |
| content | LONGTEXT | NOT NULL |
| status | ENUM('draft','sending','sent','failed') | DEFAULT 'draft' |
| recipients_count | INT UNSIGNED | DEFAULT 0 |
| sent_at | TIMESTAMP | NULL |

### 5.13 `alumni_campaign` (recipients)

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| alumni_id | BIGINT UNSIGNED | FK → alumni, CASCADE |
| campaign_id | BIGINT UNSIGNED | FK → campaigns, CASCADE |
| status | ENUM('pending','sent','failed') | DEFAULT 'pending' |
| sent_at | TIMESTAMP | NULL |
| read_at | TIMESTAMP | NULL — when the alumni opened it in the portal (F10) |
| error_message | VARCHAR(255) | NULL |

UNIQUE (`alumni_id`, `campaign_id`).

---

## 6. SQL DDL

```sql
CREATE TABLE organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  logo_path VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_alumni INT UNSIGNED NULL,
  max_events INT UNSIGNED NULL,
  max_campaigns INT UNSIGNED NULL,
  max_users INT UNSIGNED NULL,
  paddle_price_monthly_id VARCHAR(50) NULL,
  paddle_price_yearly_id VARCHAR(50) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_custom TINYINT(1) NOT NULL DEFAULT 0,
  alumni_portal TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_plans_custom (is_custom)
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  billing_cycle ENUM('monthly','yearly') NOT NULL,
  status ENUM('pending','active','expired','canceled') NOT NULL DEFAULT 'pending',
  price DECIMAL(10,2) NOT NULL,
  starts_at DATE NOT NULL,
  ends_at DATE NOT NULL,
  canceled_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_sub_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
  INDEX idx_sub_org_status (organization_id, status)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscription_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  provider VARCHAR(50) NOT NULL,
  provider_reference VARCHAR(191) NULL UNIQUE,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_pay_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  module VARCHAR(50) NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_role_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  UNIQUE KEY uq_role_org_slug (organization_id, slug)
) ENGINE=InnoDB;

CREATE TABLE permission_role (
  permission_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id),
  CONSTRAINT fk_pr_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at TIMESTAMP NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_user_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  INDEX idx_user_org (organization_id)
) ENGINE=InnoDB;

CREATE TABLE alumni (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  degree VARCHAR(120) NULL,
  graduation_year SMALLINT UNSIGNED NULL,
  company VARCHAR(120) NULL,
  job_title VARCHAR(120) NULL,
  city VARCHAR(80) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_alumni_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  UNIQUE KEY uq_alumni_org_email (organization_id, email),
  INDEX idx_alumni_org_year (organization_id, graduation_year),
  INDEX idx_alumni_org_company (organization_id, company),
  INDEX idx_alumni_org_name (organization_id, last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location VARCHAR(180) NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  capacity INT UNSIGNED NULL,
  status ENUM('draft','published','closed','canceled') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_event_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_event_org_date (organization_id, starts_at)
) ENGINE=InnoDB;

CREATE TABLE alumni_event (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alumni_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  status ENUM('registered','canceled') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP NOT NULL,
  attended TINYINT(1) NOT NULL DEFAULT 0,
  attended_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_ae_alumni FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
  CONSTRAINT fk_ae_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  UNIQUE KEY uq_alumni_event (alumni_id, event_id)
) ENGINE=InnoDB;

CREATE TABLE campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  subject VARCHAR(180) NOT NULL,
  content LONGTEXT NOT NULL,
  status ENUM('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
  recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_camp_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_camp_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_camp_org_status (organization_id, status)
) ENGINE=InnoDB;

CREATE TABLE alumni_campaign (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alumni_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  sent_at TIMESTAMP NULL,
  error_message VARCHAR(255) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_ac_alumni FOREIGN KEY (alumni_id) REFERENCES alumni(id) ON DELETE CASCADE,
  CONSTRAINT fk_ac_camp FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  UNIQUE KEY uq_alumni_campaign (alumni_id, campaign_id)
) ENGINE=InnoDB;
```

Migration order: `organizations` → `plans` → `roles` → `permissions` → `permission_role` →
`users` → `subscriptions` → `payments` → `alumni` → `events` → `alumni_event` → `campaigns` →
`alumni_campaign`.

> `roles` is created before `users`, and `events`/`campaigns` reference `users` — so the
> `created_by` foreign keys are added in the same migration as their table (users already exists).

---

## 7. Eloquent relations

```php
Organization: hasMany(User), hasMany(Role), hasMany(Alumni), hasMany(Event),
              hasMany(Campaign), hasMany(Subscription), hasMany(Payment),
              hasOne(activeSubscription) // ofMany where status = active

Plan:         hasMany(Subscription)
Subscription: belongsTo(Organization), belongsTo(Plan), hasMany(Payment)
Payment:      belongsTo(Subscription), belongsTo(Organization)

Role:         belongsTo(Organization), hasMany(User), belongsToMany(Permission)
Permission:   belongsToMany(Role)
User:         belongsTo(Organization), belongsTo(Role)

Alumni:       belongsTo(Organization),
              belongsToMany(Event)->withPivot('status','registered_at','attended','attended_at'),
              belongsToMany(Campaign)->withPivot('status','sent_at','error_message')

Event:        belongsTo(Organization), belongsTo(User,'created_by'),
              belongsToMany(Alumni)->withPivot(...)

Campaign:     belongsTo(Organization), belongsTo(User,'created_by'),
              belongsToMany(Alumni)->withPivot(...)
```

Models using the `BelongsToOrganization` trait: `User`, `Role`, `Alumni`, `Event`, `Campaign`,
`Subscription`, `Payment`.

---

## 8. Dashboard queries (§3.7)

- **Number of alumni** — `alumni` count for the organization (excluding soft-deleted).
- **Participation rate** — `SUM(attended) / COUNT(*)` on `alumni_event` joined to the
  organization's events, restricted to `status = 'registered'`.
- **Recent activity** — last 5 rows of `alumni`, `events` and `campaigns` ordered by
  `created_at`, merged and sorted in PHP.

---

## 9. Seeders

1. **Permissions** — the list defined in `project.md` §5.4.
2. **System roles** — `super-admin` (all permissions), `org-admin` (all organization
   permissions), `staff` (view permissions only).
3. **Plans** — `Free` (limited) and `Pro` (higher limits), as named in §3.6.
4. **Super administrator** — one account with `organization_id = NULL`.
5. **Demo organization** — one organization, an active subscription, one org admin, a few alumni,
   events and campaigns for development.

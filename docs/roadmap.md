# Aeterna — Roadmap (`roadmap.md`)

Phasing follows §8 *Planning prévisionnel* of the cahier des charges:

| Phase | Content |
|---|---|
| **Phase 1** | Analysis and design (MCD, MLD) |
| **Phase 2** | Frontend development |
| **Phase 3** | Backend development |

Reading of this order: **Phase 2** builds every React/Inertia page against typed mock props, so
screens and navigation are validated before any business logic exists. **Phase 3** replaces the
mock props with real controllers, policies and queries — the page components are not rewritten.

---

## Phase 1 — Analysis & Design

### 1.1 Conceptual modeling
- [ ] MCD: 8 entities (Organization, User, Role, Alumni, Event, Campaign, Subscription, Plan)
- [ ] Cardinalities: Organization → Alumni (1,N), Alumni ↔ Event (N,N), Alumni ↔ Campaign (N,N)
- [ ] MLD: relational translation, association tables, foreign keys
- [ ] Validate the multi-tenant isolation strategy (`organization_id` on every tenant table)

### 1.2 Functional design
- [ ] Use case list per user type (Super Admin / Org Admin / Staff)
- [ ] Permission matrix (module × role)
- [ ] Navigation map and route list
- [ ] Screen inventory per module

### 1.3 Technical setup
- [ ] `laravel new aeterna` + Inertia.js + React + TypeScript + Vite
- [ ] MySQL connection, `.env`, base configuration
- [ ] Git repository, branch strategy, commit convention
- [ ] Migrations written for the 8 entities + pivots (schema from `database-schema.md`)
- [ ] Seeders: plans, permissions, system roles, super admin account, demo organization

**Deliverables:** MCD, MLD, permission matrix, runnable empty project with migrated database.

---

## Phase 2 — Frontend Development

All pages are built as Inertia pages receiving **typed mock props**. No API calls, no database.

### 2.1 Foundations
- [ ] TypeScript types for the 8 entities and pivots (`resources/js/types`)
- [ ] `AuthLayout`, `AppLayout` (organization), `AdminLayout` (super admin)
- [ ] Sidebar + topbar, responsive navigation, active route highlighting
- [ ] Shared components: DataTable (pagination, sort, search), Modal, ConfirmDialog, FormField,
      Select, DatePicker, Badge, Toast, EmptyState, Pagination
- [ ] Permission-aware rendering helper (`can()` from the shared `auth` prop)

> Visual identity (logo, colors, fonts, spacing) comes from `design.md`. Until it is delivered,
> build with neutral placeholder styling and centralize every token so it can be swapped in one file.

### 2.2 Authentication screens
- [ ] Login, forgot password, reset password
- [ ] Profile / change password

### 2.3 Organization space
- [ ] Dashboard: alumni count, participation rate, recent activity
- [ ] Alumni: index (search + filters + pagination), create, edit, show, delete confirmation
- [ ] Events: index, create, edit, show
- [ ] Event registrations: registration list, add/remove an alumni, attendance marking
- [ ] Campaigns: index, create (subject, content, recipient selection), show with delivery statuses
- [ ] Internal users: index, create, edit, deactivate
- [ ] Roles & permissions: index, create/edit role with permission checkboxes
- [ ] Organization settings, current subscription and plan usage

### 2.4 Super Administrator space
- [ ] Platform dashboard
- [ ] Organizations: index, create, edit, activate/deactivate
- [ ] Plans: index, create, edit (pricing + limits)
- [ ] Subscriptions: index, assign a subscription to an organization, payment history

### 2.5 Responsive pass
- [ ] Every page verified on mobile, tablet and desktop breakpoints

**Deliverable:** complete navigable interface with mock data.

---

## Phase 3 — Backend Development

Each step replaces the mock props of the matching Phase 2 screen.

### 3.1 Core & multi-tenancy
- [ ] Models + relations + `BelongsToOrganization` trait and global scope
- [ ] Middlewares: authenticated, has-organization, is-super-admin
- [ ] Scoped route model binding
- [ ] Shared Inertia props: authenticated user, organization, permissions, flash messages

### 3.2 Authentication & authorization
- [ ] Login / logout / password reset, throttling, session regeneration
- [ ] Permissions loaded from database into Gates
- [ ] Policies: Alumni, Event, Campaign, User, Role, Organization
- [ ] `FormRequest` authorization + validation for every write action

### 3.3 Organizations, plans, subscriptions, payments
- [ ] Organization CRUD (super admin) + creation of the first org admin
- [ ] Plan CRUD with limits (alumni, events, campaigns, users) and pricing
- [ ] Subscription assignment, status, start/end dates, expiry
- [ ] `PlanLimitService` blocking creations once a limit is reached
- [ ] Online payment integration + payment recording and history

### 3.4 Alumni
- [ ] CRUD, validation, per-organization unique email
- [ ] Search, filters, sorting, server-side pagination
- [ ] Soft delete

### 3.5 Events & participation
- [ ] Event CRUD
- [ ] Registration: attach/detach alumni, capacity check, duplicate check
- [ ] Attendance marking, attendance rate per event

### 3.6 Campaigns
- [ ] Campaign CRUD (draft)
- [ ] Recipient selection from filtered alumni
- [ ] Queued sending via Laravel Mail, per-recipient status update (pending/sent/failed)

### 3.7 Dashboard
- [ ] Organization statistics queries (alumni count, participation rate, recent activity)
- [ ] Super admin platform statistics

### 3.8 Hardening & delivery
- [ ] Isolation tests: a user of organization A can never read or write data of organization B
- [ ] Permission tests per role
- [ ] Plan limit tests
- [ ] Error pages (403, 404, 500), production build, deployment

**Deliverable:** fully functional platform matching the cahier des charges.

---

## Definition of Done (per module)

1. Validation server-side and client-side feedback
2. Policy/permission enforced
3. Data scoped to the organization
4. Responsive on mobile
5. Success and error flash messages
6. Empty state and pagination handled

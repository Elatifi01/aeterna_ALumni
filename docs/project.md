# Aeterna — Alumni CRM SaaS

## Project Specification (`project.md`)

> **Source of truth:** `Cahier des Charges – Plateforme Aeterna (Alumni CRM SaaS)`.
> **Scope rule:** this document contains **only** what is written in the cahier des charges.
> Anything not listed there is explicitly declared out of scope in §3.2 and must not be built.
> **Design/UI:** deferred to `design.md` (logo, colors, fonts, layouts) — not yet defined.

---

## 1. Context & Objective

Universities, schools and training centers must maintain a continuous relationship with their
alumni. Today this is handled with unsuitable tools (Excel, scattered emails), which makes
follow-up, communication and engagement analysis difficult.

Aeterna is a **multi-tenant web SaaS platform** that allows an organization to:

1. Manage its alumni
2. Organize events
3. Send email communication campaigns
4. Manage subscriptions and plans

**Solution type**

- Multi-tenant SaaS web application
- Browser-accessible
- One single platform serving several organizations
- Data isolated per organization
- Responsive

---

## 2. Technical Stack (imposed by §5.1)

| Layer    | Technology                                        |
| -------- | ------------------------------------------------- |
| Backend  | **Laravel**                                       |
| Frontend | **React via Inertia.js**                          |
| Database | **MySQL**                                         |
| Build    | Vite (Laravel default)                            |
| Auth     | Laravel session authentication (Inertia SPA flow) |
| Styling  | **To be defined in `design.md`**                  |

Recommended concrete versions: Laravel 12, Inertia.js 2, React 19 + TypeScript, MySQL 8.

---

## 3. Scope

### 3.1 In scope

| #   | Module                                                | Cahier des charges                          |
| --- | ----------------------------------------------------- | ------------------------------------------- |
| F1  | Organization management                               | §3.1                                        |
| F2  | User management (accounts, auth, roles & permissions) | §3.2                                        |
| F3  | Alumni management                                     | §3.3                                        |
| F4  | Event management                                      | §3.4                                        |
| F5  | Email campaigns                                       | §3.5                                        |
| F6  | Subscription & plan management                        | §3.6                                        |
| F7  | Dashboard / statistics                                | §3.7                                        |
| F8  | Online payment integration                            | §6                                          |
| F9  | Alumni portal                                         | §3.8 (added)                                |
| F10 | Notifications & message inbox                         | §3.9 (added)                                |
| F11 | Job board (emploi.ma)                                 | §3.10 (added)                               |
| F12 | Bulk import of alumni                                 | §3.11 (added)                               |
| F13 | Export of the alumni list                             | §3.12 (added)                               |
| F14 | Aeterna Assistant                                     | docs/AETERNA_AI_ASSISTANT_IMPLEMENTATION.md |

### 3.2 Out of scope (do **not** build)

Not mentioned in the cahier des charges, therefore forbidden:

- Mentoring, donations/fundraising, directory networking feed
- Mobile application, public REST API, webhooks
- SMS / push / WhatsApp notifications
- Bulk operations other than the alumni import of F12 and the export of F13
- Two-factor authentication, SSO, OAuth social login
- Email templates builder, A/B testing, open/click tracking analytics
- Activity/audit log table
- Advanced BI, custom report builder, data exports
- Multi-language interface (i18n)

---

## 4. Multi-tenancy Architecture (§2.1, §5.2)

**Strategy: single database, shared schema, row-level isolation via `organization_id`.**

Rules:

1. Every tenant-owned table carries a non-nullable `organization_id` foreign key.
2. Tenant models use a `BelongsToOrganization` trait that:
    - applies a **global Eloquent scope** filtering on the authenticated user's `organization_id`;
    - auto-fills `organization_id` on create.
3. The tenant is resolved **from the authenticated actor only** (never from the URL, never from a
   request parameter). TWO guards can be that actor: `web` for the staff of an organization and
   `alumni` for the portal (F9). `App\Support\Tenant` asks each in turn with `hasUser()` — never
   `user()`, which would recurse through this very scope while resolving.
4. The **Super Administrator** has `organization_id = NULL` and bypasses the global scope; his
   routes live under a separate `/admin` route group protected by a dedicated middleware.
5. Any request referencing a related record by id (e.g. `event_id`, `alumni_id`) must validate
   that the record belongs to the current organization (`Rule::exists()` + `where organization_id`).
6. Route model binding is scoped: a 404 is returned for a record of another organization.
7. **One exception, and only for anonymous screens.** The portal's login, forgot-password and
   reset pages live at `/portail/{organization:slug}/…`. `alumni.email` is unique per
   organization, not globally — the same person may be an alumni of two establishments
   (docs/database-schema.md §5.9) — so an address alone is not a login identity and the slug is
   what says _which credentials to check_. It grants nothing: no session exists yet, the slug is
   never trusted after sign-in, and every authenticated portal route carries no identifier at
   all. Rule 3 governs from the moment the alumni is signed in.

```
app/
├── Models/Concerns/BelongsToOrganization.php   // global scope + auto-fill
├── Scopes/OrganizationScope.php
├── Support/Tenant.php                          // which organization, from which guard
└── Http/Middleware/
    ├── EnsureUserIsSuperAdmin.php
    ├── EnsureUserHasOrganization.php
    ├── EnsureAlumniCanUsePortal.php             // portal door: access, organization, plan
    └── EnsureAlumniPasswordChanged.php          // the forced first change
```

---

## 5. User Types & Permissions (§2.2, §3.2)

### 5.1 Super Administrator

- Global access to the platform
- Manages organizations (create, edit, activate/deactivate)
- Manages subscriptions and plans
- Full system supervision (platform-level statistics)
- Not attached to any organization (`organization_id = NULL`)

### 5.2 Organization Administrator

- Manages alumni
- Manages events
- Manages campaigns
- Manages internal users (create accounts, assign roles & permissions)
- Restricted to his own organization

### 5.3 Staff

- Limited access **according to the permissions granted to him**
- Restricted to his own organization

### 5.4 Permission model

A role belongs either to the platform (`organization_id = NULL`) or to an organization.
The platform rows are the Super Administrator's own role plus three templates; every
organization is provisioned with its own copies of those templates and may re-permission
them freely, which changes nothing for any other organization. Permissions are atomic and
grouped by module:

| Module       | Permissions                                                                                                                          |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| alumni       | `alumni.view`, `alumni.create`, `alumni.update`, `alumni.delete`, `alumni.invite`, `alumni.portal`, `alumni.import`, `alumni.export` |
| events       | `events.view`, `events.create`, `events.update`, `events.delete`, `events.registrations`                                             |
| campaigns    | `campaigns.view`, `campaigns.create`, `campaigns.send`                                                                               |
| users        | `users.view`, `users.create`, `users.update`, `users.delete`                                                                         |
| roles        | `roles.view`, `roles.manage`                                                                                                         |
| dashboard    | `dashboard.view`                                                                                                                     |
| organization | `organization.update`                                                                                                                |
| subscription | `subscription.view`                                                                                                                  |
| assistant    | `assistant.use`                                                                                                                      |

Enforcement: Laravel Gates/Policies registered from the `permissions` table, checked in
`FormRequest::authorize()` and in policies; the permission list is shared to React through the
Inertia `auth` prop so the UI can hide unauthorized actions.

**The alumni role (added 2026-09-17).** Roles carry a `guard`: `web` for the staff roles above,
`alumni` for exactly ONE role per organization, slug `alumni`, that every one of its alumni
holds — there is no `role_id` on the alumni table. It is listed in Roles beside Administrateur
and Personnel, editable by whoever holds `roles.manage`, never deletable and never assignable
to a member of staff. Its catalogue is separate, and every slug in it starts with `portal.`:

| Module | Permissions                                                                                                                                                      |
| ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| portal | `portal.events.view`, `portal.events.register`, `portal.messages.view`, `portal.notifications.view`, `portal.jobs.view`, `portal.jobs.apply`, `portal.assistant.use`, `portal.profile.update` |

What is ticked is what the portal offers; what is unticked disappears for every alumni of the
establishment at once — from the sidebar, and from the routes behind it, which answer 403
(`portal-can:` middleware). The dashboard, the password screens and signing out are never
behind the role: an alumni who could be locked out of those has no account worth the name.
Revoking one person entirely remains `alumni.portal_access`.

`Alumni::hasPermission()` refuses any slug that does not start with `portal.` before looking
anything up, and the role forms validate against their own guard's catalogue, so the two sets
cannot bleed into each other. An organization that has no alumni role at all — never
configured — gets the defaults, which are the whole portal; a role that exists is authoritative.
Every organization is seeded with the full set.

---

## 6. Functional Requirements

### F1 — Organization management (§3.1)

- Create an organization (name, contact information, status)
- Edit organization information
- Assign a subscription (plan + billing cycle + period)
- Actor: Super Administrator
- Creating an organization also creates its first Organization Administrator account.

### F2 — User management (§3.2)

- Create user accounts inside an organization
- Secure authentication: hashed passwords (bcrypt), login throttling, CSRF, session
  regeneration, password reset by email
- Assign roles and permissions to users
- Actors: Super Administrator (platform users), Organization Administrator (internal users)

### F3 — Alumni management (§3.3)

- Add, edit, delete an alumni
- Stored information: last name, first name, email, phone, degree, graduation year, company,
  job title, city
- Advanced search and filtering: full-text search on name/email/company + filters on graduation
  year, degree, company; server-side pagination and sorting
- Email unique per organization

### F4 — Event management (§3.4)

- Create events (title, description, start/end date, location, capacity, status)
- Manage registrations: register/unregister an alumni for an event, capacity check
- Attendance follow-up: mark an alumni as present/absent, attendance rate per event

### F5 — Email campaigns (§3.5)

- Create an email campaign (subject, content)
- Select recipients among the organization's alumni (using the same filters as F3)
- Send the campaign to selected alumni; per-recipient delivery status (pending / sent / failed)
- Sending is queued (Laravel queue + Mail)

**Deleting and re-sending, added 2026-09-15.**

A campaign can be deleted at any status, a sent one included. Deletion here is a
soft delete: the campaign and its per-recipient rows go to the corbeille intact
and come back on restore, so removing a sent campaign tidies a list rather than
destroying a delivery record. Deleting one mid-send also stops the mail that has
not gone out, because `SendCampaignRecipient` keeps `SoftDeletingScope` and
skips a campaign it finds deleted. **Editing stays draft-only**: rewriting the
subject of a campaign four hundred people have received would make the record
disagree with what was actually sent.

A campaign that has finished — `sent` or `failed` — can be sent again, behind
`campaigns.send` like any other send. Two scopes, because two different requests
wear the same word: `failed` retries only the addresses that did not go through,
which is the everyday one and disturbs nobody who received the message; `all`
mails every recipient a second time, which is a real thing to want and a real
thing to do by accident, so the interface asks first and names how many people
already received it. Both only put rows back to `pending` and re-queue them —
the job closes the campaign when none are left, exactly as on a first send, so
there is no second definition of "finished". Re-sent rows lose their `read_at`:
receiving a message again means having an unread message again, and the portal
inbox should say so.

### F6 — Subscriptions & plans (§3.6, §6)

- Define plans (Free, Pro, …) with monthly and annual pricing
- Feature limitation per plan: maximum number of alumni, events, campaigns and users
- Active subscription tracking: status, start date, end date, expiry
- Limits enforced server-side before any create action; a clear error is returned when the plan
  limit is reached
- **Bespoke subscriptions**: for an organization that fits no catalogue plan, the Super
  Administrator chooses the limits directly (how many alumni, events, campaigns and users) and the
  price is computed from the rates in `config/pricing.php` — a base fee plus a price per block of
  each resource, or a flat fee for unlimited. A part block is charged as a whole one. The computed
  figure may be overridden with a negotiated price. This creates a plan flagged `is_custom` that
  stays off the catalogue and cannot be assigned to any other organization
  (docs/database-schema.md §4.3)
- Online payment integration for subscription purchase/renewal, with payment history

### F9 — Alumni portal (§3.8, added 2026-09-07)

Added on the client's instruction, outside the original cahier des charges. It
opens a space to the alumni themselves, sold as a capability of the paid plans:
`plans.alumni_portal`. An organization on Free has no alumni accounts at all.

**Getting an account.** Creating an alumni creates their account in the same
request. A welcome e-mail carries their login (their address) and a password
generated for them alone — never one shared default, which would open every
account at once if it leaked. There is no invitation to accept.

**The first sign-in.** The mailed password is provisional: it lives in that
mailbox for good. Until it has been replaced, every portal route leads back to
"Choisir votre mot de passe", and the new one may not be the mailed one. An
unchanged provisional password stops working after
`config('portal.initial_password_expires_days')` (30 by default, `null` to
disable), after which "mot de passe oublie" is the way back in.

**What an alumni can do.** See the published events of their establishment,
register for one and cancel — under the same capacity rules an administrator
obeys, since both go through EventRegistrationService — and change their own
password.

**Managing it from the back-office.** `alumni.invite` gates "Envoyer les
identifiants" (which also re-sends, replacing the password) and "Retirer
l acces" (which clears the password outright). Both are hidden when the plan
has no portal.

**Their own profile.** Portal settings hold two screens, Profil and Securite,
reached from the alumni's own name and avatar at the foot of the sidebar: those
open a menu upward with Profil, Securite and Se deconnecter. There is no
separate Parametres entry in the navigation, because these settings are the
alumni's own rather than another module of the portal. On the profile an alumni keeps their **telephone, entreprise, poste and
ville** current — the things that change over time, which is the whole value of
letting them do it, and which keeps the establishment's directory accurate
without anybody retyping it.

Their **nom, e-mail, diplome and promotion are shown but locked**: the first two
are the establishment's record of who somebody is (and the e-mail is also their
login), the last two its record of what they studied. They are displayed rather
than hidden, with a line saying who to ask, so an alumni is not left wondering
where their name went. The four editable fields are the only ones in
`UpdateProfileRequest::rules()`, so a locked field posted by a tampered form
never reaches the model — there is a test that tries exactly that.

**Signing in.** The anonymous screens live at `/portail/{organization}/…`
because `alumni.email` is unique per organization, not globally (§4) — an
address alone cannot identify an account. Everything after sign-in carries no
identifier at all.

### F10 — Notifications & message inbox (§3.9, added 2026-09-07)

Added alongside F9. **In-app only: this slice sends no new e-mail.** Campaigns
keep going out exactly as F5 describes; everything else appears in the portal
and nowhere else, because an organization composing a campaign has chosen to
write to its alumni and publishing an event has not.

**The inbox needs no new table.** A message IS a row of `alumni_campaign` with
`status = 'sent'` — the row SendCampaignRecipient already writes to record
delivery — plus a `read_at` column. No second copy of the content that could
drift from the campaign itself. Only sent rows are listed: pending means the
queue has not reached it, failed means it never arrived, and neither belongs in
an inbox.

**Three notifications**, all on the database channel:

- `CampaignDelivered` — raised by SendCampaignRecipient _after_ the mail
  actually goes, so the inbox never shows a message that failed to send.
- `EventPublished` — on the crossing from any other status into `published`,
  never on a later edit of an already-published event, and never for an event
  that has already happened.
- `RegistrationConfirmed` — whoever registered the alumni, themselves or an
  administrator; the second case is the one where they would otherwise never
  know.

All three are raised only for alumni who hold a portal account: a notification
with no inbox to appear in is nothing at all. `AlumniNotifier` is the single
place that rule lives.

Isolation rests on the notifiable rather than on a column: `notifications`
carries no `organization_id`, every row hangs off an alumni, and an alumni
belongs to exactly one organization.

### F11 — Job board (§3.10, added 2026-09-07, moved to emploi.ma 2026-09-10, multi-source 2026-09-17)

Alumni search Moroccan job offers from inside the portal. Part of the alumni
portal and gated by the same `plans.alumni_portal`; it is not separately priced.

**Several sites, one search (2026-09-17).** A search asks every enabled
*job source* and merges the answers, taking offers from each site in turn so no
board pushes the others off the first screen. Each offer carries its `source`
and is badged with it; there is nothing to choose — one query, every site — and
a site that fails answers with its own notice while the others still return
results.
A saved offer or application snapshots the site name in `job_applications.source`.

`JobSource` is the contract (`key`, `label`, `isConfigured`, `search`);
`JobSearch` is the aggregator the controller and the assistant's `search_jobs`
tool both use. `EmploiMaClient` is one source; every other Parse.bot scraper is
a `ParseBotSource` described entirely in `config/services.php` under
`jobs.sources.<key>` — base URL, endpoint, the scraper's parameter names, the
key the offers sit under, and a field map onto the fields `JobOffer` reads. So
adding a site is configuration once the scraper's response has been seen, not
code. All sources share the Parse.bot key (`PARSEBOT_API_KEY`) and ONE credit
budget and cache TTL (`services.parsebot.*`): the account is what is metered,
not the scraper.

**LinkedIn** is the second source (2026-09-17), through Parse.bot scraper
`63156746-…`, endpoint `search_jobs`, parameters `keywords` and `location`.
Its default location is the United States, so the source always sends one —
the alumni's city when given, `LINKEDIN_DEFAULT_LOCATION` (Morocco) otherwise.
Because the city goes upstream here, a `ParseBotSource` keys its cache on the
parameters it actually sends, not on the city-less `JobQuery::fingerprint()`
emploi.ma uses. Ten offers per call, no paging. Indeed was considered and
dropped; the mechanism does not care.

**The source is emploi.ma**, reached through Parse.bot's REST wrapper. It
replaced Jooble, whose free key returned US listings only — the wrong market for
this product entirely.

**The constraint that shapes the client.** Every endpoint costs ONE CREDIT and
the free tier allows 200 a month, renewing, at five requests a minute.
`EmploiMaClient` therefore, in order: normalises the query into the cache key,
serves from cache (a day), spends only on a miss and only if the monthly budget
allows, and **degrades rather than fails** — out of budget, rate-limited, no key
or emploi.ma erroring all return the last good page for that search, or an empty
result the screen explains calmly. An alumni never sees a 500.

**Two numbers, from two places.** Parse.bot returns `x-credits-remaining` and
`x-credits-limit` on every response, so what is left is a fact we are told
rather than one we infer — that is what the platform dashboard shows. Our own
monthly counter is a separate governor set _below_ the real allowance, because
the upstream figure only arrives after a call has already been charged; the
margin is what stops us discovering the limit by hitting it.

**The search has no location parameter.** emploi.ma filters on keywords and job
category (`metier_id`) only. So the city an alumni types narrows the rows that
came back, in our own code, and is deliberately **not** part of the cache
fingerprint: two cities within one category share one cached response and cost
one credit between them. The screen reports both figures — "17 offres a
Casablanca, sur 25 trouvees" — and when a city filters everything away it says
so, rather than letting "aucune offre" blame the search.

The **Metier** dropdown comes from `/list_job_categories`, cached for a week
because emploi.ma's list of trades changes about never; at that TTL it costs
roughly four credits a month however many alumni browse.

**Offers are never stored**, only cached. What IS stored is what an alumni did
about one — saved or applied — with a **snapshot** of the offer, because a
listing expires off emploi.ma while their record of applying must not. Offers
carry no id of their own, so the identifier is the site's numeric id taken from
the end of the URL, with a hash of the URL as the fallback.

There is **no apply endpoint**: "Postuler" records the intent and hands over the
link. The outcome happens on emploi.ma, out of our sight, and the interface says
so rather than implying otherwise.

### F12 — Bulk import of alumni (§3.11, added 2026-09-09)

An organization uploads an Excel or CSV file and the alumni in it are created.
Its own permission, `alumni.import`: one click can add hundreds of people, which
is a different right to hand out than the right to add one.

**The model file is the format.** `AlumniImportTemplate::COLUMNS` is the single
definition — the downloadable model is generated from it and the parser reads
back through it — so a file produced by the download can never fail the header
check. Headings are matched loosely (case, accents and spacing ignored) because
a heading retyped by hand should still be recognised; extra columns are ignored
rather than refused. **The first line is the heading and is never data.**

**Uploading is not importing.** The file is analysed and the screen reports what
would happen — rows read, valid, ignored, beyond the plan — and importing is a
second, deliberate click.

**Two rules decide which lines survive:**

- _Invalid rows are skipped, not fatal._ Real spreadsheets have a bad address on
  line 3, and refusing 400 good rows over it helps nobody. Each rejection is
  listed with its line number as it appears in Excel and the reason.
- _Rows beyond the plan are refused, and the ones that fit still import._ The
  heading never counts against the allowance. What is left out is reported, so
  the same file can be re-uploaded after an upgrade for the remainder.

An address is rejected when it is malformed, repeated inside the file, or
already used in the organization — **including by an alumni in the corbeille**,
since the unique index covers soft-deleted rows and the insert would fail where
the check did not. The same address in a _different_ organization is fine (§4).

**Both destructive actions ask for the signed-in user's password.** Discarding
the file destroys a list of real people's names, addresses and telephone
numbers; undoing an import removes alumni. The password is typed for that
action, not confirmed session-wide minutes earlier. An undo soft-deletes to the
corbeille (§8), so a mistaken undo is itself recoverable, and it removes only
the rows that import created — `alumni.import_id` is what makes that exact.

**Importing never creates portal accounts on its own.** An import of four
hundred people would otherwise send four hundred credential e-mails the moment
somebody clicked Importer, with no chance to say no. Instead the screen asks
afterwards — "Creer les comptes du portail ?" — and the same offer stays on the
import in the history, so somebody who chose _plus tard_ can still say yes.

Saying yes queues **one job per alumni**, like campaign sending and for the same
reason: four hundred SMTP round trips do not fit in a request. Creating a single
alumni still sends inline, because that is one deliberate act with a person
waiting for the answer. An alumni who already has an account is skipped, so
running it twice never resets a password already sitting in somebody's inbox.
The offer only appears when the plan includes the portal, and sending the mail
is `alumni.invite` rather than `alumni.import` — reaching a real person's
mailbox is a different right from adding rows.

Uploaded files live on the **private** disk. They are lists of personal data and
must never be reachable by URL.

### F13 — Export of the alumni list (§3.12, added 2026-09-10)

An organization downloads its alumni as a spreadsheet. Its own permission,
`alumni.export`: reading a screen and walking out with every alumni's name,
address and telephone number in one file are different acts, and an
organization may grant one without the other.

**It exports what the screen is showing.** The list filters — search, promotion,
diplome, entreprise — narrow the file, so "Exporter" means "exporter ce que je
vois". `Alumni::scopeFiltered` is the single definition both use, because that
promise only holds while one definition serves both.

**An exported file re-imports.** The first columns are exactly
`AlumniImportTemplate::COLUMNS`, in its order, so an organization can export,
correct in Excel and bring the corrections back. Three portal columns follow —
whether they hold an account, whether access is active, when they last signed
in — and the reader ignores columns it does not recognise, so they inform
without breaking the round trip. There is a test that does the whole loop.

Values are written as text, not numbers: a telephone like `0612345678` read back
as `612345678` is a wrong telephone. Rows are pulled in batches of 500 so a
large list is not loaded as models all at once. Alumni in the corbeille are not
exported, and neither, as everywhere, is another organization's.

**laravel-excel was considered and not adopted.** It is a Laravel wrapper around
`phpoffice/phpspreadsheet`, which this project already uses directly for the
import and the template; adopting it would have meant a second spreadsheet layer
doing the same job, and rewriting import code that passes its tests. None of the
logic that matters — plan limits, per-row errors with line numbers, duplicate
detection — comes from it in any case.

### F14 — Aeterna Assistant (added 2026-09-10)

An assistant that answers from **real Aeterna data**, specified in
`docs/AETERNA_AI_ASSISTANT_IMPLEMENTATION.md`. It runs on a **local model**
through Ollama, so development and the demo need no paid API and nothing leaves
the machine.

**The rule the whole feature rests on: the model proposes, Laravel disposes.**
A tool is offered only when `Tool::authorize()` passes for the actor resolved
from the SESSION, and asked again before it runs — so a tool the model was
never shown cannot be reached by naming it, and a refusal is worded the same
whether the tool is unknown or merely forbidden, so it cannot reveal that a
staff tool exists. This is also why prompt injection cannot escalate: the
check never asks the model who is calling.

**Two actors, one engine.** `Actor` resolves either an alumni on the portal
guard or staff on the web guard, and `can()` is the only question a tool asks
about authority — always false for an alumni, whose access comes from being
that alumni rather than from a permission.

**The portal's four tools** each reuse the service the equivalent screen uses:
`search_jobs` goes through `EmploiMaClient`, so a search the board already made
costs no credit; `get_upcoming_events` returns published events only;
`get_my_profile` lists its fields one by one, because handing over the model
would send the password hash and the login token to a language model;
`get_my_applications` filters on `alumni_id` as well as the tenant.

**Three caps, all safety rails rather than tuning.** Tool iterations, so a model
that keeps asking for tools cannot loop for ever; job searches per conversation,
so a chatty session cannot eat a month of emploi.ma credits; and history length,
so a long conversation cannot outgrow a small model's context.

Conversations belong to a **polymorphic owner** — the shape `notifications`
already uses — and are reached through that owner's relation, never by id
alone. Each answer records which tools built it, and the interface shows them,
so a grounded answer is visibly grounded rather than asserted.

**The staff's three tools** are gated by the permission slugs that already
exist, never by new ones and never by which controller was reached:
`search_alumni` needs `alumni.view` and reuses `Alumni::scopeFiltered()`, the
single definition of a filter that the list and the export already share;
`get_alumni_statistics` needs the same and answers with **real SQL aggregates**,
because a language model counting rows in a list it was shown is how you get a
confident wrong number; `draft_campaign` needs `campaigns.create`.

`draft_campaign` is the only tool that writes, and it writes the least dangerous
thing there is: a campaign with `status = 'draft'` that nobody has seen. Sending
stays behind `campaigns.send` and a person clicking the button. It also runs
`PlanLimitService::ensureCanCreate()` and returns the refusal as the tool result
— it would be a poor sort of limit that the assistant could walk around.

A campaign is two decisions — what it says and who receives it — and the second
is the one with consequences, so **the tool refuses to draft anything until an
audience has been named**. Called without `recipients` it creates no row and
answers with the question to put to the user, together with the audiences that
actually exist and how many alumni each covers, so the choice is between real
options rather than a guess. `mode: filtered` with nothing to filter on counts
as no audience for the same reason: mailing everybody by accident is the failure
worth preventing. The system prompt asks the model to put the question first,
but the guarantee does not rest on it doing so.

Recipients themselves are resolved by `CampaignRecipientResolver`, the same
service the campaign screen uses, so "tous les alumni" means exactly what it
means there — this establishment's, through `OrganizationScope` — and an
audience matching nobody gets the same refusal the screen gives. The rows land
as `pending`, which is what the draft screen expects; nothing is queued.

**Modifying, rather than writing a second one.** With only `draft_campaign` to
hand, "modifie la campagne et ajoute des destinataires" produced a new campaign
— the one thing that was not asked for. `update_campaign_draft` changes the
subject, the content or the audience of a campaign that already exists, and
`list_campaigns` (`campaigns.view`) lets one be found by name, since the id
otherwise only exists in the conversation that created it.

Editing is the same capability as authoring, so the tool carries
`campaigns.create`, exactly as the edit screen does, and **`CampaignPolicy`'s
draft-only rule holds unchanged**: a campaign that has left draft can no longer
be changed by anyone, because its recipient rows are a delivery record. That
refusal comes back as a tool result so the assistant can explain it. An id
belonging to another establishment is simply not found, which is the same answer
a made-up one gets.

`mode: add` exists because "ajoute la promotion 2023" and "envoie-la a la
promotion 2023" are different instructions; replacing an audience when somebody
meant to widen it silently drops people from a campaign.

**Two questions, not one.** `assistant.use` decides who may open Aeterna Assistant at
all and is granted by role like any other permission, so an establishment
chooses who gets it; the tools decide what it may read for them. A user with
`assistant.use` and nothing else opens the page and simply has no alumni tools,
and the suggested questions are filtered the same way, so nothing is proposed
that would be refused. Suggestions stay reachable once a conversation has
started, not only on the empty state.

**On the alumni side the establishment decides, not the alumni.** Whether the
portal offers Aeterna Assistant is `portal.assistant.use` on the organization's
alumni role (§5.4), edited from the Roles screen like the rest of the portal.
It replaced a dedicated `organizations.portal_assistant` switch on 2026-09-17 —
two controls for one thing was one too many — and the migration carried each
organization's setting over. Unticking it closes the door, not merely the
sidebar entry, and the assistant's own tools follow the same role: an alumni
whose establishment offers no job board is offered no `search_jobs`.

**Which guard answers is the caller's to say, never a guess.** Both guards keep
their own key in ONE session, so signing into the back office and the portal
from the same browser resolves both at once. `Actor` originally asked the alumni
guard first and fell back to the web guard, and the staff assistant then
answered as the alumni — listing their conversations and offering no staff tool.
`Actor::fromPortal()` and `Actor::fromStaff()` are named per guard for that
reason, and each controller says which space it is. `Tenant` had the same flaw
in reverse and now orders its guards by the space of the request, because for
someone who is staff of one establishment and an alumni of another, a fixed
order runs portal queries against the wrong tenant. `HandleInertiaRequests`
shares only the guard belonging to the space, so a back-office page cannot end
up wearing another establishment's name and logo.

Ollama being unreachable is reported in the transcript in plain French; a model
that is not pulled says which one to pull. Neither shows a stack trace.

### F7 — Dashboard (§3.7)

Global statistics for the current organization:

- Number of alumni
- Participation rate (attendees / registrations across events)
- Recent activity — **derived** from the most recent alumni, events and campaigns records
  (no dedicated activity table is created)

Super Administrator dashboard: number of organizations, active subscriptions, distribution per plan.

---

## 7. Business Model (§6)

- Monthly or annual subscription
- Different plans with limitations
- Online payment system integration

---

## 8. Security (§5.2)

- Secure authentication (hashed passwords, session-based, throttled login)
- Data protection: validation on every input, mass-assignment protection, XSS-safe rendering
  through React, CSRF tokens on all mutating requests, authorization policy on every resource
- Data isolation via `organization_id` (see §4)
- Deletions are soft deletes on tenant data to prevent accidental loss

---

## 9. Project Structure

```
aeterna/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Super Administrator (organizations, plans, subscriptions)
│   │   │   ├── Auth/
│   │   │   ├── AlumniController.php
│   │   │   ├── EventController.php
│   │   │   ├── EventRegistrationController.php
│   │   │   ├── CampaignController.php
│   │   │   ├── UserController.php
│   │   │   ├── RoleController.php
│   │   │   └── DashboardController.php
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Policies/
│   ├── Services/               # PlanLimitService, CampaignSender, PaymentService
│   └── Scopes/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/js/
│   ├── Components/
│   ├── Layouts/                # AppLayout, AdminLayout, AuthLayout
│   ├── Pages/
│   │   ├── Admin/{Organizations,Plans,Subscriptions}/
│   │   ├── Auth/
│   │   ├── Alumni/{Index,Create,Edit,Show}.tsx
│   │   ├── Events/
│   │   ├── Campaigns/
│   │   ├── Users/
│   │   ├── Roles/
│   │   └── Dashboard.tsx
│   └── types/
└── routes/web.php
```

---

## 10. Conventions

- Database identifiers: English, `snake_case`, plural table names.
- Code (models, controllers, props): English.
- User interface: **French** (the cahier des charges and the end users are French-speaking).
- All list pages: server-side pagination, search and filters passed as query string, preserved
  on navigation via Inertia `only` partial reloads.
- Every write action returns a redirect with a flash message rendered by a shared toast component.

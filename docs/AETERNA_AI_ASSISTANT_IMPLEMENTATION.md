# Aeterna AI Assistant — Implementation Plan

## Objective

Add an **Aeterna AI Assistant** to the existing Aeterna Alumni CRM SaaS.

The assistant must be an AI assistant **for Aeterna**, not a generic chatbot. It should answer questions using real Aeterna data while respecting authentication, permissions, and multi-tenant organization boundaries.

Examples:

- "What jobs are available for data analysts in Casablanca?"
- "Which alumni events are happening this month?"
- "How can I update my profile?"
- "What skills should I learn to become a cloud engineer?"
- "Show me jobs matching my profile."
- "Find alumni who know Laravel." (authorized admins)
- "How many alumni work in IT?" (authorized admins)
- "create me a campagne (authorized admin)"

## Important constraints

### 1. Free/local-first AI

For development and the school presentation, use **Ollama with a local open model**.

Do NOT require a paid AI API.

The architecture must make the model provider replaceable later:

```text
AeternaAIService
      |
      +-- Ollama provider (development/demo)
      |
      +-- future cloud provider (optional)
```

Keep AI provider-specific code isolated in one place.

### 2. Laravel remains the security authority

The AI must NEVER have unrestricted database access.

The model can request tools/functions, but Laravel executes the tools.

Flow:

```text
User
  |
  v
Aeterna AI UI
  |
  v
Laravel
  |
  +-- authenticate user
  +-- check permissions
  +-- identify current organization/tenant
  +-- execute allowed AI tool
  |
  v
Database / external jobs API
  |
  v
Tool result
  |
  v
AI
  |
  v
User
```

Never allow the model to decide whether a user is authorized.

Every database query must be scoped to the current tenant where applicable.

---

# Core AI capabilities

Implement these capabilities progressively.

## 1. Job search

Tool:

```text
searchJobs(keyword, city, category, employment_type)
```

Examples:

- Laravel jobs in Casablanca
- Software engineering jobs in Rabat
- Data analyst jobs in Marrakech

The tool should query Aeterna's jobs database.

If an external Moroccan jobs source is already integrated, keep it behind the same job-search service rather than exposing external API details to the AI.

Return structured job data:

```text
id
title
company
city
category
employment_type
description/summary
skills
application_url
posted_at
```

The AI should summarize results and provide useful links/actions.

## 2. Upcoming events

Tool:

```text
getUpcomingEvents()
```

The assistant should answer questions such as:

- "What events are happening this month?"
- "Are there any networking events?"
- "What is the next alumni event?"

Only return events visible to the authenticated user and current organization.

## 3. Current user's profile

Tool:

```text
getMyProfile()
```

Return only data that the current authenticated user is allowed to use.

This enables:

- "What jobs match my profile?"
- "What skills am I missing?"
- "How can I improve my profile?"

Do not expose private fields unnecessarily.

## 4. Alumni search

Admin/authorized-user tool:

```text
searchAlumni(query, skills, city, industry)
```

Examples:

- "Find alumni who know Laravel."
- "Show alumni working in Casablanca."
- "Find alumni working in cloud computing."

Must enforce existing Aeterna permissions.

If the current user does not have permission to view alumni, the tool must refuse.

## 5. Alumni statistics

Admin/authorized-user tool:

```text
getAlumniStatistics(filters)
```

Examples:

- "How many alumni work in IT?"
- "Which cities have the most alumni?"
- "What are the most common skills?"

Statistics must come from real database queries.

## 6. Career advice

This capability can initially use the user's profile and general model knowledge.

Examples:

- "What skills should I learn to become a cloud engineer?"
- "What career paths fit my profile?"

Avoid presenting AI-generated career advice as guaranteed facts.

---

# Suggested architecture

Create a dedicated AI layer.

Possible structure:

```text
app/
├── Services/
│   └── AI/
│       ├── AeternaAIService.php
│       ├── AIProviderInterface.php
│       ├── OllamaProvider.php
│       └── Tools/
│           ├── SearchJobsTool.php
│           ├── GetUpcomingEventsTool.php
│           ├── GetMyProfileTool.php
│           ├── SearchAlumniTool.php
│           └── GetAlumniStatisticsTool.php
│
├── Http/
│   └── Controllers/
│       └── AIAssistantController.php
│
└── Models/
    ├── AIConversation.php
    └── AIMessage.php
```

Adapt this structure to the existing project conventions instead of blindly creating duplicate services.

---

# AI provider abstraction

Create an interface similar to:

```php
interface AIProviderInterface
{
    public function chat(
        array $messages,
        array $tools = []
    ): array;
}
```

Then:

```php
class OllamaProvider implements AIProviderInterface
{
    // HTTP communication with local Ollama server
}
```

Configuration should come from `.env`:

```env
AI_PROVIDER=ollama

OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=<selected-local-model>
```

Do not hard-code API keys or secrets.

If an HTTP client already exists in the project, reuse it. Otherwise use Laravel's HTTP client rather than adding Guzzle solely for this feature.

---

# Tool-calling design

The model should receive a list of available tools.

Example conceptual tool:

```text
search_jobs
Description:
Search jobs available to the current user.

Arguments:
keyword: string|null
city: string|null
category: string|null
employment_type: string|null
```

When the AI requests:

```json
{
    "tool": "search_jobs",
    "arguments": {
        "keyword": "Laravel",
        "city": "Casablanca"
    }
}
```

Laravel executes the tool:

```text
AI request
    ↓
Validate arguments
    ↓
Authorize user
    ↓
Apply tenant scope
    ↓
Query database
    ↓
Return structured result
```

Then send the tool result back to the model so it can formulate the final response.

Do not let the model generate SQL.

---

# Conversation persistence

Add persistent conversations.

Suggested tables:

## ai_conversations

```text
id
user_id
organization_id / tenant_id
title
created_at
updated_at
```

## ai_messages

```text
id
conversation_id
role
content
tool_name nullable
tool_call_id nullable
metadata nullable
created_at
```

Use the project's existing organization/tenant relationship and naming conventions.

Every conversation must belong to its owner and organization.

A user must never be able to retrieve another user's conversation by changing an ID.

Use authorization policies where appropriate.

---

# API endpoints

Follow the existing Aeterna API/routing conventions.

Possible endpoints:

```text
POST   /ai/chat
GET    /ai/conversations
GET    /ai/conversations/{conversation}
DELETE /ai/conversations/{conversation}
```

For the initial version, normal request/response is acceptable.

Streaming can be added later if the existing frontend architecture makes it worthwhile.

---

# Frontend

Add an Aeterna AI page or assistant interface using the existing UI system.

Do not create an unrelated visual style.

Suggested interface:

```text
Aeterna AI

Your alumni & career assistant

Suggested questions:

[ Find jobs for me ]
[ Upcoming alumni events ]
[ Improve my profile ]
[ What skills should I learn? ]

--------------------------------

User message

--------------------------------

Aeterna AI response

--------------------------------

Ask Aeterna anything...             [Send]
```

Use existing:

- layout
- typography
- buttons
- inputs
- cards
- icons
- Tailwind conventions
- shadcn/ui components

Do not introduce unnecessary UI libraries.

---

# Important UX behavior

The assistant should make it obvious when it is using Aeterna data.

For example:

> I found 6 software-development jobs in Casablanca from the available job listings.

For database-backed answers, avoid inventing values.

If no results exist:

> I couldn't find any matching jobs in Casablanca.

Do not fabricate jobs, alumni, events, companies, statistics, or URLs.

For general questions that do not require Aeterna data, the assistant can answer from the model's knowledge.

---

# Permission model

The assistant must respect existing Aeterna roles and permissions.

Examples:

### Alumni

Allowed:

```text
getMyProfile
searchJobs
getUpcomingEvents
careerAdvice
```

### Admin

Potentially allowed:

```text
searchJobs
getUpcomingEvents
getMyProfile
searchAlumni
getAlumniStatistics
```

But DO NOT hard-code these assumptions if the project already has a permission system.

Use the project's existing permission checks.

The AI tool layer must verify permissions independently of the frontend.

---

# Multi-tenancy

This is mandatory.

For every tenant-specific query:

```text
current authenticated user
        ↓
current organization
        ↓
organization-scoped query
```

Never do:

```php
User::all();
```

inside an AI tool if it can expose other organizations.

Prefer the project's existing relationships/scopes.

Example concept:

```php
$organization = $request->user()->organization;

$alumni = $organization
    ->users()
    ->where(...)
    ->get();
```

Adapt to the actual Aeterna schema.

---

# Security requirements

1. Never expose `.env` values to the frontend.
2. Never send database credentials to Ollama.
3. Never allow arbitrary SQL generated by the model.
4. Validate every tool argument.
5. Authorize every tool execution.
6. Scope tenant data.
7. Scope conversations to the authenticated user.
8. Do not expose private alumni information unless the existing permission system permits it.
9. Do not trust tool names or arguments supplied by the frontend; validate them server-side.
10. Limit conversation history/token size when necessary.
11. Handle Ollama being unavailable gracefully.

If Ollama is offline, show a useful error such as:

> Aeterna AI is currently unavailable. Please make sure the local AI service is running.

Do not expose raw stack traces to users.

---

# Development plan

Implement in this order.

## Phase 1 — Infrastructure

- Inspect the existing Aeterna architecture first.
- Identify the current User, Organization/Tenant, Job, Event, Alumni, Skill, and Permission models.
- Identify existing routes/controllers/services.
- Do not duplicate existing functionality.
- Add AI configuration.
- Add AI provider interface.
- Add Ollama provider.
- Test basic message → Ollama → response.

## Phase 2 — Conversations

- Create migrations for AI conversations/messages.
- Add models and relationships.
- Add authorization.
- Add controller endpoints.
- Persist chat history.

## Phase 3 — First tool

Implement:

```text
searchJobs()
```

Test:

> "Find Laravel jobs in Casablanca."

The answer must be based on actual database/API results.

## Phase 4 — Profile and events

Implement:

```text
getMyProfile()
getUpcomingEvents()
```

Test:

> "What jobs match my profile?"

> "What alumni events are happening this month?"

## Phase 5 — Admin tools

Implement:

```text
searchAlumni()
getAlumniStatistics()
```

Only expose them to authorized users.

## Phase 6 — Frontend polish

- Chat history
- Suggested prompts
- Loading state
- Error state
- Empty state
- Job result cards
- Event result cards
- Clear AI branding

## Phase 7 — Testing

Add tests for:

### Authorization

```text
Unauthorized user cannot use admin tools.
User cannot access another user's conversation.
User cannot access another organization's data.
```

### AI tools

```text
searchJobs returns real jobs.
getMyProfile returns current user only.
getUpcomingEvents respects organization visibility.
searchAlumni respects permissions.
statistics are organization-scoped.
```

### Failure cases

```text
Ollama unavailable.
Invalid tool arguments.
No jobs found.
No events found.
Empty profile.
Unknown user request.
```

---

# Do not over-engineer the first version

For the school presentation, the target is:

```text
User asks question
       ↓
Aeterna AI understands intent
       ↓
Laravel executes the correct tool
       ↓
Real Aeterna data is retrieved
       ↓
AI explains the result
```

A reliable 5-tool assistant is better than a huge AI system that is difficult to debug.

---

# Example final demo

Use this as the main presentation scenario:

### User

> Show me software development jobs in Casablanca that match my profile.

### Internal flow

```text
AI
 ↓
getMyProfile()
 ↓
searchJobs(
    keyword="software development",
    city="Casablanca"
)
 ↓
compare profile skills with returned jobs
 ↓
generate response
```

### User sees

> I found 5 software-development jobs in Casablanca.
>
> Based on your profile, these 3 are the strongest matches:
>
> **1. Laravel Developer — 92% match**
>
> - Laravel ✓
> - PHP ✓
> - MySQL ✓
> - React ✓
>
> **2. Full-Stack Developer — 86% match**
> ...
>
> You could improve your match for similar positions by developing TypeScript and Docker skills.

The exact percentages should only be shown if a deterministic matching method is implemented. Otherwise use qualitative wording such as "strong match."

---

# Coding instructions for Claude

Before modifying code:

1. Inspect the repository.
2. Read the existing architecture and relevant models/controllers/routes.
3. Determine the actual tenant and permission implementation.
4. Determine the existing Jobs and Events schema.
5. Reuse existing services/components.
6. Do not replace working authentication, authorization, or tenancy code.
7. Make the smallest clean changes necessary.
8. Keep code typed and consistent with the project's existing standards.
9. Run relevant tests after each major phase.
10. Fix failures rather than bypassing tests.

Do not make unrelated changes.

Do not rewrite the project architecture just to add AI.

At the end, provide:

```text
Implemented:
- ...

Files changed:
- ...

Migrations:
- ...

Environment variables:
- ...

Commands to run:
- ...

Tests:
- ...

Known limitations:
- ...
```

# Definition of done

The feature is considered complete when:

- A user can open Aeterna AI.
- A conversation can be created and persisted.
- User messages reach the local Ollama model.
- The AI can call at least `searchJobs`, `getMyProfile`, and `getUpcomingEvents`.
- Laravel executes tools securely.
- Tenant isolation is enforced.
- Existing permissions are respected.
- The assistant does not invent database-backed results.
- The UI works with the existing Aeterna design system.
- Ollama/API failures are handled gracefully.
- Relevant automated tests pass.
- No paid AI API is required for local development/demo.

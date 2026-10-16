# Task 6 Plan — User grouping, roles & page permissions

## Current state
- Roles are a `users.role` enum column (`UserRole`: `admin`, `agent`); every policy checks `$user->isAdmin()`.
- `spatie/laravel-permission` 8.3 is installed and its tables are migrated (`2026_10_16_181709`), but nothing uses it yet (`teams` = false).
- Admin pages (`routes/admin.php`) are all gated by `can:viewAny,User`, so every admin sees everything.
- There is no concept of campaign, team, manager/supervisor, or onshore/offshore.

## Goals
1. New user fields on create/edit: **onshore/offshore**, **campaign**, **team**. Manager and supervisor come from the team.
2. Four roles:
   - **super_admin**: sees all data in the system.
   - **admin**: manages the system, scoped to the agents on their team(s).
   - **supervisor**: focuses on performance and quality (reports, training progress, roleplay/call review), scoped to their team(s).
   - **agent**: unchanged.
3. One permission for every page, assigned to roles (Spatie).

---

## 1. Data model

### New tables
| Table | Columns |
|---|---|
| `campaigns` | `id`, `name` (unique), `is_active` (bool, default true), timestamps |
| `teams` | `id`, `campaign_id` (FK → campaigns, required, cascadeOnDelete), `name`, timestamps. Unique on `[campaign_id, name]` |
| `team_leaders` | `id`, `team_id` (FK → teams, cascadeOnDelete), `user_id` (FK → users, cascadeOnDelete), `role` (`TeamLeaderRole`: `manager` / `supervisor`), timestamps. Unique on `[team_id, user_id]` |

A super_admin decides how many managers (admins) and supervisors each team has, from zero to many.

### `users` table additions
- `work_location`: string enum `onshore` / `offshore`, nullable (pulled agents won't have it).
- `team_id`: FK → teams, nullable, nullOnDelete.

The campaign comes from the team (`$user->team->campaign`), so there's no `users.campaign_id`. Managers and supervisors also come from the team's `team_leaders`, so they're never stored twice on users. A user can then be grouped by campaign, team, manager, supervisor or location.

### Enums and models
- `App\Enums\WorkLocation` (`Onshore`, `Offshore`) with labels.
- `App\Enums\TeamLeaderRole` (`Manager`, `Supervisor`).
- `App\Enums\UserRole`: add `SuperAdmin = 'super_admin'` and `Supervisor = 'supervisor'`. The enum keeps the role names in one place.
- `App\Enums\Permission`: one case per page/action (see §3), so no permission strings are hard-coded.
- Models `Campaign` and `Team` (`php artisan make:model -mfs`), with relations:
  - `Campaign hasMany Team`, `Campaign hasManyThrough User (agents)`
  - `Team belongsTo Campaign`, `hasMany User (members)`, `belongsToMany User as leaders` (pivot `team_leaders`, `withPivot('role')`), plus `managers()` and `supervisors()` filtered by pivot role
  - `User belongsTo Team`, `belongsToMany Team as ledTeams` (pivot `team_leaders`), plus `managedTeams()` and `supervisedTeams()`

## 2. Roles: move to Spatie
- Add the `HasRoles` trait to `User`. Spatie becomes the source of truth for roles and permissions.
- Migration (data step):
  1. Create the four roles and all permissions. A `RolesAndPermissionsSeeder` is also called from that migration so production gets it.
  2. Give each existing user a role based on `users.role`: `admin` → **super_admin** (they see everything today), `agent` → `agent`.
  3. Drop the `users.role` column, either in the same release or a follow-up once verified.
- Replace `User::isAdmin()` with intent-based helpers:
  - `isSuperAdmin()`, `hasRole(...)`
  - `canImpersonate()`: super_admin, or admin for agents in their teams.
  - `canBeImpersonated()`: an active non-super_admin.
- `Gate::before` in `AppServiceProvider`: super_admin passes every check.
- `HandleInertiaRequests`: share `auth.user.roles` and `auth.permissions` (a flat string array), replacing `auth.user.role === 'admin'`.
- `UserFactory`: replace the `admin()` state with `superAdmin()`, `admin()`, `supervisor()` and `agent()` states that assign Spatie roles, plus a `forTeam(Team)` state. Update `DatabaseSeeder`.

## 3. Permissions (one per page, with actions where needed)

| Permission | Page / route | super_admin | admin | supervisor | agent |
|---|---|:-:|:-:|:-:|:-:|
| `dashboard.view` | `/dashboard` | ✓ | ✓ | ✓ | ✓ |
| `training.view` | My Training (`training.*`) | ✓ | ✓ | ✓ | ✓ |
| `roleplay.medicare.view` | Medicare Roleplay | ✓ | ✓ | ✓ | ✓ |
| `roleplay.aca.view` | ACA Roleplay | ✓ | ✓ | ✓ | ✓ |
| `roleplay.meals.view` | Meals Roleplay | ✓ | ✓ | ✓ | ✓ |
| `licenses.view` | My Licenses | ✓ | ✓ | ✓ | ✓ |
| `users.view` | Admin › Users index/show | ✓ | ✓ | ✓ | |
| `users.create` | create + pull agents | ✓ | ✓ | | |
| `users.update` | edit, bulk license, tracks | ✓ | ✓ | | |
| `users.delete` | delete, bulk delete | ✓ | ✓ | | |
| `users.impersonate` | impersonate | ✓ | ✓ | | |
| `reports.view` | Admin › Reports | ✓ | ✓ | ✓ | |
| `training-tracks.view` | Admin › Training Tracks | ✓ | ✓ | ✓ | |
| `training-tracks.manage` | assign tracks to users | ✓ | ✓ | | |
| `exam-retakes.grant` | grant exam retakes | ✓ | ✓ | ✓ | |
| `roleplay-sessions.review` | view and rate other agents' roleplay attempts | ✓ | ✓ | ✓ | |
| `call-logs.review` | rate/update call logs | ✓ | ✓ | ✓ | |
| `screening.view` | Admin › Screening + responses | ✓ | | | |
| `screening.manage` | create screening, delete responses | ✓ | | | |
| `vertical-training.view` / `.manage` | Admin › Vertical Training | ✓ | ✓ | | |
| `licensing.view` / `.manage` | Admin › Licensing + steps | ✓ | ✓ | | |
| `campaigns.manage` | new Campaigns page | ✓ | | | |
| `teams.manage` | new Teams page | ✓ | | | |
| `roles.manage` | new Roles & Permissions page | ✓ | | | |

### Enforcement
- **Routes**: split the `routes/admin.php` group from `can:viewAny,User` to per-route `->can(Permission::X->value)` or `permission:` middleware. Register Spatie's `role`/`permission` middleware aliases in `bootstrap/app.php`. Agent routes in `web.php` also get their `*.view` permission.
- **Policies**: each existing policy (`User`, `License`, `Screening`, `ScreeningResponse`, `VerticalTraining`, `CallLog`, `RoleplaySession`, `CourseTrack`) changes from `isAdmin()` to `$user->can(Permission::X)`, **and** checks scope for records tied to a user (§4).
- **Sidebar** (`app-sidebar.tsx`): show each nav item only if `auth.permissions` includes its permission, via a small `useCan()` helper. Remove the `isAdmin` checks.

## 4. Team scoping (admin & supervisor)
- `User::scopeVisibleTo(Builder $query, User $viewer)`:
  - super_admin: no constraint.
  - admin: `team_id IN (viewer.managedTeams)` (a subquery on `team_leaders`), plus the viewer themself.
  - supervisor: `team_id IN (viewer.supervisedTeams)`, plus the viewer themself.
  - agent: only themself.
- `User::canManage(User $model): bool` uses the same rule for a single record, for use in policies.
- Apply the scope in:
  - `Admin\UserController@index`, bulk destroy and bulk license (reject IDs outside scope through policies)
  - `Admin\ReportController@agentReportQuery`
  - `Admin\TrainingTrackController` index/show
  - `DashboardController` admin stats (`totalAgents`, `activeAgents`, `AgentTrainingReport` IDs)
  - `RoleplaySessionPolicy::view` and `CallLogPolicy` (the session/log owner must be visible)
  - `ExamRetakeController@store` (the agent must be visible)
- Screening is **super_admin only** for now, so it needs no team scoping. `screeningAwaitingReview` in `HandleInertiaRequests` is computed only when the user has `screening.view`.
- Role assignment guard: an admin can only create or edit users with role `agent` or `supervisor`, and only into their own teams. Only super_admin can grant `admin` or `super_admin`.

## 5. Users UI (create / edit / index / show)
- `StoreUserRequest` / `UpdateUserRequest`:
  - `role` must be in the roles the actor may assign.
  - `work_location`: `Rule::enum(WorkLocation::class)`, required for manual users.
  - `team_id`: exists, and within the actor's managed teams unless super_admin.
- `UserController@store/update`: `syncRoles([$role])` and save the new fields. `create`/`edit` pass `roles` (assignable only), `workLocations`, and the `teams` the actor may assign, grouped by campaign.
- `UserResource`: add `work_location`, `team` (with campaign, manager and supervisor names) and `roles`.
- `admin/users/create.tsx` / `edit.tsx`: an Onshore/Offshore toggle (segmented `ToggleGroup`), a Campaign select used only to filter the list, and a Team select. Only `team_id` is saved.
- `admin/users/index.tsx`: new filters for campaign, team, manager, supervisor and location, and columns for Team and Location. Reports also get campaign, team and location filters.
- Pulled agents (`PullAndSyncAgentsPageJob`) are created with no team. A super_admin assigns them afterwards; consider a bulk "Assign team" action next to "Assign license".

## 6. New admin pages (super_admin)
- **Campaigns** (`admin/campaigns`): CRUD, following the Licensing pages' pattern.
- **Teams** (`admin/teams`): CRUD with a campaign select, multi-selects for managers (admin users) and supervisors (supervisor users), and a member count. Leaders are saved to `team_leaders` with their pivot role.
- **Roles & Permissions** (`admin/roles`): a matrix of roles × permissions with checkboxes, grouped by page and saved via `syncPermissions`. super_admin is locked (all permissions, read-only column). Saving clears Spatie's permission cache. The seeded defaults in §3 are the starting values.

## 7. Tests (Pest, feature)
- Migration/data: existing admins become super_admin and agents become agent.
- Users: store/update persist `work_location`, `team_id` and the role. An admin assigning a team they don't manage is rejected.
- Scoping: an admin sees only agents on teams they manage in Users, Reports, Training Tracks and Dashboard counts, and gets 403 on show/edit/impersonate of others. The same holds for a supervisor (read-only), while super_admin sees all. A team with two supervisors gives both of them access.
- Quality actions: a supervisor can rate call logs and roleplay attempts and grant exam retakes for their team's agents, but not for other teams.
- Permissions: a supervisor gets 403 on users create/delete, licensing and vertical training. An admin gets 403 on screening. An agent gets 403 on all `/admin/*`. Campaigns, Teams and Roles pages are limited to super_admin.
- Roles page: toggling a permission off for a role removes access on the next request.
- Role escalation: an admin cannot assign `admin` or `super_admin`.
- Update the existing tests that use `User::factory()->admin()` to the new factory states.

## 8. Order of work
1. Enums, Campaign/Team models, migrations, factories, seeder
2. Spatie roles: `HasRoles`, data migration, `Gate::before`, factory states, fix existing tests
3. Permission enum, route middleware, policy rewrites, shared props, sidebar
4. Scoping (`visibleTo` / `canManage`) across controllers and policies
5. User create/edit/index UI and requests
6. Campaigns, Teams, and Roles & Permissions admin pages
7. Tests, `pint`, `wayfinder:generate`, `npm run build`

## Decisions
1. All existing admins become `super_admin`.
2. A team can have several managers and supervisors. The super_admin assigns them on the Teams page (`team_leaders` pivot).
3. A team belongs to exactly one campaign. Users store only `team_id`, and the campaign comes from the team.
4. Screening is super_admin only for now.
5. The editable Roles & Permissions matrix page is included.
6. Supervisors, admins and super_admins can rate call logs and roleplay attempts and grant exam retakes, scoped to their teams (super_admin: all).

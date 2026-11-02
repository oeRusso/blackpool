# ADR 0002 — Server-side authorization with Policies and permissions

- **Status:** accepted · 2026-11-02

## Context

The legacy app only hid buttons with `permissions.includes(...)` in Vue. Registration was open, so any registered user could create, edit or delete categories with direct HTTP requests.

## Decision

- Model roles and permissions as PHP enums (`RoleName`, `PermissionName`) and seed them with spatie/laravel-permission.
- Authorize every action on the server:
    - a `can:access-admin` gate for the `/admin` prefix;
    - Policies for categories, lessons, users and roles;
    - `FormRequest::authorize()`.
- Express content access as policy abilities. `LessonPolicy::view` covers metadata; `LessonPolicy::watch` covers the video, PDF, quiz and progress, with free and premium rules.
- Send the frontend a server-computed ability map (`auth.can`) that is used only to show or hide UI.
- Keep business constraints in policies, not in a `Gate::before` admin bypass. For example, a category with lessons cannot be deleted and an admin cannot change their own roles; both rules apply to admins too.

## Consequences

- One source of truth for permissions, covered by Pest and Playwright regression tests (including forged requests).
- New features must add a policy ability and a test before shipping. The architecture tests and code review enforce this convention.

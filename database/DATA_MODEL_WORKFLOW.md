# Compliance workflow & assignment (RBAC)

- **User role** is stored in `users.role_id` → `roles.slug` (`admin`, `maker`, `reviewer`, `approver`). Session `role_slug` is refreshed on each request via `Auth::syncRoleFromDatabase()`.
- **Compliance assignment** uses:
  - `compliances.owner_id` — Maker (submit / upload)
  - `compliances.reviewer_id` — Reviewer (forward / rework)
  - `compliances.approver_id` — Approver (final approve / reject)
- **Workflow state** is `compliances.status`: `draft` → `pending` → `submitted` → `under_review` → `completed` / `rejected` (with `rework` returning to maker).

There is no separate `assigned_to` column; `owner_id` is the primary assignment for makers.

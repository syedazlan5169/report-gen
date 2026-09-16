# Report Generator

Report Generator is a small Laravel application for producing deterministic, copy-ready WhatsApp attendance reports from staff information. It replaces a manual workflow without relying on AI-generated results.

## Core workflow

1. Register or log in with a username and password.
2. Set up staff, shifts, and report templates.
3. Generate reports from the selected shift and attendance inputs.
4. Copy each report into the appropriate WhatsApp group.

The application is mobile-first because daily use is expected primarily on a phone.

Authentication is username-only. Usernames are trimmed, normalized to lowercase, and unique. V1 has no email field, email verification, or email-based password reset. Laravel handles password hashing and Remember me persistence; browsers and password managers may autofill credentials, but the application never stores plaintext passwords.

## Planned V1 entities

- `users`
- `staff`, including `short_code`, `rank_prefix`, integer `staff_number`, `is_base_member`, and `is_active`
- `shifts`
- `report_templates`, including plain-text `body`, per-user unique `name`, `is_enabled`, and `sort_order`

Each user owns one independent configuration and one logical base group. There is no `base_groups` table in V1; base membership is stored on `staff.is_base_member`.

Staff records are entered manually by each user. `short_code` is normalized to uppercase and is unique per user. `staff_number` is stored as an integer and is unique per user. V1 uses hard delete and displays staff by `short_code` ascending; there is no `sort_order`.

Shift records are entered manually by each user. `code` is trimmed, stored lowercase, and unique per user. `display_name` is retained for readable labels. `start_time` and `end_time` are database time columns; overnight shifts such as `22:00` to `07:00` are valid. V1 uses hard delete and displays shifts by `start_time`, then `code`; there is no `sort_order`.

Report templates are hard-deleted and displayed by `sort_order` ascending, then `id` ascending. Names are unique per user, while body text is stored exactly as entered. Bodies accept only the fixed V1 placeholders `{{date}}`, `{{day}}`, `{{shift_start}}`, `{{shift_end}}`, `{{shift_time_range}}`, `{{supervisor}}`, `{{working_staff_list}}`, `{{leave_staff_list}}`, `{{overtime_staff_list}}`, and `{{attendance_count}}`. Placeholder syntax is `{{placeholder_name}}`; unsupported or malformed placeholders are rejected. This module only configures templates and does not render or replace them.

## Technology

- PHP 8.4-compatible Laravel application
- Blade server-rendered views
- Tailwind CSS
- Alpine.js for small client interactions
- MySQL 8.4 in the production environment

Report generation will use explicit application rules and safe placeholder substitution. It will not use AI or executable user-provided template code.

## Production target

Production is planned for the VPS described in `docs/VPS_DEPLOYMENT_PROFILE.md`: Dockerized Laravel/PHP-FPM, an app-specific Nginx container behind host Nginx and Certbot, and the shared `probono-db` MySQL network. Production deployment is deferred until the application is complete.

# I’ve News

A responsive, newspaper-inspired PHP publication with section archives, individual story pages, search, a newsroom content manager, and a MySQL-backed editorial workflow.

## Run locally with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. In phpMyAdmin, import [`schema.sql`](./schema.sql). This creates the `ivenews` database, editorial roles and permissions, audit and revision tables, and starter sections.
3. If your local MySQL credentials differ from XAMPP’s defaults, configure the PHP environment variables before starting Apache:

   ```text
   IVENews_DSN=mysql:host=127.0.0.1;dbname=ivenews;charset=utf8mb4
   IVENews_DB_USER=root
   IVENews_DB_PASSWORD=your-local-database-password
   IVENews_APP_KEY=your-long-random-secret
   ```

   The application defaults to a local `ivenews` database, user `root`, and an empty password for a standard XAMPP development installation. Use a dedicated database account and strong secrets outside local development.
4. Visit `http://localhost/ivenews/setup.php` once to create the first administrator. Setup also creates four **unpublished starter drafts** based on the earlier homepage. Review and update these older stories before publishing them.
5. Sign in at `http://localhost/ivenews/admin/login.php`. Once setup has created an account, the setup page disables itself.

`schema.sql` is denied over HTTP by the included Apache rules. `.htaccess` protections require Apache 2.4 and `AllowOverride All`. If you deploy behind a different web server, configure equivalent rules to deny access to `schema.sql` and the `includes` directory.

## Public pages and newsroom

- The front page displays the latest published reporting and editor’s picks.
- Select a section to read its topic archive; archives and search results are paginated.
- Open a story for the full reading layout, reading progress, sharing, and source links.
- The newsroom supports drafts, publishing, archiving, featured stories, section management, staff accounts, and an audit history.
- Writers reference existing image files under `img/` instead of uploading files. This keeps the initial CMS simple; add validated image uploads before accepting uploads from untrusted staff.

## Roles and permissions

| Role | Permissions |
|---|---|
| **Administrator** | All editorial permissions: create/edit any story, publish and archive, manage sections and staff, and view audit history. The first administrator is created through setup. Later administrators must be granted directly by a trusted database operator. |
| **Editor** | Create, review, and edit any story; publish and archive stories; manage sections. Cannot manage staff or access audit history. |
| **Author** | Create stories and edit their own drafts. Cannot publish, archive, feature, or edit another person’s work. |
| **Reader** | Public access to published stories, sections, and search; no account or write access required. |

Permissions are stored separately from roles in `permissions` and `role_permissions`, so they can be adjusted without changing the application’s permission checks.

## Database schema

| Table | Purpose |
|---|---|
| `roles`, `permissions`, `role_permissions` | Role definitions and normalized role-to-permission assignments. |
| `users` | Staff identity, password hashes, active state, login lockout counters, and last sign-in. |
| `categories` | Topic/section names, URL slugs, descriptions, and display ordering. |
| `articles` | Story content, author and section relationships, image metadata, workflow state, feature flag, and publication dates. |
| `article_revisions` | Previous article text and status snapshots captured before edits. |
| `audit_logs` | Who performed an action, on which record, when, from which IP, and structured JSON details. |
| `login_attempts` | Time-indexed authentication attempt records for throttling repeated sign-in failures. |

The schema uses InnoDB foreign keys, `utf8mb4`, unique account and URL slugs, indexes for the public feed and editorial lists, and append-only revision/audit records. Consider a retention policy for login and audit records and regular database backups before production use.

## Notes

- PHP 8.1+ with PDO MySQL and MySQL 8+ or a compatible MariaDB server is recommended.
- Story content is plain text; output is escaped and paragraph line breaks are preserved.
- Passwords use PHP’s `password_hash()`. Sign-in uses CSRF tokens, secure/HTTP-only/SameSite session cookies, session-ID rotation, per-account lockouts, and IP/email attempt throttling.
- On HTTPS deployments, enable HTTPS-only cookies and configure the web server’s TLS and security headers.

# Automating schema updates on Fornex

The production site is deployed via FTPS. DB migrations are an **independent SSH-only workflow**, never run from a public URL.

## One-time setup in GitHub (tesla.bosh.kg repo)

Already configured: `SSH_PRIVATE_KEY` and `SSH_KEY_PASSPHRASE` repository secrets.

Still required:
- Repository secret `SSH_HOST`: the externally accessible **SSH hostname** supplied by Fornex.
- Repository variable `SSH_PORT`: SSH service port from Fornex (default 22 only if explicitly confirmed).
- Repository secret `SSH_KNOWN_HOSTS`: host key record from a trusted/verified source for the same host/port. Cross-check host key fingerprint with Fornex before trusting it. Do **not** use `StrictHostKeyChecking=no`.

The public key `github_tesla.pub` must be authorized for account `g143326`. The private key and its passphrase stay only in Actions secrets; do not send either in chat.

## First migration

Open Actions > Apply EV database migrations (SSH) > Run workflow.
Workflow uploads only `database/` and `tools/migrate.php` to `/home/g143326/.tesla-migrations` outside public_html. Executes the runner using the Fornex PHP 8.3 CLI and reads `/home/g143326/.tesla-ev.env`, without touching the existing website .env or station APIs.

The runner uses MySQL `GET_LOCK`, creates `schema_migrations`, records checksums, and applies each SQL migration once, in filename order. Existing `001_accounts.sql` uses IF NOT EXISTS and INSERT IGNORE, so rerunning it after manual import will not discard data. `002_google_auth.sql` creates the required Google identities table.

MySQL DDL **cannot be rolled back atomically**. If an error occurs halfway through a migration, the workflow stops; inspect and fix before retrying. For future destructive schema changes, take a verified database backup first and author a backwards-compatible migration. Never edit an already applied migration file; add a new numbered migration.

## Before automatic triggering

Initially this workflow is `workflow_dispatch` only. After confirming one successful run, SSH host verification, and backup process, update it to trigger on new migrations merged to `main` (e.g. `on.push.paths: ['database/**']`). Do not enable automatic execution before connectivity and recovery are verified.

## Google sign-in

After migration 002 succeeds, Google OAuth still requires `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` appended to the private Fornex config file (outside public_html). See `docs/GOOGLE_SIGNIN_SETUP.md`.

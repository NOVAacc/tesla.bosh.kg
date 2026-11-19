# EV accounts foundation (staged, not live)
This code is prepared for production review and has NOT been deployed or migrated.
Existing charging endpoints remain unchanged.

## Install
1. In Fornex phpMyAdmin, choose the dedicated MySQL database and import database/001_accounts.sql. Do not import into the BOSH main database.
2. For the g143326 Fornex account create /home/g143326/.tesla-ev.env (outside public_html) with DB_HOST="localhost", DB_PORT="3306", DB_DATABASE="...", DB_USERNAME="...", DB_PASSWORD="...". Restrict permissions to 600 and ensure the PHP account can read it. Do NOT place this file inside the website document root, upload it through GitHub, or send its contents in chat. EV_PRIVATE_ENV may override the path when the server environment supports it. GitHub repository secrets are CI-only and are not inherited by PHP.
3. Ensure PHP 8.1+ with pdo_mysql and mbstring; force HTTPS.
4. Validate /api/account.php?action=me returns user:null and a csrf value, then categories. Existing /api/stations must stay functional.
5. Configure SMTP and email verification before allowing registration. Public registration currently deliberately returns 503; no user accounts can be created using the public endpoint. Admin bootstrap must be done manually through a secure one-time CLI procedure (not yet implemented).

## Security and scope
- All actions that mutate server state use CSRF tokens and POST.
- Session cookie Secure, HttpOnly and SameSite=Lax.
- Login throttled by email/IP hash via SQL.
- Passwords expected via PHP password_hash(), never plaintext.
- Login requires verified email. No admin promotion routes.
- No payment, wallet, personal phone, password reset, or charging control implementation.
- Business listings are only public if approved by an administrator; approval UI and admin API are not yet included.
- No modifications to existing charging API.
- Before production, implement email verification/password reset, stable audit logs, account deletion, admin actions and rate limits for registration.

# Google account sign-in for tesla.bosh.kg
Do not commit any credentials. Do not place secrets in public_html.

## Database migration (required before enabling)
Import database/002_google_auth.sql in phpMyAdmin into the existing tesla.bosh.kg MySQL database. It only creates user_google_identities and does not replace existing user tables.

## Google Cloud Console
1. Visit https://console.cloud.google.com/auth/overview and choose or create a Google Cloud project.
2. Complete Google Auth Platform Branding and Audience (External if public sign-in). For Testing audience, add test-user Google addresses. Configure authorized domain bosh.kg; ensure required policy/contact URLs are available.
3. Create an OAuth Client ID of type **Web application** in Google Auth Platform > Clients.
   Authorized JavaScript origin (optional for this server redirect flow): https://tesla.bosh.kg
   Authorized redirect URI (MUST match exactly): https://tesla.bosh.kg/api/google-callback.php
4. Put these credentials in the EXISTING private file `/home/g143326/.tesla-ev.env` (outside public_html), appending only:
   GOOGLE_CLIENT_ID="YOUR_CLIENT_ID.apps.googleusercontent.com"
   GOOGLE_CLIENT_SECRET="YOUR_CLIENT_SECRET"
   Do not rewrite or remove DB_* values. Restrict file to user with chmod 600.
5. Open https://tesla.bosh.kg/account.html and choose Continue with Google. First successful login creates a user with role=user; subsequent logins use Google's stable `sub` identifier.
6. An account that already exists with the same email is never automatically linked. Such login returns existing_email. Account-linking must be added later as a verified, authenticated operation.

## Security
OAuth authorization code flow with secure HTTP-only session, random state and nonce (10-minute single use), fixed redirect URI, server-side client secret and token exchange, Google tokeninfo verification of issuer/audience/nonce/expiration/email verification, parameterized SQL, transaction, regenerated PHP session ID.
No refresh token stored; only identity scopes requested. Google sign-in works independently of password registration, which remains disabled.
Google tokeninfo performs remote verification per login; consider Google auth SDK + cached JWKs for scale.

AR HOMEMADE — SETUP AND QA NOTES
================================

1. REQUIREMENTS
- XAMPP (Apache, PHP 8+, MySQL/MariaDB)
- A modern browser

2. INSTALL
- Copy the AR_Homemade folder into C:\xampp\htdocs\ (Windows/XAMPP).
- Start Apache and MySQL in the XAMPP Control Panel.
- Open http://localhost/phpmyadmin
- Create a database named AR_Homemade, or use the database name already configured in your environment.
- Import AR_Homemade.sql into that database.
- Open http://localhost/AR_Homemade/login.php

3. DATABASE CONNECTION
Default settings are XAMPP defaults: host 127.0.0.1, user root, blank password, database AR_Homemade.
To use different settings, configure environment variables AR_DB_HOST, AR_DB_USER, AR_DB_PASSWORD, and AR_DB_NAME.

4. IMPORTANT FIRST-RUN CHECKS
- Confirm that you can log in with the credentials provided by your project administrator. The SQL file contains password hashes, not readable passwords; do not assume the passwords are 'admin' or 'staff'.
- Create actual ingredient records and set recipes in Ingredients & Recipes before completing an order. Recipe data is intentionally not guessed or seeded because ingredient quantities must reflect the business's real recipes.
- Verify ingredient opening quantities and low-stock thresholds against actual inventory before using this for real operations.
- The bundled SQL contains sample/demo order and stock data. Review it before production use.

5. CHANGES MADE IN THIS REPAIR
- Matched the default database name to AR_Homemade as named in the SQL dump (important on case-sensitive MySQL hosts).
- Added configurable database connection settings and clearer, logged connection failure handling.
- Regenerated the PHP session ID after successful login.
- Changed user deletion from a destructive GET link to a POST request protected by a session CSRF token.
- Prevented deleting accounts referenced by order history, preserving referential integrity.
- Added feedback messages for successful or blocked user deletion.

6. VALIDATION LIMITS
All PHP files were checked for PHP syntax errors. A live database/server was not available in this environment, so SQL import, login, transaction behavior, and browser rendering must still be tested in your local XAMPP environment. This is not a claim of production certification.

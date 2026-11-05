# Facility Issue Reporting System

A PHP and MySQL web application for submitting and managing facility issue
reports. It includes an AI-assisted reporting chatbot, staff assignments,
notifications, an admin dashboard, and PDF report export.

## Requirements

- Windows with XAMPP (Apache and MySQL)
- PHP with the `mysqli`, `fileinfo`, and OpenSSL extensions enabled
- Composer
- A Gemini API key to use the AI chatbot

The application expects MySQL at `localhost` with a database named `fyp`.
The default local configuration uses the XAMPP MySQL `root` account without a
password. If your local MySQL credentials differ, update the local configuration
and database connection settings before running setup.

## Installation and startup

1. Place the project folder at `C:\xampp\htdocs\FYP`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open PowerShell in the project folder and install the PHP dependencies:

   ```powershell
   composer install
   ```

4. Create local configuration files from the examples if they do not already
   exist:

   ```powershell
   Copy-Item config.example.php config.php
   Copy-Item "auth example.php" auth.php
   ```

   Keep `config.php` and `auth.php` local. They are ignored by Git because they
   contain machine-specific settings and may contain credentials.

5. Add your Gemini API key to '`config.php` as the value for `GEMINI_API_KEY`.
   Keep the key private and do not commit it.
6. If your MySQL username or password is not the default, update the database
   connection settings in `setup.php` and `auth.php` to match.
7. Initialize the database by opening:

   If wish to change admin email and password, do check the `setup.php` at 
   the end of the code which it shows:
   ```text
   $adminEmail = 'admin@example.com';
   $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
   $adminName = 'Administrator';
   $adminRole = 'admin';
   ```
   
   Change it if you wish

   ```text
   http://localhost/FYP/setup.php
   ```

   The setup script creates the `fyp` database and required tables, and seeds
   initial data. It also creates a default administrator account if one does
   not already exist. Follow the credentials displayed by setup, then change
   the default password after signing in.
8. Open the application:

   ```text
   http://localhost/FYP/Login.php
   ```

   Register a user account or sign in with the administrator account created
   during setup.

## Optional email configuration

Email-based OTPs and staff assignment notifications require working SMTP
settings in the local `auth.php` file. Configure these for your SMTP provider.
For local development without SMTP, the `MAIL_DEBUG_TO_FILE` option can be
enabled to write email messages to a local log instead.

## Notes

- The AI chatbot requires a valid Gemini API key and PHP URL streams
  (`allow_url_fopen`) to be enabled.
- Report photo uploads are stored in the `uploads` directory. Ensure the
  Apache/PHP process can write to that directory.
- Run `setup.php` only in a local or otherwise protected environment. Do not
  leave the setup page publicly accessible on a production server.
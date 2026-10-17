# Deployment Guide

## InfinityFree Hosting Instructions

### 1. Web Root and `.env` Handling
On InfinityFree, the project root directory maps directly to the web root (`htdocs`).
- **Never commit `.env` to Git**: The `.env` file is git-ignored and must never be pushed to the remote repository or deployment pipeline.
- **Manual FTP Upload**: The `.env` configuration file must be uploaded **by hand over secure FTP (FTPS/SFTP)** directly into the project root directory on the server.
- **HTTP Protection**: The root `.htaccess` file enforces strict server-level deny rules (`Require all denied` on `<FilesMatch "^\.env">`) to block any direct web access to `.env` and `.env.*` files.

### 2. PayMongo Gateway Mode
- Maintain `PAYMONGO_IS_TEST=true` in `.env` on production until the owner confirms that the PayMongo merchant account is fully verified, activated, and authorized for live digital payments.
- When `PAYMONGO_IS_TEST=true`, live transactions are not charged, preventing accidental live debits during staging or pre-verification periods.
- Once verified by the owner, update `.env` to `PAYMONGO_IS_TEST=false` to enable live PayMongo processing.

### 3. File Permissions and Security Checks
- Ensure root `.htaccess` is present and deployed to protect sensitive files (`.env*`, `*.sql`, `*.md`, `*.log`, `*.txt` except `robots.txt`, and composer manifests).
- Run `php tools/check_exposure.php` before release to verify that no sensitive files or credentials have been tracked by version control.

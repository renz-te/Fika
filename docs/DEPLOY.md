# Fika System Deployment Guide

## Production Environment
The production environment is hosted on **InfinityFree**. It is an FTP-only environment with no shell access, and the database is managed via phpMyAdmin.

## Core Deployment Principles
To prevent overwriting critical setup and properly isolate the new architecture:
1. **Never upload legacy directories**: `cafe_hrms/` and `cafe_pos/` must not be uploaded to the production server.
2. **Whitelist approach**: Only upload explicitly defined core and module directories:
   - `app/`
   - `config/` (excluding `.env` and `config.php` containing local credentials, handled by `.ftpignore` or workflow exclusions)
   - `migrations/`
   - `fika_hrms/`
   - `fika_pos/`
   - `fika_finance/`
   - `fika_inventory/`
3. **Configuration**: On the InfinityFree server, you must manually create/update the `config.php` file using production credentials. Do not commit production credentials to Git.
4. **Database Migrations**: Run SQL migrations manually via phpMyAdmin on InfinityFree.

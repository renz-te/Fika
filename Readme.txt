Fika System
===========

This repository contains the complete ecosystem for the Fika project.

Directory Structure
-------------------
- cafe_hrms/ : The Human Resource Management System (HRMS) module.
- cafe_pos/  : The Point of Sale (POS) and Kiosk module.

Environment & Configuration
---------------------------
- The main database configuration is located in `cafe_hrms/config.php`.
- The configuration script dynamically switches credentials based on whether the system is running locally or on the live production server (InfinityFree).
- Core routing relies on `.htaccess` to enable clean URLs (removing `.php` extensions) while safely bypassing API endpoints for seamless AJAX fetches.

Deployment (CI/CD)
------------------
- This project utilizes GitHub Actions for continuous deployment.
- Pushing to the `main` branch will trigger the FTP deployment workflow (`.github/workflows/deploy.yml`), automatically syncing your changes to the live production server.

Notes
-----
Ensure that local testing is performed through the Laragon environment (`http://localhost/Fika/Fika/cafe_hrms` or standard virtual hosts) before pushing to `main` to prevent breaking changes on the live site.

# PHP Scripts Cleanup Plan

## Current Issues
1. Too many overlapping PHP scripts with similar functionality
2. No clear organization of scripts
3. Scripts from different stages of troubleshooting mixed together
4. Difficult to determine which scripts are still relevant

## Organization Plan

### 1. Create a structured directory for utilities
```
faveo/public/utils/
  ├── health/         # Health and diagnostic scripts
  ├── fixes/          # URL and configuration fixes 
  ├── database/       # Database utilities
  ├── installation/   # Installation scripts
  └── admin/          # Admin utilities
```

### 2. Consolidate scripts by function

#### Health and Diagnostics
- `health.php` - Simple health check (Railway health check endpoint)
- `diagnostics.php` - Comprehensive system diagnostics (combines various diagnostic scripts)

#### Core Fixes
- `url-fix.php` - Consolidated URL redirect fix (combines all URL fixes)
- `bootstrap-fix.php` - Laravel bootstrap fixes
- `permissions-fix.php` - File permission fixes

#### Database Utilities
- `database-setup.php` - Database configuration and setup
- `run-migrations.php` - Database migration utility
- `database-test.php` - Database connection testing

#### Admin Utilities
- `reset-password.php` - Admin password reset tool
- `create-admin.php` - Admin user creation tool

### 3. Scripts to Combine/Delete

#### Scripts to combine into url-fix.php:
- `fix-url.php`
- `url-redirect-fix.php` 

#### Scripts to combine into database-setup.php:
- `db-fixed.php`
- `direct-db-setup.php`
- `db-connect-fix.php`
- `create-db.php`

#### Scripts to combine into diagnostics.php:
- `env-debug.php`
- `mysql-test.php`
- `db-test.php`
- `diagnose-facade.php`

### 4. Implementation Steps

1. Create the directory structure
2. Implement the consolidated scripts
3. Test each consolidated script for functionality
4. Update bootstrap scripts to use the new file locations
5. Update documentation to reflect the new organization
6. Remove obsolete scripts after successful testing

### 5. Documentation Updates

- Update `project-structure.md` with the new organization
- Update `CURRENT_STATUS.md` to reflect cleanup
- Update deployment scripts to reference new file paths 
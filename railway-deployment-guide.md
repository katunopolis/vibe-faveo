# Railway Deployment Guide for Faveo Helpdesk

This guide outlines how to fix URL redirect issues in Faveo Helpdesk deployed on Railway.

## The Problem

When deploying Faveo on Railway, URL redirects may cause the application to redirect from the main application URL to a URL with port 8080. This happens because:

1. The application is not properly configured with the correct URL.
2. The bootstrap process doesn't properly set the application URL.
3. The database may contain incorrect URL settings.

## Solution Approach

We have several scripts to fix these issues:

### 1. Fix Bootstrap Script

The `fix-bootstrap.php` script addresses permission issues and ensures `bootstrap/app.php` is properly included in the application. Upload it to your Faveo's public directory and run it via browser or command line.

**Usage:**
```bash
# Via command line
php public/fix-bootstrap.php

# Or access via browser
https://your-app-url.up.railway.app/fix-bootstrap.php
```

### 2. Bootstrap App File

The `bootstrap-app.php` file ensures Laravel's application container is properly initialized and fixes "Facade root has not been set" errors. This file should replace the original `bootstrap/app.php` file.

### 3. Permanent URL Fix Script

The `permanent-url-fix.sh` script detects the correct application URL and updates it in:
- Database settings
- .env file
- Configuration files
- bootstrap files

## Deployment Options

There are two main ways to implement these fixes on Railway:

### Option 1: Modify via Railway Terminal

1. Go to the Railway dashboard
2. Select your Faveo service
3. Click on the Shell tab
4. Navigate to the correct directory and modify the bootstrap.sh file:
   ```bash
   cd /usr/local/bin
   cat > permanent-url-fix.sh << 'EOF'
   # Paste the content of permanent-url-fix.sh here
   EOF
   chmod +x permanent-url-fix.sh
   
   # Edit bootstrap.sh to include the permanent-url-fix.sh
   vi bootstrap.sh
   # Add the fix_url_redirect call before Apache starts
   ```

### Option 2: Modify via Repository

1. Add the fix scripts to your Git repository
2. Update your Dockerfile to copy these scripts to the correct location
3. Add instructions to your bootstrap.sh file to run the scripts
4. Push changes to GitHub for automatic deployment

Example Dockerfile additions:
```dockerfile
# Copy the URL fix script
COPY permanent-url-fix.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/permanent-url-fix.sh

# Copy modified bootstrap.sh 
COPY bootstrap.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/bootstrap.sh
```

## Testing the Fix

After deployment, verify that:
1. Your application URL doesn't redirect to a port 8080 URL
2. Admin panel links work correctly
3. The application settings show the correct URL

## Troubleshooting

If issues persist:
1. Check the Laravel logs at `/var/www/html/storage/logs/laravel.log`
2. Verify database connection and settings
3. Ensure all scripts have proper permissions (chmod +x)
4. Check if the Apache server is properly configured 
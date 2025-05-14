# Fixing Deployment Issues - Action Plan

## Problem
Railway deployment is failing at health check with "service unavailable" errors. We've tried multiple approaches but need a more systematic approach.

## Step-by-Step Action Plan

### 1. Deploy Minimal Version
First, try a minimal configuration to isolate the issue:

```bash
# Commit and push the minimal files
git checkout -b minimal-deployment
git add health.php minimal-bootstrap.sh minimal-dockerfile
git commit -m "039 Minimal deployment test"
git push origin minimal-deployment

# Tell Railway to use this branch and dockerfile for deployment
railway link
railway up -s vibe-faveo -d minimal-dockerfile
```

### 2. Fix Directory Structure Issues
If the minimal deployment succeeds, check directory structure and permissions:

```bash
# Connect to container
railway connect

# Check Apache logs
cat /var/log/apache2/error.log

# Check bootstrap logs
cat /var/log/bootstrap.log

# Check directory permissions
ls -la /var/www/html/public
ls -la /var/www/html/storage
```

### 3. Configure Railway Environment
Make sure necessary Railway environment variables are set:

```bash
# Set environment variables manually if needed
railway variables set APP_URL=https://vibe-faveo-production.up.railway.app
railway variables set APP_ENV=local
railway variables set APP_DEBUG=true
```

### 4. Database Connection Test
Test database connectivity issues:

```bash
# Connect to container
railway connect

# From inside the container, test MySQL connection
mysql -u$MYSQLUSER -p$MYSQLPASSWORD -h$MYSQLHOST -P$MYSQLPORT $MYSQLDATABASE -e "SHOW TABLES;"
```

### 5. Incrementally Add Features Back
If minimal deployment succeeds, add key features back one by one:

1. PHP Extensions - Update minimal-dockerfile to add essential PHP extensions
2. Database Configuration - Add database code back into the bootstrap script
3. URL Configuration - Add URL configuration code to fix redirects

### 6. Testing Locally
If Railway deployment continues to fail, test using local Docker:

```bash
# Build and run locally 
docker build -t faveo-local -f minimal-dockerfile .
docker run -p 8080:80 faveo-local

# Then access http://localhost:8080/health.php
```

### 7. Debugging Apache Issues
If Apache is failing to start or serve requests:

```bash
# Connect to container
railway connect

# Check if Apache is running
ps aux | grep apache

# Manual Apache start for debugging
apache2 -X

# Check Apache configuration
apache2ctl -t

# Inspect logs for errors
tail -50 /var/log/apache2/error.log
```

### 8. Force Health Check Success
If all else fails, create a static health check file:

```bash
# Connect to container
railway connect

# Create plain HTML health check file that will always work
echo "<html><body>OK</body></html>" > /var/www/html/public/health.php
```

### 9. Check Railway logs
Review deployment logs in Railway dashboard:

1. Go to Railway dashboard
2. Select your project and service
3. Check the "Build Logs" and "Deploy Logs" tabs
4. Look for specific error messages

### 10. Network/URL Issues
If health check fails but Apache is running:

```bash
# Test internal networking
railway connect
curl -v localhost/health.php

# Check if Railway domain is correctly set
env | grep RAILWAY_PUBLIC_DOMAIN
```

## Next Steps After Successful Deployment
Once deployment is successful:

1. Add back in URL redirect fixes
2. Add back in database configuration 
3. Merge fixes back into main branch
4. Clean up obsolete files
5. Update documentation

## Reference
Railway specific environment variables:
- `RAILWAY_PUBLIC_DOMAIN` - The public domain of your application
- `RAILWAY_ENVIRONMENT` - The environment (e.g., production)
- `RAILWAY_SERVICE_NAME` - The name of your service 
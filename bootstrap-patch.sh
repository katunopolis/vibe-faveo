#!/bin/bash
# URL Fix section to add to bootstrap.sh right before apache2-foreground

# Detect correct application URL
detect_application_url() {
  # Use APP_URL environment variable if set
  if [ -n "$APP_URL" ]; then
    # Remove trailing slash and port if present
    APP_URL=$(echo "$APP_URL" | sed 's/:[0-9]*$//' | sed 's/\/$//')
    echo "$APP_URL"
    return
  fi
  
  # Default to Railway production URL if all else fails
  echo "https://vibe-faveo-production.up.railway.app"
}

# Fix URL redirection issues in Faveo
fix_url_redirect() {
  # Get the correct application URL
  CORRECT_URL=$(detect_application_url)
  echo "Using application URL: $CORRECT_URL"
  
  # 1. Update database settings
  echo "Updating database URL settings..."
  
  # Try to update settings_system table
  mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" -h"$DB_HOST" -P"$DB_PORT" "$DB_DATABASE" -e "UPDATE settings_system SET url = '$CORRECT_URL';" || {
    echo "WARNING: Could not update settings_system table"
  }
  
  # 2. Update .env file with correct APP_URL
  echo "Updating .env file with correct URL..."
  if [ -f /var/www/html/.env ]; then
    # Check if APP_URL exists in .env
    if grep -q "APP_URL=" /var/www/html/.env; then
      # Update existing APP_URL
      sed -i "s|APP_URL=.*|APP_URL=$CORRECT_URL|g" /var/www/html/.env || {
        echo "WARNING: Could not update APP_URL in .env file"
      }
    else
      # Add APP_URL if it doesn't exist
      echo "APP_URL=$CORRECT_URL" >> /var/www/html/.env || {
        echo "WARNING: Could not add APP_URL to .env file"
      }
    fi
  fi
  
  # 3. Create config override file with correct URL
  echo "Creating URL config override..."
  mkdir -p /var/www/html/config
  cat > /var/www/html/config/override-url.php << EOF
<?php
// URL override for app config
return ['url' => '$CORRECT_URL'];
EOF
  
  # 4. Create or update db_bootstrap.php to include URL override
  echo "Updating bootstrap file with URL override..."
  BOOTSTRAP_FILE="/var/www/html/public/db_bootstrap.php"
  if [ -f "$BOOTSTRAP_FILE" ]; then
    # Check if APP_URL already exists in bootstrap file
    if grep -q "APP_URL" "$BOOTSTRAP_FILE"; then
      # Update existing APP_URL
      sed -i "s|\\\$_ENV\\['APP_URL'\\]\\s*=.*|\\\$_ENV\\['APP_URL'\\] = '$CORRECT_URL';|g" "$BOOTSTRAP_FILE"
      sed -i "s|putenv('APP_URL=.*')|putenv('APP_URL=$CORRECT_URL')|g" "$BOOTSTRAP_FILE"
    else
      # Add APP_URL if it doesn't exist
      echo "
// Override APP_URL for proper redirects
\$_ENV['APP_URL'] = '$CORRECT_URL';
putenv('APP_URL=$CORRECT_URL');
" >> "$BOOTSTRAP_FILE"
    fi
  else
    # Create a new bootstrap file with URL configuration
    cat > "$BOOTSTRAP_FILE" << EOF
<?php
// Set URL and prevent redirects
\$_ENV['APP_URL'] = '$CORRECT_URL';
putenv('APP_URL=$CORRECT_URL');
EOF
  fi
  
  # 5. Patch the index.php file to include the bootstrap file
  echo "Patching index.php to include bootstrap file..."
  INDEX_FILE="/var/www/html/public/index.php"
  if [ -f "$INDEX_FILE" ]; then
    # Make a backup if it doesn't exist
    if [ ! -f "${INDEX_FILE}.bak" ]; then
      cp "$INDEX_FILE" "${INDEX_FILE}.bak"
    fi
    
    # Check if bootstrap include already exists
    if ! grep -q "db_bootstrap.php" "$INDEX_FILE"; then
      # Add bootstrap include at the beginning of index.php
      sed -i '1s/^/<?php require_once __DIR__ . "\/db_bootstrap.php"; ?>\n/' "$INDEX_FILE"
    fi
  fi
  
  # 6. Clear Laravel caches
  echo "Clearing Laravel caches..."
  find /var/www/html/bootstrap/cache -name "*.php" -type f -delete 2>/dev/null || true
  find /var/www/html/storage/framework/cache/data -type f -delete 2>/dev/null || true
  find /var/www/html/storage/framework/views -name "*.php" -type f -delete 2>/dev/null || true
  
  echo "URL redirect fix completed"
}

# Call the function to fix URL redirects
echo "Applying URL redirect fix..."
fix_url_redirect

# Add this right before apache2-foreground in bootstrap.sh 
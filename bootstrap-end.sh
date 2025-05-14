# This should replace just the end portion of bootstrap.sh

# Fix URL redirects
echo "Fixing URL redirect issues..."
# Source the URL fix script
if [ -f "/usr/local/bin/permanent-url-fix.sh" ]; then
  source /usr/local/bin/permanent-url-fix.sh
  fix_url_redirect
else
  echo "WARNING: permanent-url-fix.sh not found, skipping URL fix"
fi

echo "Starting Apache..."
apache2-foreground 
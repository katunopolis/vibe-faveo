#!/bin/bash
# INSTRUCTIONS:
# Add the following code to vibe-faveo/bootstrap.sh
# right before the line "echo "Starting Apache...""

# Fix URL redirects
echo "Fixing URL redirect issues..."
source /usr/local/bin/permanent-url-fix.sh
fix_url_redirect

# Then continue with the original code:
# echo "Starting Apache..."
# apache2-foreground 
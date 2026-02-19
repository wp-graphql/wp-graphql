#!/usr/bin/env bash

# Script to install ACF Free/Pro and optionally ACF Extended (only with ACF Pro).
# Usage:
#   ./bin/install-acf.sh                    # Install ACF Free only (no ACF Extended)
#   ./bin/install-acf.sh --pro              # Install ACF Pro + ACF Extended Free
#   ./bin/install-acf.sh --pro --extended-pro # Install ACF Pro + ACF Extended Pro (requires ACF_LICENSE_KEY and ACF_EXTENDED_LICENSE_KEY)
#
# Note: ACF Extended is only installed when using ACF Pro. With ACF Free, ACF Extended is not installed.
#
# License keys can be provided via:
#   - Environment variables: export ACF_LICENSE_KEY=your_key
#   - .env file: ACF_LICENSE_KEY=your_key (in plugins/wp-graphql-acf/.env)
#
# CI/matrix (env, no CLI args):
#   INSTALL_ACF_PRO=false, INSTALL_ACF_EXTENDED_PRO=false - ACF Free only
#   INSTALL_ACF_PRO=true,  INSTALL_ACF_EXTENDED_PRO=false - ACF Pro + ACF Extended Free
#   INSTALL_ACF_PRO=true,  INSTALL_ACF_EXTENDED_PRO=true  - ACF Pro + ACF Extended Pro (requires ACF_EXTENDED_LICENSE_KEY)

set -e

# Get the script directory to find .env file relative to script location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Load .env file if it exists (allows users to store license keys in .env)
# Check both in plugin directory and current directory
ENV_FILE=""
if [ -f "$PLUGIN_DIR/.env" ] && [ -r "$PLUGIN_DIR/.env" ]; then
  ENV_FILE="$PLUGIN_DIR/.env"
elif [ -f .env ] && [ -r .env ]; then
  ENV_FILE=".env"
fi

if [ -n "$ENV_FILE" ]; then
  set -a  # automatically export all variables
  source "$ENV_FILE"
  set +a
  echo "Loaded environment variables from $ENV_FILE"
fi

ACF_PRO=false
ACF_EXTENDED_PRO=false
ACF_VERSION=""

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    --pro)
      ACF_PRO=true
      shift
      ;;
    --extended-pro)
      ACF_EXTENDED_PRO=true
      shift
      ;;
    --version)
      ACF_VERSION="$2"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      echo "Usage: $0 [--pro] [--extended-pro] [--version VERSION]"
      exit 1
      ;;
  esac
done

# Allow CI/workflow to set variant via env (INSTALL_ACF_PRO, INSTALL_ACF_EXTENDED_PRO).
# ACF Extended is only installed when ACF Pro is used.
if [ "${INSTALL_ACF_PRO}" = "true" ]; then
  ACF_PRO=true
fi
if [ "${INSTALL_ACF_EXTENDED_PRO}" = "true" ]; then
  ACF_EXTENDED_PRO=true
fi

echo "Installing ACF plugins for local testing..."

# Install ACF Free or Pro
if [ "$ACF_PRO" == "true" ]; then
  if [ -z "$ACF_LICENSE_KEY" ]; then
    echo "❌ Error: ACF_LICENSE_KEY is required for ACF Pro"
    echo "   Set it via:"
    echo "     - Environment variable: export ACF_LICENSE_KEY=your_license_key"
    echo "     - .env file: ACF_LICENSE_KEY=your_license_key (in plugins/wp-graphql-acf/.env)"
    exit 1
  fi
  
  echo "Installing ACF Pro..."
  ACF_VERSION_PARAM=""
  if [ -n "$ACF_VERSION" ]; then
    ACF_VERSION_PARAM="&t=$ACF_VERSION"
  fi
  
  # Build the download URL
  DOWNLOAD_URL="https://connect.advancedcustomfields.com/v2/plugins/download?p=pro&k=${ACF_LICENSE_KEY}${ACF_VERSION_PARAM}"
  
  # Try to install ACF Pro
  # Note: We use a temp file approach to better handle errors
  echo "Downloading ACF Pro from ACF servers..."
  TEMP_FILE="/tmp/acf-pro-$$.zip"
  
  # Download to temp file first to verify it's a valid zip
  # Download the file (suppress npm output to stderr, capture curl errors)
  echo "  Downloading..."
  npm run --prefix ../.. wp-env run tests-cli -- bash -c "curl -L -f -s -o '${TEMP_FILE}' --max-time 30 '${DOWNLOAD_URL}'" >/dev/null 2>&1
  CURL_EXIT=$?
  
  if [ $CURL_EXIT -ne 0 ]; then
    echo "" >&2
    echo "❌ Error: Failed to download ACF Pro (curl exit code: $CURL_EXIT)" >&2
    echo "" >&2
    echo "This usually means:" >&2
    echo "     - The license key is invalid or expired" >&2
    echo "     - The license key doesn't have developer access" >&2
    echo "     - There's a network issue connecting to ACF servers" >&2
    echo "" >&2
    exit 1
  fi
  
  # Verify it's a valid zip file
  echo "  Verifying download..."
  FILE_TYPE=$(npm run --prefix ../.. wp-env run tests-cli -- bash -c "file '${TEMP_FILE}' 2>/dev/null" 2>/dev/null | grep -o 'Zip archive' || echo "")
  
  if [ -z "$FILE_TYPE" ]; then
    echo "" >&2
    echo "❌ Error: Downloaded file is not a valid zip archive" >&2
    echo "" >&2
    
    # Check what was actually downloaded (first few lines to see if it's HTML)
    FIRST_LINES=$(npm run --prefix ../.. wp-env run tests-cli -- bash -c "head -5 '${TEMP_FILE}' 2>/dev/null | head -1" 2>/dev/null || echo "")
    if echo "$FIRST_LINES" | grep -q "<!DOCTYPE\|<html"; then
      echo "The download returned an HTML error page instead of a zip file." >&2
      echo "This usually means the license key is invalid or expired." >&2
    fi
    echo "" >&2
    
    echo "This usually means:" >&2
    echo "     - The license key is invalid or expired" >&2
    echo "     - The license key doesn't have developer access" >&2
    echo "     - The download URL returned an error page (HTML) instead of a zip file" >&2
    echo "" >&2
    echo "You can try:" >&2
    echo "     1. Verify your license key is correct and has developer access" >&2
    echo "     2. Use ACF Free instead: npm run install-acf" >&2
    echo "     3. Check your internet connection" >&2
    exit 1
  fi
  
  # Check if ACF Pro is already installed and remove it first
  echo "Checking for existing ACF Pro installation..."
  if npm run --prefix ../.. wp-env run tests-cli -- wp plugin is-installed advanced-custom-fields-pro --allow-root 2>/dev/null; then
    echo "  Removing existing ACF Pro installation..."
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin uninstall advanced-custom-fields-pro --deactivate --allow-root 2>/dev/null || true
    # Also remove the directory if it still exists
    npm run --prefix ../.. wp-env run tests-cli -- rm -rf /var/www/html/wp-content/plugins/advanced-custom-fields-pro 2>/dev/null || true
  fi
  
  # Check if ACF Pro is already installed and remove it first
  echo "Checking for existing ACF Pro installation..."
  if npm run --prefix ../.. wp-env run tests-cli -- wp plugin is-installed advanced-custom-fields-pro --allow-root 2>/dev/null; then
    echo "  Removing existing ACF Pro installation..."
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin uninstall advanced-custom-fields-pro --deactivate --allow-root 2>/dev/null || true
    # Also remove the directory if it still exists
    npm run --prefix ../.. wp-env run tests-cli -- rm -rf /var/www/html/wp-content/plugins/advanced-custom-fields-pro 2>/dev/null || true
  fi
  
  # Install from the temp file
  echo "Installing ACF Pro from downloaded file..."
  INSTALL_OUTPUT=$(npm run --prefix ../.. wp-env run tests-cli -- wp plugin install "${TEMP_FILE}" --activate --force --allow-root 2>&1) || {
    INSTALL_EXIT_CODE=$?
    echo "" >&2
    echo "⚠️  Installation from temp file failed (exit code: $INSTALL_EXIT_CODE)" >&2
    echo "   Trying direct installation from URL..." >&2
    echo "" >&2
    
    # Try installing directly from URL as fallback (this sometimes works better)
    INSTALL_OUTPUT2=$(npm run --prefix ../.. wp-env run tests-cli -- wp plugin install "${DOWNLOAD_URL}" --activate --force --allow-root 2>&1) || {
      INSTALL_EXIT_CODE2=$?
      echo "" >&2
      echo "❌ Error: Both installation methods failed" >&2
      echo "" >&2
      echo "First attempt output:" >&2
      echo "$INSTALL_OUTPUT" >&2
      echo "" >&2
      echo "Second attempt output:" >&2
      echo "$INSTALL_OUTPUT2" >&2
      exit 1
    }
    echo "✅ Installation succeeded using direct URL method" >&2
  }
  
  # Clean up temp file
  npm run --prefix ../.. wp-env run tests-cli -- rm -f "${TEMP_FILE}" 2>/dev/null || true
  
  # Verify the plugin was actually installed
  if ! npm run --prefix ../.. wp-env run tests-cli -- wp plugin is-installed advanced-custom-fields-pro --allow-root 2>/dev/null; then
    echo "" >&2
    echo "❌ Error: ACF Pro installation appeared to succeed but plugin is not installed" >&2
    echo "   The download URL may have returned an invalid file" >&2
    exit 1
  fi

  # Activate the license in WordPress so the "Activate your license" notice doesn't show (uses ACF's API).
  echo "Activating ACF Pro license..."
  if npm run --prefix ../.. wp-env run tests-cli -- wp eval 'if ( function_exists( "acf_pro_activate_license" ) ) { acf_pro_activate_license( "'"$ACF_LICENSE_KEY"'" ); } elseif ( function_exists( "acf_pro_update_license" ) ) { acf_pro_update_license( "'"$ACF_LICENSE_KEY"'" ); }' 2>/dev/null; then
    echo "  License activated."
  else
    echo "  Note: License activation skipped (ACF may require network). You can activate in ACF > Updates if the notice appears."
  fi

  ACF_PLUGIN_SLUG="advanced-custom-fields-pro/acf.php"
else
  echo "Installing ACF Free..."
  if [ -n "$ACF_VERSION" ]; then
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin install advanced-custom-fields --version=$ACF_VERSION --activate --allow-root
  else
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin install advanced-custom-fields --activate --allow-root
  fi
  ACF_PLUGIN_SLUG="advanced-custom-fields/acf.php"
  # When ACF Free: ensure ACF Extended is not present (clean state; Extended only works with ACF Pro)
  echo "Ensuring ACF Extended is not active (ACF Free mode)..."
  npm run --prefix ../.. wp-env run tests-cli -- wp plugin deactivate acf-extended acf-extended-pro --allow-root 2>/dev/null || true
  npm run --prefix ../.. wp-env run tests-cli -- wp plugin uninstall acf-extended acf-extended-pro --allow-root 2>/dev/null || true
fi

# Install ACF Extended (only when ACF Pro is installed; not used with ACF Free)
ACF_EXTENDED_PLUGIN_SLUG=""
if [ "$ACF_PRO" == "true" ]; then
  # When ACF Pro: install Extended Pro (if flag + key) or Extended Free
  if [ "$ACF_EXTENDED_PRO" == "true" ] && [ -n "$ACF_EXTENDED_LICENSE_KEY" ]; then
    echo "Installing ACF Extended Pro..."
    # Make a request to the Easy Digital Downloads endpoint for ACF Extended to get the download link
    # See: https://gist.github.com/acf-extended/b65882979cdf7c4f5e6a0e5ed733aca7#file-acfe-pro-api-download-postman_collection-json
    # Do everything in one command inside the container to avoid variable expansion issues
    # Use PHP to parse JSON (PHP is available in WordPress containers, similar to jq in the old script)
    INSTALL_RESULT=$(npm run --prefix ../.. wp-env run tests-cli -- bash -c "
      download_link=\$(curl -s --location --request GET 'https://acf-extended.com?edd_action=get_version&license=${ACF_EXTENDED_LICENSE_KEY}&item_name=ACF%20Extended%20Pro&url=https://acf.wpgraphql.com' | php -r 'echo json_decode(file_get_contents(\"php://stdin\"), true)[\"download_link\"] ?? \"\";');
      download_link=\${download_link%\\\"};
      download_link=\${download_link#\\\"};
      if [ -z \"\$download_link\" ] || [ \"\$download_link\" == \"null\" ] || [ \"\$download_link\" == \"false\" ]; then
        echo 'ERROR: Invalid license key or download link';
        exit 1;
      fi;
      wp plugin install \"\$download_link\" --activate --quiet --allow-root;
      echo 'SUCCESS'
    " 2>&1)
    
    # Check if installation succeeded
    if echo "$INSTALL_RESULT" | grep -q "SUCCESS"; then
      ACF_EXTENDED_PLUGIN_SLUG="acf-extended-pro/acf-extended.php"
    elif echo "$INSTALL_RESULT" | grep -q "ERROR: Invalid license key"; then
      echo "⚠️  Warning: ACF Extended Pro license key appears invalid or expired"
      echo "   Falling back to ACF Extended Free..."
      echo ""
      npm run --prefix ../.. wp-env run tests-cli -- wp plugin install acf-extended --activate --allow-root
      ACF_EXTENDED_PLUGIN_SLUG="acf-extended/acf-extended.php"
    else
      echo "⚠️  Warning: ACF Extended Pro installation failed"
      echo "   Output: $INSTALL_RESULT"
      echo "   Falling back to ACF Extended Free..."
      echo ""
      npm run --prefix ../.. wp-env run tests-cli -- wp plugin install acf-extended --activate --allow-root
      ACF_EXTENDED_PLUGIN_SLUG="acf-extended/acf-extended.php"
    fi
  else
    echo "Installing ACF Extended Free (with ACF Pro)..."
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin install acf-extended --activate --allow-root
    ACF_EXTENDED_PLUGIN_SLUG="acf-extended/acf-extended.php"
  fi
fi

# Ensure WPGraphQL for ACF is active (E2E tests require it)
echo "Activating WPGraphQL for ACF..."
npm run --prefix ../.. wp-env run tests-cli -- wp plugin activate wp-graphql-acf --allow-root 2>/dev/null || true

echo ""
echo "✅ ACF plugins installed successfully!"
echo "   ACF Plugin: $ACF_PLUGIN_SLUG"
echo "   ACF Extended: ${ACF_EXTENDED_PLUGIN_SLUG:-(not installed)}"
echo ""
echo "💡 Tip: You can store license keys in a .env file:"
echo "   ACF_LICENSE_KEY=your_key"
echo "   ACF_EXTENDED_LICENSE_KEY=your_key"
echo ""
echo "You can now run tests with:"
echo "  npm run -w @wpgraphql/wp-graphql-acf test:codecept:wpunit"

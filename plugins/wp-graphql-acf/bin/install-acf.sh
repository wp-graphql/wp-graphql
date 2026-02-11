#!/usr/bin/env bash

# Script to install ACF Free/Pro and ACF Extended for local testing
# Usage:
#   ./bin/install-acf.sh                    # Install ACF Free + ACF Extended Free
#   ./bin/install-acf.sh --pro              # Install ACF Pro + ACF Extended Free (requires ACF_LICENSE_KEY)
#   ./bin/install-acf.sh --pro --extended-pro # Install ACF Pro + ACF Extended Pro (requires both license keys)
#
# License keys can be provided via:
#   - Environment variables: export ACF_LICENSE_KEY=your_key
#   - .env file: ACF_LICENSE_KEY=your_key (in plugins/wp-graphql-acf/.env)

set -e

# Load .env file if it exists (allows users to store license keys in .env)
if [ -f .env ]; then
  set -a  # automatically export all variables
  source .env
  set +a
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
  
  npm run --prefix ../.. wp-env run tests-cli -- wp plugin install "https://connect.advancedcustomfields.com/v2/plugins/download?p=pro&k=${ACF_LICENSE_KEY}${ACF_VERSION_PARAM}" --activate --quiet --allow-root
  ACF_PLUGIN_SLUG="advanced-custom-fields-pro/acf.php"
else
  echo "Installing ACF Free..."
  if [ -n "$ACF_VERSION" ]; then
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin install advanced-custom-fields --version=$ACF_VERSION --activate --allow-root
  else
    npm run --prefix ../.. wp-env run tests-cli -- wp plugin install advanced-custom-fields --activate --allow-root
  fi
  ACF_PLUGIN_SLUG="advanced-custom-fields/acf.php"
fi

# Install ACF Extended
if [ "$ACF_EXTENDED_PRO" == "true" ]; then
  if [ -z "$ACF_EXTENDED_LICENSE_KEY" ]; then
    echo "❌ Error: ACF_EXTENDED_LICENSE_KEY is required for ACF Extended Pro"
    echo "   Set it via:"
    echo "     - Environment variable: export ACF_EXTENDED_LICENSE_KEY=your_license_key"
    echo "     - .env file: ACF_EXTENDED_LICENSE_KEY=your_license_key (in plugins/wp-graphql-acf/.env)"
    exit 1
  fi
  
  echo "Installing ACF Extended Pro..."
  # Install jq if not available (needed for JSON parsing)
  npm run --prefix ../.. wp-env run tests-cli -- bash -c 'apt-get update -qq && apt-get install -y -qq jq > /dev/null 2>&1 || true'
  
  DOWNLOAD_LINK=$(npm run --prefix ../.. wp-env run tests-cli -- bash -c "curl -s --location --request GET 'https://acf-extended.com?edd_action=get_version&license=${ACF_EXTENDED_LICENSE_KEY}&item_name=ACF%20Extended%20Pro&url=https://acf.wpgraphql.com' | jq -r '.download_link'")
  npm run --prefix ../.. wp-env run tests-cli -- wp plugin install "${DOWNLOAD_LINK}" --activate --quiet --allow-root
  ACF_EXTENDED_PLUGIN_SLUG="acf-extended-pro/acf-extended.php"
else
  echo "Installing ACF Extended Free..."
  npm run --prefix ../.. wp-env run tests-cli -- wp plugin install acf-extended --activate --allow-root
  ACF_EXTENDED_PLUGIN_SLUG="acf-extended/acf-extended.php"
fi

echo ""
echo "✅ ACF plugins installed successfully!"
echo "   ACF Plugin: $ACF_PLUGIN_SLUG"
echo "   ACF Extended: $ACF_EXTENDED_PLUGIN_SLUG"
echo ""
echo "💡 Tip: You can store license keys in a .env file:"
echo "   ACF_LICENSE_KEY=your_key"
echo "   ACF_EXTENDED_LICENSE_KEY=your_key"
echo ""
echo "You can now run tests with:"
echo "  npm run -w @wpgraphql/wp-graphql-acf test:codecept:wpunit"

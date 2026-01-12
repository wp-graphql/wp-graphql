#!/bin/bash
# ===========================================
# WPGraphQL Smoke Test Script
# ===========================================
#
# Runs basic smoke tests against a running WordPress
# environment to verify the WPGraphQL plugin works.
#
# Prerequisites:
#   - wp-env must be running
#   - WPGraphQL plugin must be installed and active
#
# Usage:
#   ./bin/smoke-test.sh
#
# Options:
#   --endpoint URL    GraphQL endpoint URL (default: http://localhost:8888/graphql)
#   --verbose         Show full responses
#   --help            Show this help message
#
# ===========================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
# wp-env exposes WordPress on port 8888 by default
ENDPOINT="http://localhost:8888/graphql"
VERBOSE=false
PASSED=0
FAILED=0

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --endpoint)
            ENDPOINT="$2"
            shift 2
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help)
            head -25 "$0" | tail -20
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# ===========================================
# Helper Functions
# ===========================================

log_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

log_success() {
    echo -e "${GREEN}✅${NC} $1"
    PASSED=$((PASSED + 1))
}

log_error() {
    echo -e "${RED}❌${NC} $1"
    FAILED=$((FAILED + 1))
}

log_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Run a GraphQL query and check for expected content
# Usage: run_test "Test Name" "GraphQL Query" "Expected string in response"
run_test() {
    local test_name="$1"
    local query="$2"
    local expected="$3"
    
    echo -e "\n${BLUE}🔍 Testing:${NC} $test_name"
    
    # Run the query
    local response
    response=$(curl -s -X POST "$ENDPOINT" \
        -H "Content-Type: application/json" \
        -d "{\"query\":\"$query\"}" 2>&1)
    
    if [ "$VERBOSE" = true ]; then
        echo "   Response: $response"
    fi
    
    # Check for errors in response
    if echo "$response" | grep -q '"errors"'; then
        log_error "$test_name - GraphQL returned errors"
        if [ "$VERBOSE" = false ]; then
            echo "   Response: $response"
        fi
        return 1
    fi
    
    # Check for expected content
    if echo "$response" | grep -q "$expected"; then
        log_success "$test_name"
        return 0
    else
        log_error "$test_name - Expected '$expected' not found in response"
        if [ "$VERBOSE" = false ]; then
            echo "   Response: $response"
        fi
        return 1
    fi
}

# ===========================================
# Smoke Tests
# ===========================================

echo ""
echo "========================================"
echo "  WPGraphQL Smoke Tests"
echo "========================================"
echo ""
log_info "Endpoint: $ENDPOINT"
echo ""

# Test 1: Basic connectivity
run_test "GraphQL endpoint responds" \
    "{ __typename }" \
    '"data"'

# Test 2: Introspection works
run_test "Introspection query" \
    "{ __schema { queryType { name } } }" \
    '"queryType"'

# Test 3: Posts query
run_test "Posts query" \
    "{ posts { nodes { id title } } }" \
    '"nodes"'

# Test 4: Pages query  
run_test "Pages query" \
    "{ pages { nodes { id title } } }" \
    '"nodes"'

# Test 5: Users query
run_test "Users query" \
    "{ users { nodes { id name } } }" \
    '"nodes"'

# Test 6: General settings
run_test "GeneralSettings query" \
    "{ generalSettings { title url } }" \
    '"generalSettings"'

# Test 7: Content types
run_test "ContentTypes query" \
    "{ contentTypes { nodes { name } } }" \
    '"contentTypes"'

# Test 8: Taxonomies
run_test "Taxonomies query" \
    "{ taxonomies { nodes { name } } }" \
    '"taxonomies"'

# Test 9: Menus (should return empty but not error)
run_test "Menus query" \
    "{ menus { nodes { id name } } }" \
    '"menus"'

# Test 10: Media items
run_test "MediaItems query" \
    "{ mediaItems { nodes { id title } } }" \
    '"mediaItems"'

# ===========================================
# Summary
# ===========================================

echo ""
echo "========================================"
echo "  Results"
echo "========================================"
echo ""
echo -e "  ${GREEN}Passed:${NC} $PASSED"
echo -e "  ${RED}Failed:${NC} $FAILED"
echo ""

if [ $FAILED -gt 0 ]; then
    log_error "Some smoke tests failed!"
    exit 1
else
    log_success "All smoke tests passed!"
    exit 0
fi

#!/bin/bash

# Test script for VSLA Onboarding Data Fetching Endpoints
# Usage: ./test_data_endpoints.sh <JWT_TOKEN>

BASE_URL="https://fao.marksolutions.co.ug/api"
TOKEN="$1"

if [ -z "$TOKEN" ]; then
    echo "❌ Error: JWT token required"
    echo "Usage: ./test_data_endpoints.sh <JWT_TOKEN>"
    exit 1
fi

echo "🧪 Testing VSLA Onboarding Data Fetching Endpoints"
echo "=================================================="
echo ""

# Test 1: Chairperson Data
echo "📋 Test 1: Fetching Chairperson Data"
echo "GET /api/vsla-onboarding/data/chairperson"
curl -s -X GET "${BASE_URL}/vsla-onboarding/data/chairperson" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" | jq '.'
echo ""
echo "---"
echo ""

# Test 2: Group Data
echo "📋 Test 2: Fetching Group Data"
echo "GET /api/vsla-onboarding/data/group"
curl -s -X GET "${BASE_URL}/vsla-onboarding/data/group" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" | jq '.'
echo ""
echo "---"
echo ""

# Test 3: Main Members Data
echo "📋 Test 3: Fetching Main Members Data (Secretary & Treasurer)"
echo "GET /api/vsla-onboarding/data/main-members"
curl -s -X GET "${BASE_URL}/vsla-onboarding/data/main-members" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" | jq '.'
echo ""
echo "---"
echo ""

# Test 4: Savings Cycle Data
echo "📋 Test 4: Fetching Savings Cycle Data"
echo "GET /api/vsla-onboarding/data/savings-cycle"
curl -s -X GET "${BASE_URL}/vsla-onboarding/data/savings-cycle" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" | jq '.'
echo ""
echo "---"
echo ""

# Test 5: All Onboarding Data
echo "📋 Test 5: Fetching All Onboarding Data"
echo "GET /api/vsla-onboarding/data/all"
curl -s -X GET "${BASE_URL}/vsla-onboarding/data/all" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" | jq '.'
echo ""
echo "---"
echo ""

echo "✅ All tests completed!"
echo ""
echo "Expected Results:"
echo "- status: 1 (success) or 0 (error)"
echo "- message: descriptive message"
echo "- data: object with relevant data or null if not found"

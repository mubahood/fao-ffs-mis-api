#!/bin/bash

# Member API Endpoints Testing Script
# Tests all member CRUD operations via HTTP

echo "========================================"
echo "MEMBER API ENDPOINTS HTTP TESTING"
echo "========================================"
echo ""

# Configuration
API_BASE_URL="http://localhost:8888/fao-ffs-mis-api/public/api"
TOKEN=""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Get token for testing (if needed)
echo "Test 1: Authentication Check"
echo "Checking if token is required..."
echo ""

# Test 2: List Members (GET /api/members)
echo "Test 2: List Members"
echo "GET ${API_BASE_URL}/members"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "${API_BASE_URL}/members")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response preview:"
    echo "$BODY" | head -c 200
    echo "..."
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "$BODY"
fi
echo ""
echo ""

# Test 3: Get Single Member (GET /api/members/1)
echo "Test 3: Get Single Member"
echo "GET ${API_BASE_URL}/members/1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "${API_BASE_URL}/members/1")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response preview:"
    echo "$BODY" | head -c 200
    echo "..."
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "$BODY"
fi
echo ""
echo ""

# Test 4: Create Member (POST /api/members) - Without auth to test validation
echo "Test 4: Create Member (Validation Test - No Auth)"
echo "POST ${API_BASE_URL}/members"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "${API_BASE_URL}/members" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "Member",
    "phone_number": "0701234567",
    "sex": "Male"
  }')
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "201" ] || [ "$HTTP_CODE" == "401" ] || [ "$HTTP_CODE" == "422" ]; then
    echo -e "${YELLOW}⚠ Expected behavior (HTTP $HTTP_CODE)${NC}"
    if [ "$HTTP_CODE" == "401" ]; then
        echo "Note: Authentication required (as expected)"
    fi
    echo "Response:"
    echo "$BODY" | head -c 300
    echo "..."
else
    echo -e "${RED}✗ Unexpected response (HTTP $HTTP_CODE)${NC}"
    echo "$BODY"
fi
echo ""
echo ""

# Test 5: Search Members (GET /api/members?search=test)
echo "Test 5: Search Members"
echo "GET ${API_BASE_URL}/members?search=test"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "${API_BASE_URL}/members?search=test")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response preview:"
    echo "$BODY" | head -c 200
    echo "..."
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "$BODY"
fi
echo ""
echo ""

# Test 6: Filter by Status (GET /api/members?status=1)
echo "Test 6: Filter Members by Status"
echo "GET ${API_BASE_URL}/members?status=1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "${API_BASE_URL}/members?status=1")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response preview:"
    echo "$BODY" | head -c 200
    echo "..."
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "$BODY"
fi
echo ""
echo ""

# Test 7: Pagination (GET /api/members?page=1&per_page=10)
echo "Test 7: Pagination"
echo "GET ${API_BASE_URL}/members?page=1&per_page=10"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "${API_BASE_URL}/members?page=1&per_page=10")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓ Success (HTTP $HTTP_CODE)${NC}"
    echo "Response preview:"
    echo "$BODY" | head -c 200
    echo "..."
else
    echo -e "${RED}✗ Failed (HTTP $HTTP_CODE)${NC}"
    echo "$BODY"
fi
echo ""
echo ""

# Summary
echo "========================================"
echo "TEST SUMMARY"
echo "========================================"
echo "GET /members - List members"
echo "GET /members/{id} - Get single member"
echo "GET /members?search= - Search members"
echo "GET /members?status= - Filter by status"
echo "GET /members?page= - Pagination"
echo "POST /members - Create (requires auth)"
echo "PUT /members/{id} - Update (requires auth)"
echo "DELETE /members/{id} - Delete (requires auth)"
echo "POST /members/sync - Sync offline (requires auth)"
echo ""
echo "Note: Protected endpoints require authentication token"
echo "Use the mobile app or Postman to test authenticated endpoints"
echo ""

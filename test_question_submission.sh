#!/bin/bash

# Question Submission System Test Script
# Tests the complete flow of posting a farming question

echo "========================================="
echo "QUESTION SUBMISSION SYSTEM TEST"
echo "========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Function to test endpoint
test_endpoint() {
    local test_name=$1
    local expected_code=$2
    local response=$3
    
    code=$(echo "$response" | jq -r '.code // 0' 2>/dev/null)
    
    if [ "$code" == "$expected_code" ]; then
        echo -e "${GREEN}✓ PASSED${NC}: $test_name"
        ((TESTS_PASSED++))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC}: $test_name"
        echo "  Expected code: $expected_code, Got: $code"
        echo "  Response: $response"
        ((TESTS_FAILED++))
        return 1
    fi
}

# Get base URL
BASE_URL="http://localhost:8888/fao-ffs-mis-api/public/api"
echo "Testing API at: $BASE_URL"
echo ""

# ============================================================================
# TEST 1: Validate database table structure
# ============================================================================
echo -e "${BLUE}TEST 1: Database Table Structure${NC}"
echo "Checking farmer_questions table..."

mysql -h localhost -u root -proot -e "USE fao_ffs_mis; DESCRIBE farmer_questions;" 2>/dev/null > /dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PASSED${NC}: farmer_questions table exists"
    ((TESTS_PASSED++))
    
    # Check required columns
    REQUIRED_COLS=("title" "content" "author_id" "has_image" "image_url" "has_audio" "audio_url" "status")
    for col in "${REQUIRED_COLS[@]}"; do
        mysql -h localhost -u root -proot -e "USE fao_ffs_mis; SHOW COLUMNS FROM farmer_questions LIKE '$col';" 2>/dev/null | grep -q "$col"
        if [ $? -eq 0 ]; then
            echo "  ✓ Column '$col' exists"
        else
            echo -e "  ${RED}✗ Column '$col' missing${NC}"
            ((TESTS_FAILED++))
        fi
    done
else
    echo -e "${RED}✗ FAILED${NC}: farmer_questions table not found"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================================
# TEST 2: Get authentication token
# ============================================================================
echo -e "${BLUE}TEST 2: Authentication${NC}"
echo "Authenticating test user..."

AUTH_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "0700000000",
    "password": "4321"
  }')

TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.data.token // empty' 2>/dev/null)

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ PASSED${NC}: Authentication successful"
    echo "  Token: ${TOKEN:0:20}..."
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗ FAILED${NC}: Authentication failed"
    echo "  Response: $AUTH_RESPONSE"
    ((TESTS_FAILED++))
    echo ""
    echo "Cannot continue without authentication. Exiting..."
    exit 1
fi

echo ""

# ============================================================================
# TEST 3: Post question without authentication (should fail)
# ============================================================================
echo -e "${BLUE}TEST 3: Post Question Without Auth (Expected to Fail)${NC}"

RESPONSE=$(curl -s -X POST "$BASE_URL/advisory/questions" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Question Without Auth",
    "content": "This should fail due to missing authentication"
  }')

test_endpoint "Question without auth rejected" "0" "$RESPONSE"
echo ""

# ============================================================================
# TEST 4: Post question with invalid data
# ============================================================================
echo -e "${BLUE}TEST 4: Validation Tests${NC}"

# Test 4.1: Missing title
RESPONSE=$(curl -s -X POST "$BASE_URL/advisory/questions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "content": "This question has no title and should fail validation"
  }')

test_endpoint "Missing title rejected" "0" "$RESPONSE"

# Test 4.2: Title too short
RESPONSE=$(curl -s -X POST "$BASE_URL/advisory/questions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title": "Short",
    "content": "This title is too short and should fail"
  }')

test_endpoint "Short title rejected" "0" "$RESPONSE"

# Test 4.3: Content too short
RESPONSE=$(curl -s -X POST "$BASE_URL/advisory/questions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title": "Valid title that is long enough",
    "content": "Too short"
  }')

test_endpoint "Short content rejected" "0" "$RESPONSE"

echo ""

# ============================================================================
# TEST 5: Post valid question without media
# ============================================================================
echo -e "${BLUE}TEST 5: Post Valid Question (Text Only)${NC}"

TIMESTAMP=$(date +%s)
RESPONSE=$(curl -s -X POST "$BASE_URL/advisory/questions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"title\": \"How to control tomato blight disease? - Test $TIMESTAMP\",
    \"content\": \"I am experiencing brown spots on my tomato leaves. The spots are spreading quickly and some leaves are dying. What can I do to stop this disease? I am in the central region and this is happening during the rainy season.\"
  }")

if test_endpoint "Valid question posted" "1" "$RESPONSE"; then
    QUESTION_ID=$(echo "$RESPONSE" | jq -r '.data.id // empty' 2>/dev/null)
    echo "  Question ID: $QUESTION_ID"
    
    # Verify question was saved
    if [ -n "$QUESTION_ID" ]; then
        GET_RESPONSE=$(curl -s "$BASE_URL/advisory/questions/$QUESTION_ID")
        GET_CODE=$(echo "$GET_RESPONSE" | jq -r '.code // 0' 2>/dev/null)
        
        if [ "$GET_CODE" == "1" ]; then
            echo -e "  ${GREEN}✓${NC} Question retrievable from database"
            ((TESTS_PASSED++))
            
            # Check fields
            TITLE=$(echo "$GET_RESPONSE" | jq -r '.data.title // empty')
            STATUS=$(echo "$GET_RESPONSE" | jq -r '.data.status // empty')
            echo "  Title: $TITLE"
            echo "  Status: $STATUS"
        else
            echo -e "  ${RED}✗${NC} Question not found in database"
            ((TESTS_FAILED++))
        fi
    fi
fi

echo ""

# ============================================================================
# TEST 6: Get questions list
# ============================================================================
echo -e "${BLUE}TEST 6: Retrieve Questions List${NC}"

RESPONSE=$(curl -s "$BASE_URL/advisory/questions")
test_endpoint "Questions list retrieved" "1" "$RESPONSE"

if [ $? -eq 0 ]; then
    COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
    echo "  Total questions: $COUNT"
fi

echo ""

# ============================================================================
# TEST 7: Get user's questions
# ============================================================================
echo -e "${BLUE}TEST 7: Get User's Questions${NC}"

RESPONSE=$(curl -s "$BASE_URL/advisory/questions/my/list" \
  -H "Authorization: Bearer $TOKEN")

test_endpoint "User's questions retrieved" "1" "$RESPONSE"

if [ $? -eq 0 ]; then
    COUNT=$(echo "$RESPONSE" | jq '.data | length' 2>/dev/null)
    echo "  User's questions: $COUNT"
fi

echo ""

# ============================================================================
# TEST 8: File upload validation
# ============================================================================
echo -e "${BLUE}TEST 8: File Upload Tests${NC}"

# Create a test image file
TEST_IMAGE="/tmp/test_question_image.jpg"
# Create a small test image (1x1 pixel red JPEG)
echo -e '\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xff\xdb\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0a\x0c\x14\x0d\x0c\x0b\x0b\x0c\x19\x12\x13\x0f\x14\x1d\x1a\x1f\x1e\x1d\x1a\x1c\x1c\x20\x24\x2e\x27\x20\x22\x2c\x23\x1c\x1c\x28\x37\x29\x2c\x30\x31\x34\x34\x34\x1f\x27\x39\x3d\x38\x32\x3c\x2e\x33\x34\x32\xff\xc0\x00\x0b\x08\x00\x01\x00\x01\x01\x01\x11\x00\xff\xc4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xc4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xda\x00\x08\x01\x01\x00\x00\x3f\x00\x7f\xff\xd9' > "$TEST_IMAGE"

echo "Testing image upload..."

RESPONSE=$(curl -s -X POST "$BASE_URL/advisory/questions" \
  -H "Authorization: Bearer $TOKEN" \
  -F "title=How to identify pest damage with photo - Test $TIMESTAMP" \
  -F "content=I found this damage on my crops. Please help me identify what pest is causing this and how to control it." \
  -F "image=@$TEST_IMAGE")

test_endpoint "Question with image posted" "1" "$RESPONSE"

if [ $? -eq 0 ]; then
    HAS_IMAGE=$(echo "$RESPONSE" | jq -r '.data.has_image // false' 2>/dev/null)
    IMAGE_URL=$(echo "$RESPONSE" | jq -r '.data.image_url // empty' 2>/dev/null)
    
    if [ "$HAS_IMAGE" == "true" ] || [ "$HAS_IMAGE" == "1" ]; then
        echo -e "  ${GREEN}✓${NC} Image attached successfully"
        echo "  Image URL: $IMAGE_URL"
        ((TESTS_PASSED++))
    else
        echo -e "  ${RED}✗${NC} Image not attached"
        ((TESTS_FAILED++))
    fi
fi

# Cleanup
rm -f "$TEST_IMAGE"

echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo "========================================="
echo "TEST SUMMARY"
echo "========================================="
echo -e "Total Tests: $((TESTS_PASSED + TESTS_FAILED))"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    echo ""
    echo "The question submission system is working correctly:"
    echo "  ✓ Database structure is valid"
    echo "  ✓ Authentication is working"
    echo "  ✓ Validation is enforcing rules"
    echo "  ✓ Questions can be posted"
    echo "  ✓ Questions can be retrieved"
    echo "  ✓ Image uploads are working"
    echo ""
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo "Please review the failed tests above."
    echo ""
    exit 1
fi

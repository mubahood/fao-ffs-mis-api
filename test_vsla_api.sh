#!/bin/bash

# ============================================
# VSLA ONBOARDING API TESTING SCRIPT
# ============================================
# Tests all 7 endpoints of the VSLA onboarding system
# Ensures field names match mobile app expectations
# ============================================

API_BASE="http://localhost:8888/fao-ffs-mis-api/api"
CONTENT_TYPE="Content-Type: application/json"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Function to print test header
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

# Function to print success
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
    ((TESTS_PASSED++))
}

# Function to print error
print_error() {
    echo -e "${RED}✗ $1${NC}"
    ((TESTS_FAILED++))
}

# Function to print info
print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Global variables to store test data
TOKEN=""
USER_ID=""
GROUP_ID=""
CYCLE_ID=""

# ============================================
# TEST 1: Get Onboarding Configuration
# ============================================
test_get_config() {
    print_header "TEST 1: GET /vsla-onboarding/config"
    
    RESPONSE=$(curl -s -X GET "${API_BASE}/vsla-onboarding/config" \
        -H "${CONTENT_TYPE}")
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    # Check if response contains expected fields
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        if echo "$RESPONSE" | jq -e '.data.districts' > /dev/null; then
            print_success "Configuration retrieved successfully"
            print_info "Districts found: $(echo "$RESPONSE" | jq '.data.districts | length')"
        else
            print_error "Districts not found in response"
        fi
    else
        print_error "Failed to get configuration"
    fi
}

# ============================================
# TEST 2: Register Group Admin (Step 3)
# ============================================
test_register_admin() {
    print_header "TEST 2: POST /vsla-onboarding/register-admin"
    
    # Generate unique phone number for testing
    TIMESTAMP=$(date +%s)
    TEST_PHONE="0701${TIMESTAMP: -6}"
    
    print_info "Test Phone: ${TEST_PHONE}"
    
    RESPONSE=$(curl -s -X POST "${API_BASE}/vsla-onboarding/register-admin" \
        -H "${CONTENT_TYPE}" \
        -d '{
            "name": "Test Admin User",
            "phone_number": "'${TEST_PHONE}'",
            "email": "testadmin'${TIMESTAMP}'@example.com",
            "password": "test123",
            "password_confirmation": "test123",
            "country": "Uganda"
        }')
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    # Extract token and user ID
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        TOKEN=$(echo "$RESPONSE" | jq -r '.data.token')
        USER_ID=$(echo "$RESPONSE" | jq -r '.data.user.id')
        
        if [ -n "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
            print_success "Admin registered successfully"
            print_info "Token: ${TOKEN:0:20}..."
            print_info "User ID: ${USER_ID}"
            
            # Verify onboarding fields
            IS_ADMIN=$(echo "$RESPONSE" | jq -r '.data.user.is_group_admin')
            ONBOARDING_STEP=$(echo "$RESPONSE" | jq -r '.data.user.onboarding_step')
            
            if [ "$IS_ADMIN" == "Yes" ]; then
                print_success "User marked as group admin"
            else
                print_error "User NOT marked as group admin"
            fi
            
            if [ "$ONBOARDING_STEP" == "step_3_registration" ]; then
                print_success "Onboarding step set correctly"
            else
                print_error "Onboarding step incorrect: $ONBOARDING_STEP"
            fi
        else
            print_error "Token not returned"
        fi
    else
        print_error "Admin registration failed"
    fi
}

# ============================================
# TEST 3: Create VSLA Group (Step 4)
# ============================================
test_create_group() {
    print_header "TEST 3: POST /vsla-onboarding/create-group"
    
    if [ -z "$TOKEN" ]; then
        print_error "No token available. Skipping test."
        return
    fi
    
    TIMESTAMP=$(date +%s)
    
    RESPONSE=$(curl -s -X POST "${API_BASE}/vsla-onboarding/create-group" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "User-Id: ${USER_ID}" \
        -F "user=${USER_ID}" \
        -F "User-Id=${USER_ID}" \
        -F "name=Test VSLA Group ${TIMESTAMP}" \
        -F "description=This is a test VSLA group for API testing purposes" \
        -F "meeting_frequency=Weekly" \
        -F "establishment_date=2025-01-01" \
        -F "district_id=1" \
        -F "estimated_members=25" \
        -F "subcounty_text=Test Subcounty" \
        -F "parish_text=Test Parish" \
        -F "village=Test Village")
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        GROUP_ID=$(echo "$RESPONSE" | jq -r '.data.group.id')
        GROUP_CODE=$(echo "$RESPONSE" | jq -r '.data.group.code')
        
        if [ -n "$GROUP_ID" ] && [ "$GROUP_ID" != "null" ]; then
            print_success "Group created successfully"
            print_info "Group ID: ${GROUP_ID}"
            print_info "Group Code: ${GROUP_CODE}"
            
            # Verify fields match mobile app expectations
            MEETING_FREQ=$(echo "$RESPONSE" | jq -r '.data.group.meeting_frequency')
            EST_MEMBERS=$(echo "$RESPONSE" | jq -r '.data.group.estimated_members')
            
            if [ "$MEETING_FREQ" == "Weekly" ]; then
                print_success "meeting_frequency field correct"
            else
                print_error "meeting_frequency field incorrect"
            fi
            
            if [ "$EST_MEMBERS" == "25" ]; then
                print_success "estimated_members field correct"
            else
                print_error "estimated_members field incorrect"
            fi
        else
            print_error "Group ID not returned"
        fi
    else
        ERROR_MSG=$(echo "$RESPONSE" | jq -r '.message')
        print_error "Group creation failed: $ERROR_MSG"
    fi
}

# ============================================
# TEST 4: Register Main Members (Step 5)
# ============================================
test_register_members() {
    print_header "TEST 4: POST /vsla-onboarding/register-main-members"
    
    if [ -z "$TOKEN" ] || [ -z "$GROUP_ID" ]; then
        print_error "No token or group ID available. Skipping test."
        return
    fi
    
    TIMESTAMP=$(date +%s)
    SEC_PHONE="0702${TIMESTAMP: -6}"
    TRES_PHONE="0703${TIMESTAMP: -6}"
    
    print_info "Secretary Phone: ${SEC_PHONE}"
    print_info "Treasurer Phone: ${TRES_PHONE}"
    
    RESPONSE=$(curl -s -X POST "${API_BASE}/vsla-onboarding/register-main-members" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "User-Id: ${USER_ID}" \
        -F "user=${USER_ID}" \
        -F "User-Id=${USER_ID}" \
        -F "secretary_name=Test Secretary" \
        -F "secretary_phone=${SEC_PHONE}" \
        -F "secretary_email=secretary${TIMESTAMP}@example.com" \
        -F "treasurer_name=Test Treasurer" \
        -F "treasurer_phone=${TRES_PHONE}" \
        -F "treasurer_email=treasurer${TIMESTAMP}@example.com" \
        -F "send_sms=0")
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        SEC_ID=$(echo "$RESPONSE" | jq -r '.data.secretary.id')
        TRES_ID=$(echo "$RESPONSE" | jq -r '.data.treasurer.id')
        
        if [ -n "$SEC_ID" ] && [ "$SEC_ID" != "null" ]; then
            print_success "Secretary registered successfully"
            print_info "Secretary ID: ${SEC_ID}"
            
            # Verify field names
            IS_SEC=$(echo "$RESPONSE" | jq -r '.data.secretary.is_group_secretary')
            if [ "$IS_SEC" == "Yes" ]; then
                print_success "is_group_secretary field correct"
            else
                print_error "is_group_secretary field incorrect"
            fi
        else
            print_error "Secretary not created"
        fi
        
        if [ -n "$TRES_ID" ] && [ "$TRES_ID" != "null" ]; then
            print_success "Treasurer registered successfully"
            print_info "Treasurer ID: ${TRES_ID}"
            
            # Verify field names
            IS_TRES=$(echo "$RESPONSE" | jq -r '.data.treasurer.is_group_treasurer')
            if [ "$IS_TRES" == "Yes" ]; then
                print_success "is_group_treasurer field correct"
            else
                print_error "is_group_treasurer field incorrect"
            fi
        else
            print_error "Treasurer not created"
        fi
    else
        ERROR_MSG=$(echo "$RESPONSE" | jq -r '.message')
        print_error "Members registration failed: $ERROR_MSG"
    fi
}

# ============================================
# TEST 5: Create Savings Cycle (Step 6)
# ============================================
test_create_cycle() {
    print_header "TEST 5: POST /vsla-onboarding/create-cycle"
    
    if [ -z "$TOKEN" ] || [ -z "$GROUP_ID" ]; then
        print_error "No token or group ID available. Skipping test."
        return
    fi
    
    RESPONSE=$(curl -s -X POST "${API_BASE}/vsla-onboarding/create-cycle" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "User-Id: ${USER_ID}" \
        -F "user=${USER_ID}" \
        -F "User-Id=${USER_ID}" \
        -F "cycle_name=Test Cycle 2025" \
        -F "start_date=2025-01-01" \
        -F "end_date=2025-12-31" \
        -F "share_value=5000" \
        -F "meeting_frequency=Weekly" \
        -F "loan_interest_rate=10" \
        -F "interest_frequency=Monthly" \
        -F "monthly_loan_interest_rate=10" \
        -F "minimum_loan_amount=50000" \
        -F "maximum_loan_multiple=20" \
        -F "late_payment_penalty=5")
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        CYCLE_ID=$(echo "$RESPONSE" | jq -r '.data.cycle.id')
        
        if [ -n "$CYCLE_ID" ] && [ "$CYCLE_ID" != "null" ]; then
            print_success "Savings cycle created successfully"
            print_info "Cycle ID: ${CYCLE_ID}"
            
            # Verify critical fields match mobile app
            SHARE_VALUE=$(echo "$RESPONSE" | jq -r '.data.cycle.share_value')
            LOAN_RATE=$(echo "$RESPONSE" | jq -r '.data.cycle.loan_interest_rate')
            MIN_LOAN=$(echo "$RESPONSE" | jq -r '.data.cycle.minimum_loan_amount')
            MAX_MULTIPLE=$(echo "$RESPONSE" | jq -r '.data.cycle.maximum_loan_multiple')
            
            if [ "$SHARE_VALUE" == "5000" ] || [ "$SHARE_VALUE" == "5000.00" ]; then
                print_success "share_value field correct"
            else
                print_error "share_value field incorrect: $SHARE_VALUE"
            fi
            
            if [ "$LOAN_RATE" == "10" ] || [ "$LOAN_RATE" == "10.00" ]; then
                print_success "loan_interest_rate field correct"
            else
                print_error "loan_interest_rate field incorrect: $LOAN_RATE"
            fi
            
            if [ "$MIN_LOAN" == "50000" ] || [ "$MIN_LOAN" == "50000.00" ]; then
                print_success "minimum_loan_amount field correct"
            else
                print_error "minimum_loan_amount field incorrect: $MIN_LOAN"
            fi
            
            if [ "$MAX_MULTIPLE" == "20" ]; then
                print_success "maximum_loan_multiple field correct"
            else
                print_error "maximum_loan_multiple field incorrect: $MAX_MULTIPLE"
            fi
        else
            print_error "Cycle ID not returned"
        fi
    else
        ERROR_MSG=$(echo "$RESPONSE" | jq -r '.message')
        print_error "Cycle creation failed: $ERROR_MSG"
    fi
}

# ============================================
# TEST 6: Complete Onboarding (Step 7)
# ============================================
test_complete_onboarding() {
    print_header "TEST 6: POST /vsla-onboarding/complete"
    
    if [ -z "$TOKEN" ]; then
        print_error "No token available. Skipping test."
        return
    fi
    
    RESPONSE=$(curl -s -X POST "${API_BASE}/vsla-onboarding/complete" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "User-Id: ${USER_ID}" \
        -F "user=${USER_ID}" \
        -F "User-Id=${USER_ID}")
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        print_success "Onboarding completed successfully"
        
        # Verify summary data structure matches mobile app expectations
        if echo "$RESPONSE" | jq -e '.data.summary' > /dev/null; then
            print_success "Summary data present"
            
            # Check required summary fields
            GROUP_NAME=$(echo "$RESPONSE" | jq -r '.data.summary.group_name')
            GROUP_CODE=$(echo "$RESPONSE" | jq -r '.data.summary.group_code')
            TOTAL_MEMBERS=$(echo "$RESPONSE" | jq -r '.data.summary.total_members')
            
            if [ -n "$GROUP_NAME" ] && [ "$GROUP_NAME" != "null" ]; then
                print_success "group_name field present"
            else
                print_error "group_name field missing"
            fi
            
            if [ -n "$GROUP_CODE" ] && [ "$GROUP_CODE" != "null" ]; then
                print_success "group_code field present"
            else
                print_error "group_code field missing"
            fi
        else
            print_error "Summary data missing"
        fi
        
        # Verify officer data
        if echo "$RESPONSE" | jq -e '.data.secretary' > /dev/null; then
            print_success "Secretary data present"
        else
            print_error "Secretary data missing"
        fi
        
        if echo "$RESPONSE" | jq -e '.data.treasurer' > /dev/null; then
            print_success "Treasurer data present"
        else
            print_error "Treasurer data missing"
        fi
        
        # Verify cycle data
        if echo "$RESPONSE" | jq -e '.data.cycle' > /dev/null; then
            print_success "Cycle data present"
        else
            print_error "Cycle data missing"
        fi
    else
        ERROR_MSG=$(echo "$RESPONSE" | jq -r '.message')
        print_error "Onboarding completion failed: $ERROR_MSG"
    fi
}

# ============================================
# TEST 7: Get Onboarding Status
# ============================================
test_get_status() {
    print_header "TEST 7: GET /vsla-onboarding/status"
    
    if [ -z "$TOKEN" ]; then
        print_error "No token available. Skipping test."
        return
    fi
    
    RESPONSE=$(curl -s -X GET "${API_BASE}/vsla-onboarding/status" \
        -H "${CONTENT_TYPE}" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "User-Id: ${USER_ID}")
    
    echo "Response:"
    echo "$RESPONSE" | jq '.'
    
    if echo "$RESPONSE" | jq -e '.code == 1' > /dev/null; then
        print_success "Status retrieved successfully"
        
        CURRENT_STEP=$(echo "$RESPONSE" | jq -r '.data.current_step')
        IS_COMPLETE=$(echo "$RESPONSE" | jq -r '.data.is_complete')
        
        print_info "Current Step: ${CURRENT_STEP}"
        print_info "Is Complete: ${IS_COMPLETE}"
        
        if [ "$CURRENT_STEP" == "step_7_complete" ]; then
            print_success "Onboarding step correct (step_7_complete)"
        else
            print_info "Current onboarding step: $CURRENT_STEP"
        fi
    else
        ERROR_MSG=$(echo "$RESPONSE" | jq -r '.message')
        print_error "Status retrieval failed: $ERROR_MSG"
    fi
}

# ============================================
# MAIN EXECUTION
# ============================================

echo -e "${BLUE}"
echo "============================================"
echo "  VSLA ONBOARDING API TEST SUITE"
echo "============================================"
echo -e "${NC}"
echo ""
echo "Testing API: ${API_BASE}"
echo "Started: $(date)"
echo ""

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo -e "${RED}Error: jq is not installed. Please install jq to run this script.${NC}"
    echo "Install with: brew install jq (macOS) or apt-get install jq (Linux)"
    exit 1
fi

# Run all tests in sequence
test_get_config
test_register_admin
test_create_group
test_register_members
test_create_cycle
test_complete_onboarding
test_get_status

# Print summary
echo ""
print_header "TEST SUMMARY"
echo -e "Tests Passed: ${GREEN}${TESTS_PASSED}${NC}"
echo -e "Tests Failed: ${RED}${TESTS_FAILED}${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    exit 1
fi

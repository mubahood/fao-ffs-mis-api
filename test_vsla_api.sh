#!/bin/bash

# VSLA Transaction API Testing Script
# Tests all endpoints for the universal transaction system

# Configuration
BASE_URL="http://localhost:8888/api"
TOKEN="f54cc4931c83b78f29c0db4e454f52a570c09e40ab20254ba728f03739f09e32"
USER_ID=1
PROJECT_ID=1
GROUP_ID=1

echo "=========================================="
echo "VSLA Transaction API Testing"
echo "=========================================="
echo ""

# Test 1: Create Saving Transaction
echo "TEST 1: Create Saving Transaction"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "saving",
    "amount": 50000,
    "description": "Monthly savings contribution - API Test",
    "transaction_date": "2025-01-20"
  }' | json_pp
echo ""
echo ""

# Test 2: Create Fine Transaction
echo "TEST 2: Create Fine Transaction"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "fine",
    "amount": 5000,
    "description": "Late attendance fine - API Test",
    "transaction_date": "2025-01-20"
  }' | json_pp
echo ""
echo ""

# Test 3: Create Charge Transaction
echo "TEST 3: Create Charge Transaction"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "charge",
    "amount": 3000,
    "description": "Administrative charge - API Test",
    "transaction_date": "2025-01-20"
  }' | json_pp
echo ""
echo ""

# Test 4: Create Welfare Transaction
echo "TEST 4: Create Welfare Transaction"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "welfare",
    "amount": 10000,
    "description": "Welfare fund contribution - API Test",
    "transaction_date": "2025-01-20"
  }' | json_pp
echo ""
echo ""

# Test 5: Create Social Fund Transaction
echo "TEST 5: Create Social Fund Transaction"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "social_fund",
    "amount": 2000,
    "description": "Social fund contribution - API Test",
    "transaction_date": "2025-01-20"
  }' | json_pp
echo ""
echo ""

# Test 6: Create Loan Repayment Transaction
echo "TEST 6: Create Loan Repayment Transaction"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "loan_repayment",
    "amount": 25000,
    "description": "Loan repayment - API Test",
    "transaction_date": "2025-01-20"
  }' | json_pp
echo ""
echo ""

# Test 7: Get Member Balance
echo "TEST 7: Get Member Balance"
echo "------------------------------------------"
curl -X GET "${BASE_URL}/vsla/transactions/member-balance/${USER_ID}?project_id=${PROJECT_ID}" \
  -H "Authorization: Bearer ${TOKEN}" | json_pp
echo ""
echo ""

# Test 8: Get Group Balance
echo "TEST 8: Get Group Balance"
echo "------------------------------------------"
curl -X GET "${BASE_URL}/vsla/transactions/group-balance/${GROUP_ID}?project_id=${PROJECT_ID}" \
  -H "Authorization: Bearer ${TOKEN}" | json_pp
echo ""
echo ""

# Test 9: Validation Error Test - Missing Required Field
echo "TEST 9: Validation Error Test (Missing amount)"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "saving",
    "description": "Test validation"
  }' | json_pp
echo ""
echo ""

# Test 10: Validation Error Test - Invalid Transaction Type
echo "TEST 10: Validation Error Test (Invalid type)"
echo "------------------------------------------"
curl -X POST "${BASE_URL}/vsla/transactions/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "user_id": '${USER_ID}',
    "project_id": '${PROJECT_ID}',
    "transaction_type": "invalid_type",
    "amount": 10000,
    "description": "Test validation"
  }' | json_pp
echo ""
echo ""

# Final: Check User Balance in Database
echo "FINAL: Check User Balance in Database"
echo "------------------------------------------"
cd /Applications/MAMP/htdocs/fao-ffs-mis-api && \
php artisan tinker --execute='$user = \App\Models\User::find(1); if($user) { echo "User Balance: " . $user->balance . PHP_EOL; echo "Loan Balance: " . $user->loan_balance . PHP_EOL; }'

echo ""
echo "=========================================="
echo "Testing Complete!"
echo "=========================================="

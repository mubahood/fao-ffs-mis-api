#!/bin/bash
BASE_URL="http://localhost:8888/fao-ffs-mis-api/api"
TOKEN="f54cc4931c83b78f29c0db4e454f52a570c09e40ab20254ba728f03739f09e32"

echo "=========================================="
echo "COMPREHENSIVE VSLA API TESTING"
echo "=========================================="
echo ""

# Test Fine Transaction
echo "TEST: Fine Transaction"
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "User-Id: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"fine","amount":3000,"description":"Late meeting fine","transaction_date":"2025-01-21"}' | python3 -m json.tool | head -3
echo -e "\n"

# Test Charge Transaction
echo "TEST: Charge Transaction"
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "User-Id: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"charge","amount":2000,"description":"Admin charge","transaction_date":"2025-01-21"}' | python3 -m json.tool | head -3
echo -e "\n"

# Test Welfare Transaction
echo "TEST: Welfare Transaction"
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "User-Id: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"welfare","amount":5000,"description":"Welfare contribution","transaction_date":"2025-01-21"}' | python3 -m json.tool | head -3
echo -e "\n"

# Test Social Fund Transaction
echo "TEST: Social Fund Transaction"
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "User-Id: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"social_fund","amount":1000,"description":"Social fund","transaction_date":"2025-01-21"}' | python3 -m json.tool | head -3
echo -e "\n"

# Test Loan Repayment Transaction
echo "TEST: Loan Repayment Transaction"
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "User-Id: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"loan_repayment","amount":10000,"description":"Loan repayment","transaction_date":"2025-01-21"}' | python3 -m json.tool | head -3
echo -e "\n"

# Get Member Balance
echo "TEST: Get Member Balance"
curl -s -X GET "${BASE_URL}/vsla/transactions/member-balance/1?project_id=1" \
-H "Authorization: Bearer ${TOKEN}" \
-H "User-Id: 1" | python3 -m json.tool
echo -e "\n"

# Check Database Balance
echo "Database Verification:"
php artisan tinker --execute='$u = \App\Models\User::find(1); echo "User Balance: " . $u->balance . PHP_EOL; echo "Loan Balance: " . $u->loan_balance . PHP_EOL;' 2>/dev/null | grep -E "(User Balance|Loan Balance)"

echo ""
echo "=========================================="
echo "ALL TESTS COMPLETE!"
echo "=========================================="

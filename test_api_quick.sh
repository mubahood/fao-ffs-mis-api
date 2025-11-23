#!/bin/bash
BASE_URL="http://localhost:8888/fao-ffs-mis-api/api"
TOKEN="f54cc4931c83b78f29c0db4e454f52a570c09e40ab20254ba728f03739f09e32"

echo "=== TEST 1: Saving Transaction ==="
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "HTTP_USER_ID: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"saving","amount":50000,"description":"Savings Test","transaction_date":"2025-01-20"}' | python3 -m json.tool
echo -e "\n"

echo "=== TEST 2: Fine Transaction ==="
curl -s -X POST "${BASE_URL}/vsla/transactions/create" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer ${TOKEN}" \
-H "HTTP_USER_ID: 1" \
-d '{"user_id":1,"project_id":1,"transaction_type":"fine","amount":5000,"description":"Fine Test","transaction_date":"2025-01-20"}' | python3 -m json.tool
echo -e "\n"

echo "=== TEST 3: Get Member Balance ==="
curl -s -X GET "${BASE_URL}/vsla/transactions/member-balance/1?project_id=1" \
-H "Authorization: Bearer ${TOKEN}" \
-H "HTTP_USER_ID: 1" | python3 -m json.tool
echo -e "\n"

echo "=== Check Database Balance ==="
php artisan tinker --execute='$u = \App\Models\User::find(1); echo "Balance: " . $u->balance . " | Loan: " . $u->loan_balance . PHP_EOL;' 2>/dev/null | tail -1

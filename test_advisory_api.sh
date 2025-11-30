#!/bin/bash

BASE_URL="http://localhost:8888/fao-ffs-mis-api/api/advisory"

echo "🧪 Testing Advisory Module API Endpoints"
echo "=========================================="
echo ""

echo "1️⃣ Testing Categories Endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/categories")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Categories: SUCCESS (HTTP $STATUS)"
    COUNT=$(curl -s "$BASE_URL/categories" | grep -o '"id":[0-9]*' | wc -l | tr -d ' ')
    echo "   📊 Found $COUNT categories"
else
    echo "   ❌ Categories: FAILED (HTTP $STATUS)"
fi
echo ""

echo "2️⃣ Testing Posts Endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/posts?per_page=5")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Posts: SUCCESS (HTTP $STATUS)"
    COUNT=$(curl -s "$BASE_URL/posts?per_page=5" | grep -o '"id":[0-9]*' | wc -l | tr -d ' ')
    echo "   📊 Found $COUNT posts"
else
    echo "   ❌ Posts: FAILED (HTTP $STATUS)"
fi
echo ""

echo "3️⃣ Testing Featured Posts Endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/posts/featured?limit=3")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Featured Posts: SUCCESS (HTTP $STATUS)"
    COUNT=$(curl -s "$BASE_URL/posts/featured?limit=3" | grep -o '"featured":true' | wc -l | tr -d ' ')
    echo "   📊 Found $COUNT featured posts"
else
    echo "   ❌ Featured Posts: FAILED (HTTP $STATUS)"
fi
echo ""

echo "4️⃣ Testing Single Post Endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/posts/1")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Single Post: SUCCESS (HTTP $STATUS)"
    TITLE=$(curl -s "$BASE_URL/posts/1" | grep -o '"title":"[^"]*"' | head -1 | cut -d'"' -f4)
    echo "   📄 Post: $TITLE"
else
    echo "   ❌ Single Post: FAILED (HTTP $STATUS)"
fi
echo ""

echo "5️⃣ Testing Questions Endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/questions")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Questions: SUCCESS (HTTP $STATUS)"
    COUNT=$(curl -s "$BASE_URL/questions" | grep -o '"id":[0-9]*' | wc -l | tr -d ' ')
    echo "   📊 Found $COUNT questions"
else
    echo "   ❌ Questions: FAILED (HTTP $STATUS)"
fi
echo ""

echo "6️⃣ Testing Search Endpoint..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/posts?search=pest")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Search: SUCCESS (HTTP $STATUS)"
    RESULTS=$(curl -s "$BASE_URL/posts?search=pest" | grep -o '"id":[0-9]*' | wc -l | tr -d ' ')
    echo "   🔍 Found $RESULTS results for 'pest'"
else
    echo "   ❌ Search: FAILED (HTTP $STATUS)"
fi
echo ""

echo "7️⃣ Testing Category Filter..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/posts?category_id=1")
if [ "$STATUS" -eq 200 ]; then
    echo "   ✅ Category Filter: SUCCESS (HTTP $STATUS)"
    RESULTS=$(curl -s "$BASE_URL/posts?category_id=1" | grep -o '"category_id":1' | wc -l | tr -d ' ')
    echo "   📁 Found $RESULTS posts in category 1"
else
    echo "   ❌ Category Filter: FAILED (HTTP $STATUS)"
fi
echo ""

echo "=========================================="
echo "✅ Advisory Module API Test Complete!"
echo ""
echo "📝 Admin Panel: http://localhost:8888/fao-ffs-mis-api/admin"
echo "   Navigate to: Advisory > Categories/Articles/Questions"
echo ""
echo "📱 Flutter Integration:"
echo "   - Models: lib/models/AdvisoryModels.dart"
echo "   - Service: lib/services/advisory_api.dart"  
echo "   - Screen: lib/screens/advisory/AdvisoryMainScreen.dart"
echo ""

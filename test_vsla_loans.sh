#!/bin/bash

# VSLA Loan Management System Test Script
# Tests all loan management endpoints

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://localhost:8888"
API_URL="${BASE_URL}/api/vsla/loans"

# Test user token (replace with actual token)
TOKEN="your-auth-token-here"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}VSLA LOAN MANAGEMENT SYSTEM TEST${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Test data
PROJECT_ID=1
USER_ID=2
BORROWER_ID=3
APPROVED_BY_ID=2

echo "Base URL: $BASE_URL"
echo "Project ID: $PROJECT_ID"
echo "Borrower ID: $BORROWER_ID"
echo ""
echo "NOTE: Update TOKEN, PROJECT_ID, and USER_IDs in this script before running"
echo ""
echo "Test endpoints available:"
echo "1. POST /api/vsla/loans/check-eligibility - Check borrower eligibility"
echo "2. POST /api/vsla/loans - Create loan request"
echo "3. GET /api/vsla/loans/{id} - Get loan details"
echo "4. POST /api/vsla/loans/{id}/approve - Approve loan"
echo "5. POST /api/vsla/loans/{id}/reject - Reject loan"
echo "6. POST /api/vsla/loans/{id}/disburse - Disburse loan"
echo "7. POST /api/vsla/loans/{id}/repayments - Record repayment"
echo "8. GET /api/vsla/loans/{id}/repayments - Get repayments"
echo "9. GET /api/vsla/loans/{id}/statement - Generate statement"
echo "10. GET /api/vsla/loans/pending-approvals - Get pending approvals"
echo "11. GET /api/vsla/loans/overdue - Get overdue loans"
echo "12. GET /api/vsla/loans/user/{userId} - Get user's loans"
echo "13. GET /api/vsla/loans/summary - Get loan summary"

# VSLA Transaction API - Complete Documentation & Testing Guide

**Date:** November 22, 2025  
**Status:** ✅ Ready for Testing  
**Base URL:** `http://localhost:8888/fao-ffs-mis-api/api`

---

## 🎯 API Endpoints Overview

All endpoints require authentication via JWT token in headers:
```
Authorization: Bearer {token}
User-Id: {user_id}
```

### Transaction Creation Endpoints

1. **POST** `/vsla/transactions/saving` - Record member savings
2. **POST** `/vsla/transactions/loan-disbursement` - Disburse loan to member
3. **POST** `/vsla/transactions/loan-repayment` - Record loan repayment
4. **POST** `/vsla/transactions/fine` - Record fine/penalty

### Balance & Statement Endpoints

5. **GET** `/vsla/transactions/member-balance/{user_id}` - Get member balance
6. **GET** `/vsla/transactions/group-balance/{group_id}` - Get group balance
7. **GET** `/vsla/transactions/member-statement` - Get member transaction history
8. **GET** `/vsla/transactions/group-statement` - Get group transaction history

### Dashboard Endpoints

9. **GET** `/vsla/transactions/recent` - Get recent transactions
10. **GET** `/vsla/transactions/dashboard-summary` - Get dashboard overview

---

## 📋 Detailed API Documentation

### 1. Record Savings

**Endpoint:** `POST /api/vsla/transactions/saving`

**Purpose:** Record a member's savings contribution. Creates two transactions:
- User savings account (debit +)
- Group cash account (credit +)

**Request Body:**
```json
{
  "user_id": 25,
  "project_id": 15,
  "amount": 50000,
  "description": "Monthly savings contribution",
  "transaction_date": "2025-11-22"
}
```

**Validation Rules:**
- `user_id`: Required, must exist in users table
- `project_id`: Required, must exist in projects table
- `amount`: Required, numeric, minimum 1
- `description`: Optional, max 500 characters
- `transaction_date`: Optional, valid date (defaults to today)

**Success Response (201):**
```json
{
  "code": 1,
  "message": "Savings recorded successfully",
  "data": {
    "user_transaction": {
      "id": 101,
      "project_id": 15,
      "amount": 50000,
      "amount_signed": 50000,
      "owner_type": "user",
      "owner_id": 25,
      "account_type": "savings",
      "contra_entry_id": 102,
      "is_contra_entry": false,
      "transaction_date": "2025-11-22"
    },
    "group_transaction": {
      "id": 102,
      "project_id": 15,
      "amount": 50000,
      "amount_signed": 50000,
      "owner_type": "group",
      "owner_id": 12,
      "account_type": "cash",
      "contra_entry_id": 101,
      "is_contra_entry": true,
      "transaction_date": "2025-11-22"
    },
    "user_balances": {
      "savings": 50000,
      "loans": 0,
      "fines": 0,
      "interest": 0,
      "net_position": 50000
    },
    "group_balances": {
      "cash": 50000,
      "total_savings": 50000,
      "loans_outstanding": 0,
      "fines_collected": 0
    }
  }
}
```

**Error Response (400):**
```json
{
  "code": 0,
  "message": "User is not a member of this VSLA group",
  "data": null
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/saving \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "User-Id: 25" \
  -d '{
    "user_id": 25,
    "project_id": 15,
    "amount": 50000,
    "description": "Monthly savings"
  }'
```

---

### 2. Disburse Loan

**Endpoint:** `POST /api/vsla/transactions/loan-disbursement`

**Purpose:** Disburse a loan to a member. Creates two transactions:
- Group cash account (debit -)
- User loan account (credit +)

**Request Body:**
```json
{
  "user_id": 25,
  "project_id": 15,
  "amount": 150000,
  "interest_rate": 10,
  "description": "Business expansion loan",
  "transaction_date": "2025-11-22"
}
```

**Validation Rules:**
- `user_id`: Required, must exist
- `project_id`: Required, must exist
- `amount`: Required, numeric, min 1
- `interest_rate`: Optional, numeric, 0-100
- `description`: Optional, max 500 characters
- `transaction_date`: Optional, valid date

**Business Rules:**
- Loan amount must not exceed (member savings × max_loan_multiplier)
- Group must have sufficient cash balance
- Member must not have existing outstanding loan
- Member must be part of the group

**Success Response (201):**
```json
{
  "code": 1,
  "message": "Loan disbursed successfully",
  "data": {
    "group_transaction": {
      "id": 103,
      "amount": 150000,
      "amount_signed": -150000,
      "owner_type": "group",
      "account_type": "cash"
    },
    "user_transaction": {
      "id": 104,
      "amount": 150000,
      "amount_signed": 150000,
      "owner_type": "user",
      "account_type": "loan",
      "contra_entry_id": 103
    },
    "user_balances": {
      "savings": 50000,
      "loans": 150000,
      "net_position": -100000
    },
    "group_balances": {
      "cash": -100000,
      "loans_outstanding": 150000
    },
    "interest_rate": 10
  }
}
```

**Error Response (400):**
```json
{
  "code": 0,
  "message": "Loan amount exceeds maximum allowed (UGX 150,000)",
  "data": null
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/loan-disbursement \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "user_id": 25,
    "project_id": 15,
    "amount": 150000,
    "interest_rate": 10
  }'
```

---

### 3. Record Loan Repayment

**Endpoint:** `POST /api/vsla/transactions/loan-repayment`

**Purpose:** Record a member's loan repayment. Creates two transactions:
- User loan account (debit -)
- Group cash account (credit +)

**Request Body:**
```json
{
  "user_id": 25,
  "project_id": 15,
  "amount": 30000,
  "description": "Partial loan repayment",
  "transaction_date": "2025-11-22"
}
```

**Business Rules:**
- Member must have outstanding loan
- Repayment amount cannot exceed outstanding loan
- Updates loan balance automatically

**Success Response (201):**
```json
{
  "code": 1,
  "message": "Loan repayment recorded successfully",
  "data": {
    "user_transaction": {
      "id": 105,
      "amount": 30000,
      "amount_signed": -30000,
      "account_type": "loan"
    },
    "group_transaction": {
      "id": 106,
      "amount": 30000,
      "amount_signed": 30000,
      "account_type": "cash"
    },
    "user_balances": {
      "loans": 120000
    },
    "remaining_loan": 120000
  }
}
```

---

### 4. Record Fine

**Endpoint:** `POST /api/vsla/transactions/fine`

**Purpose:** Record a fine or penalty for a member. Creates two transactions:
- User fine account (debit -)
- Group cash account (credit +)

**Request Body:**
```json
{
  "user_id": 25,
  "project_id": 15,
  "amount": 5000,
  "description": "Late meeting attendance",
  "transaction_date": "2025-11-22"
}
```

**Validation:**
- `description` is REQUIRED for fines

**Success Response (201):**
```json
{
  "code": 1,
  "message": "Fine recorded successfully",
  "data": {
    "user_transaction": {
      "amount": 5000,
      "amount_signed": -5000,
      "account_type": "fine"
    },
    "group_transaction": {
      "amount": 5000,
      "amount_signed": 5000,
      "account_type": "cash"
    },
    "user_balances": {
      "fines": 5000
    }
  }
}
```

---

### 5. Get Member Balance

**Endpoint:** `GET /api/vsla/transactions/member-balance/{user_id}`

**Query Parameters:**
- `project_id` (optional): Filter by specific savings cycle

**Example:** `GET /api/vsla/transactions/member-balance/25?project_id=15`

**Success Response (200):**
```json
{
  "code": 1,
  "message": "Member balance retrieved successfully",
  "data": {
    "user_id": 25,
    "user_name": "John Doe",
    "project_id": 15,
    "balances": {
      "savings": 50000,
      "loans": 120000,
      "fines": 5000,
      "interest": 0,
      "net_position": -75000
    },
    "formatted": {
      "savings": "UGX 50,000.00",
      "loans": "UGX 120,000.00",
      "fines": "UGX 5,000.00",
      "interest": "UGX 0.00",
      "net_position": "UGX -75,000.00"
    }
  }
}
```

---

### 6. Get Group Balance

**Endpoint:** `GET /api/vsla/transactions/group-balance/{group_id}`

**Query Parameters:**
- `project_id` (optional): Filter by specific savings cycle

**Example:** `GET /api/vsla/transactions/group-balance/12?project_id=15`

**Success Response (200):**
```json
{
  "code": 1,
  "message": "Group balance retrieved successfully",
  "data": {
    "group_id": 12,
    "project_id": 15,
    "balances": {
      "cash": 75000,
      "total_savings": 200000,
      "loans_outstanding": 120000,
      "fines_collected": 5000
    },
    "formatted": {
      "cash": "UGX 75,000.00",
      "total_savings": "UGX 200,000.00",
      "loans_outstanding": "UGX 120,000.00",
      "fines_collected": "UGX 5,000.00"
    },
    "accounting_verification": {
      "total_debits": 375000,
      "total_credits": 375000,
      "difference": 0,
      "is_balanced": true
    }
  }
}
```

---

### 7. Get Member Statement

**Endpoint:** `GET /api/vsla/transactions/member-statement`

**Query Parameters:**
- `user_id` (required): User ID
- `project_id` (optional): Filter by cycle
- `account_type` (optional): Filter by type (savings, loan, fine)
- `limit` (optional): Number of records (default: 50)

**Example:** `GET /api/vsla/transactions/member-statement?user_id=25&project_id=15&limit=20`

**Success Response (200):**
```json
{
  "code": 1,
  "message": "Member statement retrieved successfully",
  "data": {
    "transactions": [
      {
        "id": 105,
        "amount": 30000,
        "amount_signed": -30000,
        "description": "Loan repayment",
        "account_type": "loan",
        "transaction_date": "2025-11-22",
        "contra_entry": {...}
      }
    ],
    "balances": {
      "savings": 50000,
      "loans": 120000,
      "net_position": -70000
    },
    "count": 5
  }
}
```

---

### 8. Get Dashboard Summary

**Endpoint:** `GET /api/vsla/transactions/dashboard-summary`

**Query Parameters:**
- `group_id` (required): Group ID
- `project_id` (optional): Filter by cycle

**Example:** `GET /api/vsla/transactions/dashboard-summary?group_id=12&project_id=15`

**Success Response (200):**
```json
{
  "code": 1,
  "message": "Dashboard summary retrieved successfully",
  "data": {
    "overview": {
      "total_savings": 200000,
      "formatted_savings": "UGX 200,000",
      "active_loans": 3,
      "loans_outstanding": 450000,
      "formatted_loans": "UGX 450,000",
      "total_members": 25,
      "cash_balance": -250000,
      "formatted_cash": "UGX -250,000",
      "fines_collected": 15000
    },
    "cycle_progress": {
      "start_date": "Jan 01, 2025",
      "end_date": "Jun 30, 2025",
      "elapsed_weeks": 17,
      "total_weeks": 26,
      "percentage": 65
    },
    "group_id": 12,
    "project_id": 15
  }
}
```

---

### 9. Get Recent Transactions

**Endpoint:** `GET /api/vsla/transactions/recent`

**Query Parameters:**
- `group_id` or `project_id` (one required)
- `type` (optional): savings, loans, transactions
- `limit` (optional): Number of records (default: 10)

**Example:** `GET /api/vsla/transactions/recent?project_id=15&type=savings&limit=5`

**Success Response (200):**
```json
{
  "code": 1,
  "message": "Recent transactions retrieved successfully",
  "data": {
    "transactions": [
      {
        "id": 101,
        "amount": 50000,
        "formatted_amount": "UGX 50,000",
        "description": "Monthly savings",
        "account_type": "savings",
        "owner_type": "user",
        "owner_name": "John Doe",
        "transaction_date": "Nov 22, 2025",
        "type": "income"
      }
    ],
    "count": 5
  }
}
```

---

## 🧪 Testing Scenarios

### Test Scenario 1: Complete Savings Flow

```bash
# Step 1: Record John's savings
curl -X POST http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/saving \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "user_id": 25,
    "project_id": 15,
    "amount": 100000,
    "description": "Initial savings"
  }'

# Expected: 2 transactions created
# - User savings: +100,000
# - Group cash: +100,000

# Step 2: Verify balances
curl -X GET "http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/member-balance/25?project_id=15" \
  -H "Authorization: Bearer {token}"

# Expected: savings = 100,000
```

### Test Scenario 2: Loan Lifecycle

```bash
# Step 1: Record savings (prerequisite)
# (Use Test Scenario 1)

# Step 2: Disburse loan (3x savings = 300,000 max)
curl -X POST http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/loan-disbursement \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 25,
    "project_id": 15,
    "amount": 250000,
    "interest_rate": 10
  }'

# Expected: 2 transactions created
# - Group cash: -250,000
# - User loan: +250,000

# Step 3: Partial repayment
curl -X POST http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/loan-repayment \
  -d '{
    "user_id": 25,
    "project_id": 15,
    "amount": 50000
  }'

# Expected: Loan reduced to 200,000

# Step 4: Check remaining balance
curl -X GET "http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/member-balance/25?project_id=15"

# Expected: loans = 200,000
```

### Test Scenario 3: Multiple Members

```bash
# Member 1 saves
curl -X POST .../saving -d '{"user_id": 25, "project_id": 15, "amount": 50000}'

# Member 2 saves
curl -X POST .../saving -d '{"user_id": 26, "project_id": 15, "amount": 75000}'

# Member 3 saves
curl -X POST .../saving -d '{"user_id": 27, "project_id": 15, "amount": 60000}'

# Check group balance
curl -X GET ".../group-balance/12?project_id=15"

# Expected: 
# - total_savings = 185,000
# - cash = 185,000
```

### Test Scenario 4: Accounting Verification

```bash
# After multiple transactions, verify accounting equation
curl -X GET "http://localhost:8888/fao-ffs-mis-api/api/vsla/transactions/group-balance/12?project_id=15"

# Expected accounting_verification:
# {
#   "total_debits": 500000,
#   "total_credits": 500000,
#   "difference": 0,
#   "is_balanced": true
# }
```

---

## ✅ Testing Checklist

### Basic Functionality
- [ ] Record savings (single member)
- [ ] Record savings (multiple members)
- [ ] Disburse loan (within limit)
- [ ] Disburse loan (exceeds limit - should fail)
- [ ] Loan repayment (partial)
- [ ] Loan repayment (full)
- [ ] Loan repayment (exceeds outstanding - should fail)
- [ ] Record fine

### Balance Calculations
- [ ] Member balance after savings
- [ ] Member balance after loan
- [ ] Member balance after repayment
- [ ] Group cash balance accuracy
- [ ] Loans outstanding calculation
- [ ] Net position calculation

### Contra Entries
- [ ] Verify contra_entry_id links correctly
- [ ] Verify is_contra_entry flag
- [ ] Verify both entries have same amount
- [ ] Verify opposite signs (+ and -)

### Business Rules
- [ ] Maximum loan enforcement (savings × multiplier)
- [ ] Group cash sufficiency check
- [ ] Prevent multiple active loans
- [ ] Member group membership validation
- [ ] Transaction date validation

### Accounting Equation
- [ ] Total debits = Total credits
- [ ] After 10 transactions
- [ ] After 100 transactions
- [ ] After mixed operations

### Edge Cases
- [ ] Zero amount (should fail)
- [ ] Negative amount (should fail)
- [ ] Non-existent user (should fail)
- [ ] Non-existent project (should fail)
- [ ] User from different group (should fail)

---

## 📊 Expected Database State After Tests

After running all test scenarios, verify in database:

```sql
-- Check contra entries are linked
SELECT 
    t1.id,
    t1.amount_signed,
    t1.contra_entry_id,
    t2.amount_signed as contra_amount
FROM project_transactions t1
LEFT JOIN project_transactions t2 ON t1.contra_entry_id = t2.id
WHERE t1.project_id = 15;

-- Verify accounting balance
SELECT 
    SUM(CASE WHEN amount_signed > 0 THEN amount_signed ELSE 0 END) as debits,
    ABS(SUM(CASE WHEN amount_signed < 0 THEN amount_signed ELSE 0 END)) as credits
FROM project_transactions
WHERE project_id = 15;

-- Check member balances
SELECT 
    owner_id,
    account_type,
    SUM(amount_signed) as balance
FROM project_transactions
WHERE owner_type = 'user'
GROUP BY owner_id, account_type;
```

---

## 🎯 Performance Benchmarks

Target performance metrics:

- Transaction creation: < 200ms
- Balance calculation: < 100ms
- Statement retrieval (50 records): < 300ms
- Dashboard summary: < 500ms

---

**Status:** ✅ All Endpoints Ready for Testing  
**Next Step:** Execute test scenarios and verify results  
**Documentation:** Complete and ready for frontend integration

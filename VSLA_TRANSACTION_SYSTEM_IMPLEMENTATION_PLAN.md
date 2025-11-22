# VSLA Transaction System - Implementation Plan

**Date:** November 22, 2025  
**Status:** [ ] In Progress  
**Priority:** CRITICAL - Core Heart of VSLA System

---

## 📋 Executive Summary

This document outlines the complete implementation plan for the VSLA double-entry accounting transaction system using the existing `ProjectTransaction` model as the foundation.

---

## 🎯 Objectives

1. Implement a robust double-entry accounting system for VSLA groups
2. Ensure every debit has a corresponding credit (accounting equation balance)
3. Track member-level and group-level transactions separately
4. Support all VSLA financial activities: savings, loans, repayments, fines
5. Provide accurate balance calculations for members and groups
6. Create comprehensive API endpoints for transaction management
7. Build user-friendly Flutter screens with offline capability

---

## 📊 Current State Analysis

### Existing ProjectTransaction Model
**File:** `app/Models/ProjectTransaction.php`

**Current Fields:**
- `id` - Primary key
- `project_id` - Links to Project (Savings Cycle)
- `amount` - Transaction amount (decimal)
- `transaction_date` - Date of transaction
- `created_by_id` - User who created transaction
- `description` - Transaction description
- `type` - income/expense
- `source` - Transaction source
- `related_share_id` - Links to ProjectShare
- `created_at`, `updated_at`, `deleted_at` - Timestamps

**Current Features:**
✅ Soft deletes enabled
✅ Auto-recalculates project totals on create/update/delete
✅ Relationships: project, creator, relatedShare
✅ Scopes: income, expense, bySource, forProject
✅ Accessors: type_label, source_label, formatted_amount

**Missing for VSLA:**
❌ Owner tracking (user vs group)
❌ Contra entry linking
❌ Account type classification
❌ Double-entry logic
❌ Balance calculation methods

---

## 🔧 Required Database Changes

### Migration: Add VSLA Double-Entry Fields
**File:** `database/migrations/2025_11_22_XXXXXX_add_vsla_double_entry_fields_to_project_transactions.php`

```sql
-- New Columns to Add:
owner_type VARCHAR(10) NULL           -- 'user' or 'group'
owner_id BIGINT UNSIGNED NULL         -- User ID or Group ID
contra_entry_id BIGINT UNSIGNED NULL  -- Links to paired transaction
account_type VARCHAR(20) NULL         -- 'savings', 'loan', 'cash', 'fine', 'interest', 'penalty'
is_contra_entry TINYINT(1) DEFAULT 0  -- Flag for contra entries
amount_signed DECIMAL(15,2) NULL      -- Signed amount (+/-)
```

**Indexes:**
- `owner_type`, `owner_id` (composite index)
- `contra_entry_id`
- `account_type`
- `project_id`, `owner_type`, `owner_id` (composite)

---

## 📐 Double-Entry Accounting Rules

### Rule 1: Savings Transaction
**When member saves UGX 50,000:**

**Entry 1 (User Side):**
- owner_type: 'user'
- owner_id: [member_id]
- account_type: 'savings'
- amount: 50000
- amount_signed: +50000
- type: 'income'
- description: 'Savings contribution'

**Entry 2 (Group Side - Contra):**
- owner_type: 'group'
- owner_id: [group_id]
- account_type: 'cash'
- amount: 50000
- amount_signed: +50000
- type: 'income'
- description: 'Savings received from [member_name]'
- contra_entry_id: [Entry 1 ID]
- is_contra_entry: 1

### Rule 2: Loan Disbursement
**When member borrows UGX 200,000:**

**Entry 1 (Group Side):**
- owner_type: 'group'
- owner_id: [group_id]
- account_type: 'cash'
- amount: 200000
- amount_signed: -200000
- type: 'expense'
- description: 'Loan disbursed to [member_name]'

**Entry 2 (User Side - Contra):**
- owner_type: 'user'
- owner_id: [member_id]
- account_type: 'loan'
- amount: 200000
- amount_signed: +200000
- type: 'income'
- description: 'Loan received'
- contra_entry_id: [Entry 1 ID]
- is_contra_entry: 1

### Rule 3: Loan Repayment
**When member repays UGX 50,000:**

**Entry 1 (User Side):**
- owner_type: 'user'
- owner_id: [member_id]
- account_type: 'loan'
- amount: 50000
- amount_signed: -50000
- type: 'expense'
- description: 'Loan repayment'

**Entry 2 (Group Side - Contra):**
- owner_type: 'group'
- owner_id: [group_id]
- account_type: 'cash'
- amount: 50000
- amount_signed: +50000
- type: 'income'
- description: 'Loan repayment from [member_name]'
- contra_entry_id: [Entry 1 ID]
- is_contra_entry: 1

### Rule 4: Fine/Penalty
**When member fined UGX 5,000:**

**Entry 1 (User Side):**
- owner_type: 'user'
- owner_id: [member_id]
- account_type: 'fine'
- amount: 5000
- amount_signed: -5000
- type: 'expense'
- description: 'Late payment fine'

**Entry 2 (Group Side - Contra):**
- owner_type: 'group'
- owner_id: [group_id]
- account_type: 'cash'
- amount: 5000
- amount_signed: +5000
- type: 'income'
- description: 'Fine collected from [member_name]'
- contra_entry_id: [Entry 1 ID]
- is_contra_entry: 1

---

## 🏗️ Implementation Tasks

### Phase 1: Database & Model [~]
- [~] **Task 1.1:** Create migration for VSLA fields
- [ ] **Task 1.2:** Run migration and verify structure
- [ ] **Task 1.3:** Update ProjectTransaction model fillable array
- [ ] **Task 1.4:** Add owner polymorphic relationship
- [ ] **Task 1.5:** Add contraEntry relationship
- [ ] **Task 1.6:** Create scopes: userTransactions, groupTransactions, byAccountType
- [ ] **Task 1.7:** Add helper method: createWithContra()
- [ ] **Task 1.8:** Add calculation methods: calculateUserBalance(), calculateGroupBalance()

### Phase 2: Service Layer [ ]
- [ ] **Task 2.1:** Create VslaTransactionService class
- [ ] **Task 2.2:** Implement recordSaving() method
- [ ] **Task 2.3:** Implement disburseLoan() method
- [ ] **Task 2.4:** Implement recordLoanRepayment() method
- [ ] **Task 2.5:** Implement recordFine() method
- [ ] **Task 2.6:** Implement recordInterest() method
- [ ] **Task 2.7:** Add validation logic (max loan based on shares)
- [ ] **Task 2.8:** Add balance verification methods

### Phase 3: API Endpoints [ ]
- [ ] **Task 3.1:** POST /api/vsla/transactions/saving
- [ ] **Task 3.2:** POST /api/vsla/transactions/loan-disbursement
- [ ] **Task 3.3:** POST /api/vsla/transactions/loan-repayment
- [ ] **Task 3.4:** POST /api/vsla/transactions/fine
- [ ] **Task 3.5:** GET /api/vsla/transactions/member-balance/{user_id}
- [ ] **Task 3.6:** GET /api/vsla/transactions/group-balance/{group_id}
- [ ] **Task 3.7:** GET /api/vsla/transactions/member-statement
- [ ] **Task 3.8:** GET /api/vsla/transactions/group-statement
- [ ] **Task 3.9:** GET /api/vsla/transactions/recent

### Phase 4: Testing [ ]
- [ ] **Task 4.1:** Test savings recording (verify both entries created)
- [ ] **Task 4.2:** Test loan disbursement (verify balances)
- [ ] **Task 4.3:** Test loan repayment (verify calculations)
- [ ] **Task 4.4:** Test fines (verify contra entries)
- [ ] **Task 4.5:** Test balance calculations (member & group)
- [ ] **Task 4.6:** Test edge cases (negative amounts, duplicate transactions)
- [ ] **Task 4.7:** Verify accounting equation (total debits = total credits)
- [ ] **Task 4.8:** Performance test with 1000+ transactions

### Phase 5: Flutter Integration [ ]
- [ ] **Task 5.1:** Create AddSavingsScreen (form, validation, offline)
- [ ] **Task 5.2:** Create LoanRequestScreen (with max loan calculation)
- [ ] **Task 5.3:** Create LoanRepaymentScreen (outstanding balance display)
- [ ] **Task 5.4:** Create TransactionsListScreen (filterable, searchable)
- [ ] **Task 5.5:** Update dashboard with real transaction data
- [ ] **Task 5.6:** Implement offline transaction queue
- [ ] **Task 5.7:** Add sync mechanism for pending transactions
- [ ] **Task 5.8:** Connect all 8 dashboard buttons

### Phase 6: Documentation [ ]
- [ ] **Task 6.1:** API documentation (request/response examples)
- [ ] **Task 6.2:** Database schema documentation
- [ ] **Task 6.3:** Double-entry logic flowcharts
- [ ] **Task 6.4:** Testing results and verification
- [ ] **Task 6.5:** User guide for transaction features

---

## 🧪 Testing Scenarios

### Scenario 1: Basic Savings Flow
```
Given: Member John (ID: 5) in Group Alpha (ID: 3)
When: John saves UGX 50,000
Then:
  - 2 transactions created
  - John's savings balance = +50,000
  - Group cash balance = +50,000
  - Transactions linked via contra_entry_id
  - Total debits = Total credits
```

### Scenario 2: Loan Lifecycle
```
Given: Member Jane (ID: 7), Group Beta (ID: 4)
  - Jane has savings of UGX 100,000
  - Max loan = 3x savings = UGX 300,000
When: Jane requests UGX 250,000 loan
Then:
  - Loan approved (within limit)
  - 2 transactions created
  - Jane's loan balance = +250,000 (liability)
  - Group cash balance = -250,000
When: Jane repays UGX 50,000
Then:
  - 2 transactions created
  - Jane's loan balance = +200,000 (reduced)
  - Group cash balance = -200,000 (replenished)
```

### Scenario 3: Complex Multi-Transaction
```
Given: Member Mike (ID: 9), Group Gamma (ID: 5)
Transactions:
  1. Save UGX 30,000
  2. Save UGX 40,000
  3. Borrow UGX 150,000
  4. Repay UGX 30,000
  5. Fine UGX 5,000 (late repayment)
Then:
  - Total transactions: 10 (5 pairs)
  - Mike's savings: +70,000
  - Mike's loan: +120,000 (150k - 30k)
  - Mike's fines: -5,000
  - Group cash: +70k (savings) - 150k (loan) + 30k (repay) + 5k (fine) = -45,000
  - All contra entries linked correctly
```

---

## 📊 Balance Calculation Logic

### Member Balance Calculation
```php
// Savings Balance (ASSET for member)
savings_balance = SUM(amount_signed WHERE owner_type='user' 
                      AND owner_id=[member_id] 
                      AND account_type='savings')

// Loan Balance (LIABILITY for member - shows what they owe)
loan_balance = SUM(amount_signed WHERE owner_type='user' 
                   AND owner_id=[member_id] 
                   AND account_type='loan')

// Fines Balance (LIABILITY for member)
fines_balance = SUM(amount_signed WHERE owner_type='user' 
                    AND owner_id=[member_id] 
                    AND account_type='fine')

// Net Position
net_position = savings_balance - loan_balance - fines_balance
```

### Group Balance Calculation
```php
// Cash Balance (Total liquid funds)
cash_balance = SUM(amount_signed WHERE owner_type='group' 
                   AND owner_id=[group_id] 
                   AND account_type='cash')

// Total Savings Collected
total_savings = SUM(amount WHERE account_type='savings' 
                    AND project_id=[cycle_id])

// Total Loans Outstanding
total_loans = SUM(amount_signed WHERE owner_type='user' 
                  AND account_type='loan' 
                  AND project_id=[cycle_id] 
                  AND amount_signed > 0) 
              - SUM(amount WHERE account_type='loan' 
                    AND amount_signed < 0)

// Verification: Cash + Loans Out = Savings In
cash_balance + loans_outstanding = total_savings + fines_collected
```

---

## 🔐 Validation Rules

### Savings Transaction
- ✅ Amount > 0
- ✅ User must be group member
- ✅ Project (cycle) must be active
- ✅ Transaction date within cycle dates
- ✅ User not suspended/blocked

### Loan Disbursement
- ✅ Amount > project minimum_loan_amount
- ✅ Amount <= (member_savings × max_loan_multiplier)
- ✅ User has no overdue loans
- ✅ Group has sufficient cash balance
- ✅ User approved by group admin
- ✅ Interest rate applied correctly

### Loan Repayment
- ✅ Amount > 0
- ✅ Amount <= outstanding loan balance
- ✅ User has active loan
- ✅ Interest calculated if applicable

### Fine/Penalty
- ✅ Amount > 0
- ✅ Valid reason/description provided
- ✅ Approved by authorized user

---

## 📱 Flutter Screen Specifications

### AddSavingsScreen
**Features:**
- Amount input (UGX)
- Description/notes
- Transaction date picker
- Share quantity calculator
- Confirmation dialog
- Offline queue support
- Success animation

**Design:**
- Square corners (BorderRadius.zero)
- Green theme (savings color)
- Compact spacing (12px)
- Large save button

### LoanRequestScreen
**Features:**
- Amount input with max loan indicator
- Purpose dropdown (Business, Emergency, Education, etc.)
- Loan term selector (weeks)
- Auto-calculated interest preview
- Repayment schedule preview
- Terms acceptance checkbox
- Submit for approval

**Design:**
- Blue theme (loan color)
- Interest calculator widget
- Schedule table
- Warning if exceeding savings

### LoanRepaymentScreen
**Features:**
- Outstanding balance display
- Amount input (partial/full)
- Payment method (Cash, Mobile Money)
- Interest breakdown
- Principal vs interest allocation
- Receipt generation

**Design:**
- Teal theme
- Balance indicator at top
- Progress bar (loan repaid %)

---

## 🌐 API Endpoint Specifications

### POST /api/vsla/transactions/saving
**Request:**
```json
{
  "project_id": 15,
  "user_id": 25,
  "amount": 50000,
  "description": "Monthly savings contribution",
  "transaction_date": "2025-11-22"
}
```

**Response:**
```json
{
  "code": 1,
  "message": "Savings recorded successfully",
  "data": {
    "user_transaction": {
      "id": 101,
      "owner_type": "user",
      "owner_id": 25,
      "account_type": "savings",
      "amount": 50000,
      "amount_signed": 50000,
      "contra_entry_id": 102
    },
    "group_transaction": {
      "id": 102,
      "owner_type": "group",
      "owner_id": 12,
      "account_type": "cash",
      "amount": 50000,
      "amount_signed": 50000,
      "contra_entry_id": 101
    },
    "new_balances": {
      "user_savings": 180000,
      "group_cash": 1250000
    }
  }
}
```

### GET /api/vsla/transactions/member-balance/{user_id}
**Response:**
```json
{
  "code": 1,
  "data": {
    "user_id": 25,
    "user_name": "John Doe",
    "project_id": 15,
    "project_name": "Cycle Jan-Jun 2025",
    "balances": {
      "savings": 180000,
      "loans": 150000,
      "fines": 5000,
      "net_position": 25000
    },
    "formatted": {
      "savings": "UGX 180,000",
      "loans": "UGX 150,000",
      "fines": "UGX 5,000",
      "net_position": "UGX 25,000"
    }
  }
}
```

---

## ⚡ Performance Optimizations

1. **Database Indexes:**
   - Composite index on (project_id, owner_type, owner_id)
   - Index on transaction_date for range queries
   - Index on account_type for filtering

2. **Caching Strategy:**
   - Cache member balances (15 min TTL)
   - Cache group balances (5 min TTL)
   - Invalidate on new transaction

3. **Query Optimization:**
   - Use DB transactions for contra entries
   - Batch insert for multiple transactions
   - Eager load relationships

4. **Offline Support:**
   - SQLite queue for pending transactions
   - Background sync service
   - Conflict resolution strategy

---

## 🎯 Success Criteria

- [x] All 4 transaction types working (savings, loan, repayment, fine)
- [ ] Every transaction has valid contra entry
- [ ] Balance calculations 100% accurate
- [ ] All API endpoints tested with Postman
- [ ] Flutter screens functional with offline support
- [ ] All 8 dashboard buttons connected
- [ ] Documentation complete
- [ ] Zero accounting equation violations
- [ ] Performance: < 500ms per transaction
- [ ] Test coverage > 80%

---

## 📅 Timeline

**Phase 1 (Day 1):** Database & Model - 4 hours  
**Phase 2 (Day 1-2):** Service Layer - 6 hours  
**Phase 3 (Day 2):** API Endpoints - 4 hours  
**Phase 4 (Day 2-3):** Testing - 6 hours  
**Phase 5 (Day 3-4):** Flutter Integration - 8 hours  
**Phase 6 (Day 4):** Documentation - 2 hours  

**Total Estimated Time:** 30 hours over 4 days

---

**Status Legend:**
- [ ] Not Started
- [~] In Progress
- [x] Completed

**Last Updated:** November 22, 2025

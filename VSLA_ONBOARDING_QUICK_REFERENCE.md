# VSLA Onboarding System - Quick Reference Guide

## 🎯 Complete System Overview

### Frontend (Flutter - 7 Screens)
```
lib/screens/vsla/
├── VslaWelcomeScreen.dart (568 lines) - Step 1
├── VslaPrivacyTermsScreen.dart (703 lines) - Step 2  
├── VslaRegistrationScreen.dart (624 lines) - Step 3
├── VslaGroupCreationScreen.dart (716 lines) - Step 4
├── VslaMainMembersScreen.dart (756 lines) - Step 5
├── VslaSavingsCycleScreen.dart (798 lines) - Step 6
└── VslaCompleteScreen.dart (616 lines) - Step 7
```

### Backend (Laravel - Controller + Routes)
```
app/Http/Controllers/VslaOnboardingController.php (787 lines)
routes/api.php (lines 70-87)
```

### Database (3 Migrations - All Executed ✅)
```
2025_11_22_000001_add_vsla_onboarding_fields_to_users.php (86.30ms)
2025_11_22_000002_add_vsla_specific_fields_to_ffs_groups.php (100.51ms)
2025_11_22_000003_add_vsla_savings_cycle_fields_to_projects.php (107.25ms)
```

---

## 📡 API Endpoints (7 Total)

### Public Endpoints (No Auth Required)
```
GET  /api/vsla-onboarding/config           → Get configuration data
POST /api/vsla-onboarding/register-admin   → Register group admin
```

### Protected Endpoints (Require: JWT Token + User-Id Header)
```
GET  /api/vsla-onboarding/status            → Get onboarding progress
POST /api/vsla-onboarding/create-group      → Create VSLA group
POST /api/vsla-onboarding/register-main-members → Register secretary/treasurer
POST /api/vsla-onboarding/create-cycle      → Create savings cycle
POST /api/vsla-onboarding/complete          → Complete onboarding
```

---

## 🔑 Authentication Requirements

### Headers for Protected Endpoints
```bash
Authorization: Bearer {JWT_TOKEN}
User-Id: {USER_ID}
Content-Type: multipart/form-data
```

### Form Data (All Protected Requests)
```bash
user: {USER_ID}
User-Id: {USER_ID}
{...endpoint-specific fields}
```

---

## 🧪 Running Tests

### Quick Test
```bash
cd /Applications/MAMP/htdocs/fao-ffs-mis-api
./test_vsla_api.sh
```

### Expected Output
```
✓ ALL TESTS PASSED!
Tests Passed: 25
Tests Failed: 0
```

### Test Individual Endpoint
```bash
# Test config endpoint
curl http://localhost:8888/fao-ffs-mis-api/api/vsla-onboarding/config | jq

# Test with authentication
TOKEN="your_jwt_token"
USER_ID="user_id"
curl -X POST http://localhost:8888/fao-ffs-mis-api/api/vsla-onboarding/create-group \
  -H "Authorization: Bearer $TOKEN" \
  -H "User-Id: $USER_ID" \
  -F "user=$USER_ID" \
  -F "name=Test Group" \
  -F "description=Test Description" \
  -F "meeting_frequency=Weekly" \
  -F "establishment_date=2025-01-01" \
  -F "district_id=1" \
  -F "estimated_members=25" \
  | jq
```

---

## 📊 Database Fields

### Users Table - VSLA Fields
```sql
is_group_admin             ENUM('Yes','No')
is_group_secretary         ENUM('Yes','No')
is_group_treasurer         ENUM('Yes','No')
onboarding_step            VARCHAR(50)
onboarding_completed_at    TIMESTAMP
last_onboarding_step_at    TIMESTAMP
```

### FFS Groups Table - VSLA Fields
```sql
establishment_date         DATE
estimated_members          INT
admin_id                   INT
secretary_id               INT
treasurer_id               INT
subcounty_text            VARCHAR(100)
parish_text               VARCHAR(100)
```

### Projects Table - Cycle Fields
```sql
is_vsla_cycle              ENUM('Yes','No')
group_id                   INT
cycle_name                 VARCHAR(255)
share_value                DECIMAL(10,2)
meeting_frequency          VARCHAR(50)
loan_interest_rate         DECIMAL(5,2)
interest_frequency         VARCHAR(50)
weekly_loan_interest_rate  DECIMAL(5,2)
monthly_loan_interest_rate DECIMAL(5,2)
minimum_loan_amount        DECIMAL(10,2)
maximum_loan_multiple      INT
late_payment_penalty       DECIMAL(5,2)
is_active_cycle            ENUM('Yes','No')
```

---

## 🔄 Onboarding Flow

```
Step 1: Welcome Screen (UI only, no API)
   ↓
Step 2: Privacy/Terms (UI only, no API)
   ↓
Step 3: Registration → POST /register-admin
   ↓   Returns: JWT token, user with is_group_admin='Yes'
   ↓   onboarding_step: 'step_3_registration'
   ↓
Step 4: Group Creation → POST /create-group
   ↓   Creates: FfsGroup with admin_id
   ↓   onboarding_step: 'step_4_group'
   ↓
Step 5: Main Members → POST /register-main-members
   ↓   Creates: Secretary + Treasurer users
   ↓   Updates: group.secretary_id, group.treasurer_id
   ↓   onboarding_step: 'step_5_members'
   ↓
Step 6: Savings Cycle → POST /create-cycle
   ↓   Creates: Project with is_vsla_cycle='Yes'
   ↓   onboarding_step: 'step_6_cycle'
   ↓
Step 7: Complete → POST /complete
   ↓   Returns: Full summary (group, officers, cycle)
   ↓   onboarding_step: 'step_7_complete'
   ↓   onboarding_completed_at: timestamp
   ↓
   ✅ COMPLETED
```

---

## 🐛 Common Issues & Solutions

### Issue: "User ID is required in headers"
**Solution:** Add `User-Id` header to request
```bash
-H "User-Id: ${USER_ID}"
```

### Issue: "You must be logged in"
**Solution:** Ensure both JWT token AND User-Id header are sent
```bash
-H "Authorization: Bearer ${TOKEN}"
-H "User-Id: ${USER_ID}"
-F "user=${USER_ID}"
```

### Issue: "send_sms must be true or false"
**Solution:** Use 0/1 instead of false/true in form-data
```bash
-F "send_sms=0"  # Not "send_sms=false"
```

### Issue: "Data truncated for column 'status'"
**Solution:** Use enum values: 'ongoing', 'completed', 'on_hold'
```php
$cycle->status = 'ongoing';  // Not 'Active'
```

### Issue: Route not found
**Solution:** Verify route name is `/register-main-members` (not `/register-members`)

---

## 📝 Field Name Checklist

### ✅ Verified Matching Fields
- [x] is_group_admin (not isGroupAdmin)
- [x] is_group_secretary (not isGroupSecretary)
- [x] is_group_treasurer (not isGroupTreasurer)
- [x] onboarding_step (not onboardingStep)
- [x] meeting_frequency (not meetingFrequency)
- [x] estimated_members (not estimatedMembers)
- [x] share_value (not shareValue)
- [x] loan_interest_rate (not loanInterestRate)
- [x] minimum_loan_amount (not minimumLoanAmount)
- [x] maximum_loan_multiple (not maximumLoanMultiple)

---

## 🚀 Deployment Checklist

### Pre-Deployment
- [x] Run migrations: `php artisan migrate`
- [x] Check migration status: `php artisan migrate:status`
- [x] Test all endpoints: `./test_vsla_api.sh`
- [x] Verify routes: `php artisan route:list --path=vsla-onboarding`
- [x] Check file permissions on uploads directory
- [x] Clear cache: `php artisan cache:clear`
- [x] Clear config: `php artisan config:clear`

### Post-Deployment
- [ ] Test onboarding flow in production
- [ ] Verify SMS sending (if enabled)
- [ ] Check error logs
- [ ] Monitor database for correct data
- [ ] Test mobile app integration

---

## 📞 Integration with Mobile App

### Main App Entry Points
```dart
// Option 1: From BoardingWelcomeScreen
onTap: () => Get.to(() => VslaWelcomeScreen())

// Option 2: From home_tab.dart Quick Action
onTap: () => Get.to(() => VslaWelcomeScreen())
```

### API Client Configuration
```dart
// lib/utils/Utils.dart
static Future<dynamic> http_post(String path, Map<String, dynamic> body) async {
  // Automatically adds:
  // - Authorization: Bearer {token}
  // - User-Id: {userId}
  // - Form-data format
}
```

---

## 📚 Documentation Files

### Main Documentation
- `VSLA_API_TEST_RESULTS_COMPLETE.md` - Comprehensive test results
- `VSLA_ONBOARDING_QUICK_REFERENCE.md` - This file
- `test_vsla_api.sh` - Automated test script

### Code Files
- Controller: `app/Http/Controllers/VslaOnboardingController.php`
- Routes: `routes/api.php` (lines 70-87)
- Middleware: `app/Http/Middleware/EnsureTokenIsValid.php`
- Migrations: `database/migrations/2025_11_22_*`

### Frontend Files
- Screens: `lib/screens/vsla/*.dart` (7 files)
- Utils: `lib/utils/Utils.dart`
- Models: `lib/models/LoggedInUserModel.dart`

---

## 🎓 Key Learnings

1. **Always use snake_case** for database fields (not camelCase)
2. **ENUM values** must match exactly (case-sensitive)
3. **Form-data** requires `-F` flag in curl (not `-d`)
4. **Boolean values** in form-data: use 0/1 (not false/true strings)
5. **Phone numbers** auto-normalize to +256 format
6. **Group codes** auto-generate: {DIST}-VSLA-{YY}-{NUM}
7. **Member codes** auto-generate: XXX-MEM-{YY}-{NUM}
8. **JWT + User-Id** both required for auth
9. **Migration naming** matters for order (timestamps)
10. **Test everything** with real HTTP requests (not assumptions)

---

## ✅ System Status

- **Backend:** ✅ 100% Complete & Tested
- **Frontend:** ✅ 100% Complete & Integrated
- **Database:** ✅ All Migrations Executed
- **Testing:** ✅ 25/25 Tests Passing
- **Integration:** ✅ Connected to Main App
- **Documentation:** ✅ Comprehensive

**Overall Status:** 🎉 **PRODUCTION READY**

---

**Last Updated:** November 22, 2025  
**Version:** 1.0.0  
**Test Status:** ALL TESTS PASSING (100%)

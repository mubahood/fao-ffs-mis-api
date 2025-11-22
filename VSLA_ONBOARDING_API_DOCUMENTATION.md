# VSLA Onboarding API Documentation

**Last Updated:** November 22, 2025  
**API Version:** 1.0  
**Base URL:** `{APP_URL}/api/vsla-onboarding`

---

## Table of Contents

1. [Overview](#overview)
2. [Onboarding Flow](#onboarding-flow)
3. [Authentication](#authentication)
4. [API Endpoints](#api-endpoints)
5. [Data Models](#data-models)
6. [Error Handling](#error-handling)
7. [Testing Guide](#testing-guide)

---

## Overview

The VSLA Onboarding API provides a complete step-by-step registration process for Village Savings and Loan Association (VSLA) groups. The system guides users through:

1. Welcome & Role Selection
2. Terms & Privacy Policy
3. User Registration (Group Admin)
4. Group Creation
5. Main Members Registration (Secretary & Treasurer)
6. Savings Cycle Setup
7. Onboarding Completion

### Key Features

- ✅ Step-by-step guided process
- ✅ Progress tracking and resume capability
- ✅ Automatic SMS credential delivery
- ✅ Single active cycle per group enforcement
- ✅ Role-based access control
- ✅ Comprehensive validation

---

## Onboarding Flow

```
┌─────────────────────────────────────────────────────────────┐
│  STEP 1: Welcome Screen (Frontend)                          │
│  - Introduction to FAO FFS-MIS VSLA                         │
│  - Role selection: Individual vs Group Admin                │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 2: Terms & Privacy (Frontend)                         │
│  - VSLA-specific privacy policy                             │
│  - Terms of service                                          │
│  - Agreement checkbox                                        │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 3: User Registration (API)                            │
│  POST /vsla-onboarding/register-admin                       │
│  - Creates user account                                      │
│  - Marks as group admin                                      │
│  - Auto-login with JWT token                                │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 4: Group Creation (API)                               │
│  POST /vsla-onboarding/create-group                         │
│  - Creates VSLA group                                        │
│  - Generates unique group code                               │
│  - Links admin to group                                      │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 5: Main Members Registration (API)                    │
│  POST /vsla-onboarding/register-members                     │
│  - Registers secretary                                       │
│  - Registers treasurer                                       │
│  - Sends SMS credentials                                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 6: Savings Cycle Setup (API)                          │
│  POST /vsla-onboarding/create-cycle                         │
│  - Creates savings cycle (Project)                           │
│  - Configures loan settings                                  │
│  - Marks as active cycle                                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│  STEP 7: Completion (API)                                   │
│  POST /vsla-onboarding/complete                             │
│  - Marks onboarding complete                                 │
│  - Returns summary data                                      │
│  - Redirects to dashboard                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## Authentication

### Public Endpoints (No Auth Required)

- `GET /vsla-onboarding/config`
- `POST /vsla-onboarding/register-admin`

### Protected Endpoints (JWT Token Required)

All other endpoints require Bearer token in header:

```
Authorization: Bearer {JWT_TOKEN}
```

Token is returned after successful registration in Step 3.

---

## API Endpoints

### 1. Get Onboarding Configuration

**Endpoint:** `GET /vsla-onboarding/config`  
**Auth:** Not required  
**Description:** Returns dropdown data for districts, meeting frequencies, etc.

#### Response

```json
{
  "code": 1,
  "message": "Onboarding configuration retrieved successfully",
  "data": {
    "districts": [
      {
        "id": 1,
        "name": "Kampala"
      }
    ],
    "meeting_frequencies": {
      "Weekly": "Weekly",
      "Bi-weekly": "Bi-weekly",
      "Monthly": "Monthly"
    },
    "interest_frequencies": {
      "Weekly": "Weekly",
      "Monthly": "Monthly"
    },
    "loan_multiples": {
      "5": "5x Share Value",
      "10": "10x Share Value",
      "15": "15x Share Value",
      "20": "20x Share Value",
      "25": "25x Share Value",
      "30": "30x Share Value"
    }
  }
}
```

---

### 2. Register Group Admin (Step 3)

**Endpoint:** `POST /vsla-onboarding/register-admin`  
**Auth:** Not required  
**Description:** Creates new user account and marks as group admin. Auto-login included.

#### Request Body

```json
{
  "name": "John Doe",
  "phone_number": "0701234567",
  "email": "john@example.com",
  "password": "secure123",
  "password_confirmation": "secure123",
  "country": "Uganda"
}
```

#### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| name | string | Yes | min:3, max:255 |
| phone_number | string | Yes | Uganda format: 07XXXXXXXX or +2567XXXXXXXX, unique |
| email | string | No | Valid email, unique |
| password | string | Yes | min:4, confirmed |
| country | string | No | Default: Uganda |

#### Success Response

```json
{
  "code": 1,
  "message": "Registration successful! You are now logged in as a group admin.",
  "data": {
    "user": {
      "id": 123,
      "name": "John Doe",
      "phone_number": "+256701234567",
      "email": "john@example.com",
      "is_group_admin": "Yes",
      "onboarding_step": "step_3_registration",
      "status": "Active"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

#### Error Response

```json
{
  "code": 0,
  "message": "The phone number has already been taken."
}
```

---

### 3. Create VSLA Group (Step 4)

**Endpoint:** `POST /vsla-onboarding/create-group`  
**Auth:** Required (Bearer token)  
**Description:** Creates VSLA group and links to admin.

#### Request Body

```json
{
  "name": "Karamoja Savings Group",
  "description": "We are a community savings group focused on financial empowerment",
  "meeting_frequency": "Weekly",
  "establishment_date": "2024-01-15",
  "district_id": 45,
  "estimated_members": 25,
  "subcounty_text": "Moroto Town Council",
  "parish_text": "Central Ward",
  "village": "Katanga Village"
}
```

#### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| name | string | Yes | min:3, max:255 |
| description | string | Yes | min:10 |
| meeting_frequency | string | Yes | Weekly, Bi-weekly, or Monthly |
| establishment_date | date | Yes | Before or equal to today |
| district_id | integer | Yes | Must exist in locations table |
| estimated_members | integer | Yes | min:10, max:50 |
| subcounty_text | string | No | max:100 |
| parish_text | string | No | max:100 |
| village | string | No | max:100 |

#### Success Response

```json
{
  "code": 1,
  "message": "VSLA group created successfully!",
  "data": {
    "group": {
      "id": 45,
      "name": "Karamoja Savings Group",
      "type": "VSLA",
      "code": "MOR-VSLA-25-0001",
      "description": "We are a community savings group...",
      "meeting_frequency": "Weekly",
      "establishment_date": "2024-01-15",
      "district_id": 45,
      "estimated_members": 25,
      "admin_id": 123,
      "status": "Active"
    },
    "user": {
      "id": 123,
      "onboarding_step": "step_4_group",
      "group_id": 45
    }
  }
}
```

---

### 4. Register Main Members (Step 5)

**Endpoint:** `POST /vsla-onboarding/register-members`  
**Auth:** Required (Bearer token)  
**Description:** Registers secretary and treasurer. Creates accounts and sends SMS credentials.

#### Request Body

```json
{
  "secretary_name": "Jane Smith",
  "secretary_phone": "0702222222",
  "secretary_email": "jane@example.com",
  "treasurer_name": "Bob Johnson",
  "treasurer_phone": "0703333333",
  "treasurer_email": "bob@example.com",
  "send_sms": true
}
```

#### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| secretary_name | string | Yes | min:3, max:255 |
| secretary_phone | string | Yes | Uganda format, different from admin |
| secretary_email | string | No | Valid email |
| treasurer_name | string | Yes | min:3, max:255 |
| treasurer_phone | string | Yes | Uganda format, different from secretary |
| treasurer_email | string | No | Valid email |
| send_sms | boolean | No | Default: true |

#### Success Response

```json
{
  "code": 1,
  "message": "Main members registered successfully!",
  "data": {
    "secretary": {
      "id": 124,
      "name": "Jane Smith",
      "phone_number": "+256702222222",
      "is_group_secretary": "Yes",
      "group_id": 45
    },
    "treasurer": {
      "id": 125,
      "name": "Bob Johnson",
      "phone_number": "+256703333333",
      "is_group_treasurer": "Yes",
      "group_id": 45
    },
    "group": {
      "id": 45,
      "secretary_id": 124,
      "treasurer_id": 125
    },
    "sms_sent": true,
    "sms_results": {
      "secretary": {
        "success": true,
        "phone": "+256702222222",
        "message": "SMS sent successfully"
      },
      "treasurer": {
        "success": true,
        "phone": "+256703333333",
        "message": "SMS sent successfully"
      }
    }
  }
}
```

#### SMS Content Example

```
Welcome to Karamoja Savings Group! You have been appointed as Secretary.

Login Details:
Phone: +256702222222
Password: aB3dEf7h

Download FAO FFS-MIS app to get started.
```

---

### 5. Create Savings Cycle (Step 6)

**Endpoint:** `POST /vsla-onboarding/create-cycle`  
**Auth:** Required (Bearer token)  
**Description:** Creates VSLA savings cycle with loan settings. Stored as Project in backend.

#### Request Body

```json
{
  "cycle_name": "Karamoja 2025 Cycle 1",
  "start_date": "2025-01-01",
  "end_date": "2025-12-31",
  "share_value": 5000,
  "meeting_frequency": "Weekly",
  "loan_interest_rate": 10,
  "interest_frequency": "Monthly",
  "weekly_loan_interest_rate": null,
  "monthly_loan_interest_rate": 10,
  "minimum_loan_amount": 50000,
  "maximum_loan_multiple": 20,
  "late_payment_penalty": 5
}
```

#### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| cycle_name | string | Yes | min:3, max:200 |
| start_date | date | Yes | Valid date |
| end_date | date | Yes | After start_date |
| share_value | decimal | Yes | min:1000, max:100000 |
| meeting_frequency | string | Yes | Weekly, Bi-weekly, or Monthly |
| loan_interest_rate | decimal | Yes | min:0, max:100 |
| interest_frequency | string | Yes | Weekly or Monthly |
| weekly_loan_interest_rate | decimal | Conditional | Required if interest_frequency is Weekly |
| monthly_loan_interest_rate | decimal | Conditional | Required if interest_frequency is Monthly |
| minimum_loan_amount | decimal | Yes | min:1000 |
| maximum_loan_multiple | integer | Yes | min:5, max:30 |
| late_payment_penalty | decimal | Yes | min:0, max:50 |

#### Success Response

```json
{
  "code": 1,
  "message": "Savings cycle created successfully!",
  "data": {
    "cycle": {
      "id": 78,
      "title": "Karamoja 2025 Cycle 1",
      "start_date": "2025-01-01",
      "end_date": "2025-12-31",
      "is_vsla_cycle": "Yes",
      "is_active_cycle": "Yes",
      "group_id": 45,
      "cycle_name": "Karamoja 2025 Cycle 1",
      "share_value": 5000.00,
      "meeting_frequency": "Weekly",
      "loan_interest_rate": 10.00,
      "interest_frequency": "Monthly",
      "monthly_loan_interest_rate": 10.00,
      "minimum_loan_amount": 50000.00,
      "maximum_loan_multiple": 20,
      "late_payment_penalty": 5.00,
      "status": "active"
    },
    "group": {
      "id": 45,
      "cycle_number": 1,
      "cycle_start_date": "2025-01-01",
      "cycle_end_date": "2025-12-31"
    },
    "user": {
      "id": 123,
      "onboarding_step": "step_6_cycle"
    }
  }
}
```

---

### 6. Complete Onboarding (Step 7)

**Endpoint:** `POST /vsla-onboarding/complete`  
**Auth:** Required (Bearer token)  
**Description:** Marks onboarding as complete and returns summary.

#### Request Body

```json
{}
```

No body required.

#### Success Response

```json
{
  "code": 1,
  "message": "Congratulations! Your VSLA group setup is complete.",
  "data": {
    "user": {
      "id": 123,
      "onboarding_step": "step_7_complete",
      "onboarding_completed_at": "2025-11-22T14:30:00.000000Z"
    },
    "group": {
      "id": 45,
      "name": "Karamoja Savings Group",
      "code": "MOR-VSLA-25-0001"
    },
    "secretary": {
      "id": 124,
      "name": "Jane Smith",
      "phone_number": "+256702222222"
    },
    "treasurer": {
      "id": 125,
      "name": "Bob Johnson",
      "phone_number": "+256703333333"
    },
    "cycle": {
      "id": 78,
      "cycle_name": "Karamoja 2025 Cycle 1",
      "share_value": 5000.00
    },
    "summary": {
      "group_name": "Karamoja Savings Group",
      "group_code": "MOR-VSLA-25-0001",
      "total_members": 25,
      "meeting_frequency": "Weekly",
      "cycle_name": "Karamoja 2025 Cycle 1",
      "share_value": 5000,
      "cycle_duration": "12 months"
    }
  }
}
```

---

### 7. Get Onboarding Status

**Endpoint:** `GET /vsla-onboarding/status`  
**Auth:** Required (Bearer token)  
**Description:** Returns current onboarding progress for logged-in user.

#### Success Response

```json
{
  "code": 1,
  "message": "Onboarding status retrieved successfully",
  "data": {
    "current_step": "step_4_group",
    "is_complete": false,
    "completed_at": null,
    "last_step_at": "2025-11-22T14:15:00.000000Z",
    "user": {
      "id": 123,
      "name": "John Doe",
      "is_group_admin": "Yes"
    },
    "group": {
      "id": 45,
      "name": "Karamoja Savings Group"
    },
    "secretary": null,
    "treasurer": null,
    "cycle": null
  }
}
```

---

## Data Models

### User Model (Extended)

```php
// New VSLA fields
is_group_admin: enum('Yes', 'No')
is_group_secretary: enum('Yes', 'No')
is_group_treasurer: enum('Yes', 'No')
onboarding_step: enum(
    'not_started',
    'step_1_welcome',
    'step_2_terms',
    'step_3_registration',
    'step_4_group',
    'step_5_members',
    'step_6_cycle',
    'step_7_complete'
)
onboarding_completed_at: timestamp|null
last_onboarding_step_at: timestamp|null
```

### FfsGroup Model (Extended)

```php
// New VSLA fields
establishment_date: date|null
estimated_members: integer|null
admin_id: bigInteger|null
secretary_id: bigInteger|null
treasurer_id: bigInteger|null
subcounty_text: string(100)|null
parish_text: string(100)|null
```

### Project Model (Extended - VSLA Cycle)

```php
// New VSLA Cycle fields
is_vsla_cycle: enum('Yes', 'No')
group_id: bigInteger|null
cycle_name: string(200)|null
share_value: decimal(15,2)|null
meeting_frequency: enum('Weekly', 'Bi-weekly', 'Monthly')|null
loan_interest_rate: decimal(5,2)|null
interest_frequency: enum('Weekly', 'Monthly')|null
weekly_loan_interest_rate: decimal(5,2)|null
monthly_loan_interest_rate: decimal(5,2)|null
minimum_loan_amount: decimal(15,2)|null
maximum_loan_multiple: integer|null
late_payment_penalty: decimal(5,2)|null
is_active_cycle: enum('Yes', 'No')
```

---

## Error Handling

### Standard Error Response

```json
{
  "code": 0,
  "message": "Error description here"
}
```

### Common Errors

| Status | Error Message | Cause |
|--------|---------------|-------|
| 400 | "The phone number has already been taken." | Duplicate phone |
| 400 | "You already have an active VSLA group: {name}" | Admin trying to create 2nd group |
| 401 | "You must be logged in" | Missing/invalid token |
| 403 | "Only group admins can create VSLA groups" | Non-admin trying protected action |
| 422 | Validation errors | Invalid input data |

---

## Testing Guide

### Prerequisites

1. Start MAMP server
2. Run migrations: `php artisan migrate`
3. Have Postman or similar API testing tool

### Test Sequence

#### 1. Get Configuration

```bash
GET {BASE_URL}/api/vsla-onboarding/config
```

Expected: Districts list and dropdowns

#### 2. Register Admin

```bash
POST {BASE_URL}/api/vsla-onboarding/register-admin
Content-Type: application/json

{
  "name": "Test Admin",
  "phone_number": "0701111111",
  "password": "test123",
  "password_confirmation": "test123"
}
```

Expected: User object + JWT token

**Save the token for subsequent requests!**

#### 3. Create Group

```bash
POST {BASE_URL}/api/vsla-onboarding/create-group
Authorization: Bearer {TOKEN}
Content-Type: application/json

{
  "name": "Test VSLA Group",
  "description": "This is a test group for development",
  "meeting_frequency": "Weekly",
  "establishment_date": "2024-01-01",
  "district_id": 1,
  "estimated_members": 20
}
```

Expected: Group object with code

#### 4. Register Members

```bash
POST {BASE_URL}/api/vsla-onboarding/register-members
Authorization: Bearer {TOKEN}
Content-Type: application/json

{
  "secretary_name": "Test Secretary",
  "secretary_phone": "0702222222",
  "treasurer_name": "Test Treasurer",
  "treasurer_phone": "0703333333",
  "send_sms": false
}
```

Expected: Secretary and treasurer objects

#### 5. Create Cycle

```bash
POST {BASE_URL}/api/vsla-onboarding/create-cycle
Authorization: Bearer {TOKEN}
Content-Type: application/json

{
  "cycle_name": "Test Cycle 2025",
  "start_date": "2025-01-01",
  "end_date": "2025-12-31",
  "share_value": 5000,
  "meeting_frequency": "Weekly",
  "loan_interest_rate": 10,
  "interest_frequency": "Monthly",
  "monthly_loan_interest_rate": 10,
  "minimum_loan_amount": 50000,
  "maximum_loan_multiple": 20,
  "late_payment_penalty": 5
}
```

Expected: Cycle (Project) object

#### 6. Complete Onboarding

```bash
POST {BASE_URL}/api/vsla-onboarding/complete
Authorization: Bearer {TOKEN}
```

Expected: Summary with all entities

#### 7. Check Status

```bash
GET {BASE_URL}/api/vsla-onboarding/status
Authorization: Bearer {TOKEN}
```

Expected: Complete onboarding data

---

## Important Notes

1. **Cycle = Project**: In frontend, users see "Savings Cycle", but in backend it's stored as `Project` with `is_vsla_cycle = 'Yes'`.

2. **Phone Number Format**: Always validate Uganda format: `07XXXXXXXX` or `+2567XXXXXXXX`

3. **One Active Cycle**: System enforces only one active cycle per group at a time.

4. **SMS Credentials**: Passwords are auto-generated (8 characters, alphanumeric) and sent via SMS.

5. **Group Codes**: Auto-generated as `{DISTRICT}-VSLA-{YEAR}-{NUMBER}` (e.g., `MOR-VSLA-25-0001`)

6. **Onboarding Steps**: Must be completed sequentially. Cannot skip steps.

7. **Admin as Chairperson**: The group admin is automatically the chairperson and cannot be changed during onboarding.

---

## Support

For issues or questions:
- Email: support@faoffsmis.org
- Phone: +256785918341

**End of Documentation**

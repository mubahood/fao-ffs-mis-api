# CSV User Import System - Complete Documentation

## Overview
A comprehensive CSV user import system for Laravel Admin that allows system administrators to import users in bulk with field mapping, validation, and error handling.

## Features
✅ CSV file upload with flexible column mapping  
✅ Real-time validation preview showing success/failure status  
✅ Phone number normalization and validation for Uganda  
✅ Automatic FFS Group creation if doesn't exist  
✅ Duplicate phone number detection  
✅ Transaction-based import (all-or-nothing safety)  
✅ Detailed error reporting per row  
✅ Professional UI with minimal styling  
✅ Import history tracking with statistics  

## Installation

### 1. Dependencies
```bash
composer require league/csv
```

### 2. Database Migration
```bash
php artisan migrate
```

### 3. Menu Configuration
Menu entry automatically added to: **System Administration > User Management > CSV User Import**

## Usage Guide

### Step 1: Prepare CSV File
Create a CSV file with your user data. The first row should contain column headers.

**Sample CSV Structure:**
```csv
Name,Phone,Email,Gender,Group Name,Role
John Doe,0700123456,john@example.com,Male,Mukono Farmers Group,member
Jane Smith,0750234567,jane@example.com,Female,Kampala FFS,chairperson
```

**Sample file included:** `sample-users-import.csv`

### Step 2: Create Import Task
1. Navigate to: **Admin Panel > System Administration > User Management > CSV User Import**
2. Click **"Create"**
3. Fill in the form:
   - **Task Name**: Descriptive name (e.g., "November 2025 User Import")
   - **Type**: `User Data` (readonly)
   - **File Path**: Upload your CSV file
   - **Column Mapping**: Map CSV columns (A, B, C...) to fields:
     - Name Column (Required)
     - Phone Column (Required)
     - Group Column (Required)
     - Gender Column (Optional)
     - Email Column (Optional)
     - Role Column (Optional)
4. Click **"Submit"**

### Step 3: Validate Import
1. Return to the import tasks list
2. Find your task (Status: **Pending**)
3. Click **"Validate"** button
4. Review the validation report:
   - Summary cards show: Total rows, Valid rows, Invalid rows, Success rate
   - Table shows each row with status and errors
   - Valid rows appear in **green**
   - Invalid rows appear in **red** with error details

### Step 4: Process Import
1. If validation looks good, click **"Start Import"**
2. System will:
   - Set execution limits (unlimited time, 512MB memory)
   - Process each valid row
   - Create missing FFS Groups automatically
   - Create users with normalized data
   - Track imported/failed counts
3. View completion screen with statistics

## Field Requirements

### Required Fields
- ✅ **Name**: User's full name (cannot be empty)
- ✅ **Phone**: Valid Uganda phone number (12 digits, starts with 256)
- ✅ **Group**: FFS Group name (will be created if doesn't exist)

### Optional Fields
- **Email**: Valid email address
- **Gender**: Male/Female (auto-normalized: m/man/male → Male, f/woman/female → Female)
- **Role**: User role (member, chairperson, vice-chairperson, secretary, treasurer, etc.)

## Phone Number Validation

### Accepted Formats
```
0700123456  → 256700123456 (normalized)
+256700123456 → 256700123456
256700123456 → 256700123456 (valid)
```

### Valid Operator Prefixes
- **MTN**: 70, 75, 76, 77, 78, 79
- **Airtel**: 20, 25, 39
- **Africell**: 31

### Invalid Examples
- Too short: `070012345` ❌
- Invalid prefix: `256690123456` ❌
- Non-numeric: `+256 (700) 123-456` ❌ (will be normalized)
- Duplicate: Phone already exists in database ❌

## Default Values

When users are created, the following defaults are applied:

```php
'username' => $normalizedPhone,          // Phone number as username
'password' => bcrypt('12345678'),        // Default password
'status' => 'Active',                    // User active by default
'avatar' => 'default.png',               // Default avatar
'created_at' => now(),
'updated_at' => now(),
```

**⚠️ IMPORTANT**: Inform users to change their password after first login!

## Column Mapping Guide

CSV columns are identified by letters (A, B, C, D, etc.):

| Letter | Index | Example Column |
|--------|-------|----------------|
| A | 1st column | Name |
| B | 2nd column | Phone |
| C | 3rd column | Email |
| D | 4th column | Gender |
| E | 5th column | Group Name |
| F | 6th column | Role |

**Example Mapping:**
- If your CSV has: `Full Name, Contact, Organization, Sex`
- Map as: Name=A, Phone=B, Group=C, Gender=D

## Import Task Statuses

| Status | Badge Color | Description |
|--------|-------------|-------------|
| **Pending** | Blue | Task created, awaiting validation |
| **Processing** | Orange | Import is currently running |
| **Completed** | Green | Import finished successfully |
| **Failed** | Red | Import encountered errors |

## Troubleshooting

### Common Errors

#### 1. "Phone number is required"
- **Cause**: Empty phone column
- **Solution**: Ensure all rows have phone numbers

#### 2. "Invalid Uganda phone number format"
- **Cause**: Phone doesn't match 256XXXXXXXXX pattern or invalid prefix
- **Solution**: Check phone format and operator prefix

#### 3. "Phone number already exists"
- **Cause**: Duplicate phone in database
- **Solution**: Remove duplicate or update existing user manually

#### 4. "Name is required"
- **Cause**: Empty name column
- **Solution**: Ensure all rows have names

#### 5. "Group is required"
- **Cause**: Empty group column
- **Solution**: Ensure all rows have group names

### Performance Issues

**Large Files (>1000 rows):**
- System automatically sets unlimited execution time
- Memory limit set to 512MB
- Import runs in single transaction (may take several minutes)

**Timeout Solutions:**
```bash
# Increase PHP limits in php.ini
max_execution_time = 0
memory_limit = 1024M
```

### Database Issues

**Foreign Key Constraint:**
- Migration uses `unsignedBigInteger` for `initiated_by` field
- No foreign key constraint to avoid migration failures

**Transaction Rollback:**
- If ANY error occurs during import, entire batch is rolled back
- Check error message and re-import after fixing

## API Endpoints

### Validation Endpoint
```
GET /import/validate/{id}
```
**Response:** Blade view with validation results

### Processing Endpoint
```
GET /import/process/{id}
```
**Response:** Blade view with import results

## Database Schema

### import_tasks Table
```sql
id                  BIGINT UNSIGNED AUTO_INCREMENT
task_name           VARCHAR(255) - Descriptive name
type                ENUM('user_data') - Import type
file_path           VARCHAR(255) - CSV file location
status              ENUM('pending','processing','completed','failed')
message             TEXT - Error messages or notes
initiated_by        BIGINT UNSIGNED - Admin user ID
mapping             JSON - Column mapping array
total_rows          INT UNSIGNED - Total CSV rows
imported_rows       INT UNSIGNED - Successfully imported
failed_rows         INT UNSIGNED - Failed imports
started_at          TIMESTAMP - Processing start time
completed_at        TIMESTAMP - Processing end time
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

## Security Considerations

1. **Password Default**: All users get password `12345678` - Force password reset
2. **Admin Only**: Import feature restricted to admin panel users
3. **Transaction Safety**: Rollback on error prevents partial imports
4. **File Upload**: CSV files stored in `storage/app/public/imports/YYYY/MM/`
5. **Duplicate Prevention**: Phone uniqueness enforced

## File Structure

```
app/
├── Admin/
│   └── Controllers/
│       └── ImportTaskController.php    # Admin CRUD interface
├── Http/
│   └── Controllers/
│       └── ImportController.php        # Validation & processing logic
└── Models/
    └── ImportTask.php                  # Eloquent model

database/
└── migrations/
    └── 2025_11_25_001144_create_import_tasks_table.php

resources/
└── views/
    └── imports/
        ├── validate.blade.php          # Validation preview
        ├── complete.blade.php          # Success screen
        └── error.blade.php             # Error screen

routes/
├── web.php                             # Import validation & processing routes
└── app/Admin/routes.php                # Admin resource routes
```

## Sample Import Workflow

### Example 1: Successful Import
```
1. Upload CSV: "november-farmers.csv" (50 rows)
2. Map Columns: Name=A, Phone=B, Group=E, Gender=D
3. Validate: 48 valid, 2 invalid (duplicate phones)
4. Fix CSV: Remove 2 duplicate rows
5. Re-upload: All 48 valid
6. Start Import: ✓ 48 users created, 2 new groups created
7. Result: Status=Completed, 48 imported, 0 failed
```

### Example 2: Failed Import
```
1. Upload CSV: "user-list.csv" (100 rows)
2. Map Columns: Name=A, Phone=B, Group=C
3. Validate: 95 valid, 5 invalid (missing phones)
4. Start Import: Begin processing...
5. Error: Database connection lost at row 47
6. Result: Status=Failed, transaction rolled back, 0 users created
7. Solution: Re-run import after fixing network issue
```

## Best Practices

1. **Test First**: Import 10-20 records to test mapping before bulk import
2. **Clean Data**: Remove empty rows, normalize phone formats in Excel first
3. **Unique Groups**: Use consistent group naming (case-sensitive)
4. **Backup Database**: Always backup before large imports
5. **Monitor Logs**: Check Laravel logs for detailed error traces
6. **Incremental Imports**: For 1000+ users, split into batches of 500
7. **Verify Results**: Check `users` and `ffs_groups` tables after import

## Testing

### Unit Test Example
```bash
php artisan test --filter ImportTaskTest
```

### Manual Testing Checklist
- ✅ Upload CSV with valid data
- ✅ Test invalid phone formats
- ✅ Test duplicate phone detection
- ✅ Test missing required fields
- ✅ Test group auto-creation
- ✅ Test transaction rollback on error
- ✅ Verify import statistics accuracy

## Support

**Documentation Updated**: 2025-11-25  
**Laravel Version**: 9.x/10.x  
**League CSV Version**: ^9.27  

For issues or questions, check:
- Laravel Admin docs: https://laravel-admin.org/
- League CSV docs: https://csv.thephpleague.com/

## Changelog

### v1.0.0 (2025-11-25)
- ✅ Initial release
- ✅ CSV upload with flexible column mapping
- ✅ Uganda phone validation
- ✅ Automatic group creation
- ✅ Transaction-safe imports
- ✅ Professional validation UI
- ✅ Import history tracking

---

**System ready for production use!** 🚀

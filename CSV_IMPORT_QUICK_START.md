# 🚀 CSV USER IMPORT - QUICK START GUIDE

## Access the System
**URL:** `/admin/import-tasks`  
**Menu:** System Administration → User Management → CSV User Import

---

## 📝 Quick Import (3 Steps)

### Step 1: Create Task
1. Click **"Create"**
2. Enter task name: `"Your Import Name"`
3. Upload CSV file
4. Map columns (A, B, C, D...):
   - **Name** (required)
   - **Phone** (required) 
   - **Group** (required)
   - Gender (optional)
   - Email (optional)
   - Role (optional)
5. Click **"Submit"**

### Step 2: Validate
1. Find your task in the list
2. Click **"Validate"** button
3. Review the preview:
   - ✅ Green rows = Valid
   - ❌ Red rows = Invalid (see errors)
4. Fix errors if needed

### Step 3: Import
1. Click **"Start Import"** button
2. Wait for processing
3. View results (imported/failed counts)

---

## 📋 CSV File Format

**Sample CSV:**
```csv
Name,Phone,Email,Gender,Group Name,Role
John Doe,0700123456,john@example.com,Male,Mukono Farmers,member
Jane Smith,0750234567,jane@example.com,Female,Kampala FFS,chairperson
```

**Column Mapping Example:**
- Column A = Name
- Column B = Phone  
- Column C = Email
- Column D = Gender
- Column E = Group Name
- Column F = Role

---

## ☎️ Phone Number Format

**Valid Formats:**
- `0700123456` → Auto-converts to `256700123456`
- `+256700123456` → Converts to `256700123456`
- `256700123456` → Already correct ✅

**Supported Networks:**
- **MTN**: 70, 75, 76, 77, 78, 79
- **Airtel**: 20, 25, 39
- **Africell**: 31

---

## ⚠️ Common Errors

| Error | Solution |
|-------|----------|
| Phone required | Add phone number to row |
| Invalid format | Use Uganda format (0700...) |
| Duplicate phone | Phone already exists in system |
| Name required | Add name to row |
| Group required | Add group name to row |

---

## 🔐 Default Settings

When users are imported:
- **Username**: Phone number
- **Password**: `12345678` (⚠️ Change after login!)
- **Status**: Active
- **Avatar**: default.png

---

## 📊 Status Badges

- 🔵 **Pending**: Ready to validate/import
- 🟠 **Processing**: Import in progress
- 🟢 **Completed**: Import successful
- 🔴 **Failed**: Import encountered errors

---

## 💡 Pro Tips

✅ Test with 10-20 records first  
✅ Clean data in Excel before uploading  
✅ Use consistent group names  
✅ Remove duplicate phones before import  
✅ For 1000+ users, split into batches of 500  
✅ Always backup database before large imports  

---

## 📞 Need Help?

📖 **Full Documentation:** `CSV_IMPORT_SYSTEM_COMPLETE.md`  
🔧 **Technical Details:** `CSV_IMPORT_IMPLEMENTATION_SUMMARY.md`  
📊 **Sample File:** `sample-users-import.csv`

---

**System Version:** 1.0.0  
**Last Updated:** 25 November 2025  
**Status:** ✅ Production Ready

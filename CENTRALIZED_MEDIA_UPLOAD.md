# Centralized Media Upload System

## Overview
A standardized media file upload system that handles all file types (images, audio, video, PDFs) consistently across the application.

## Location
**File:** `app/Models/Utils.php`  
**Method:** `Utils::uploadMedia()`

## Features
- ✅ Single unified upload function for all media types
- ✅ Stores all files in `public/storage/images/` directory
- ✅ Returns only filename (not full path) for database storage
- ✅ Built-in validation for file types and sizes
- ✅ Unique filename generation (timestamp-random.extension)
- ✅ Error handling with null return on failure

## Usage

### Basic Upload
```php
// Upload an image
$filename = Utils::uploadMedia($request->file('image'));

// Store only the filename in database
$model->image_url = $filename;
```

### With File Type Validation
```php
// Only allow specific image formats, max 5MB
$filename = Utils::uploadMedia(
    $request->file('image'), 
    ['jpeg', 'jpg', 'png'], 
    5
);

if ($filename) {
    $model->image_url = $filename;
}
```

### Different Media Types
```php
// Audio files (max 10MB)
$audioFile = Utils::uploadMedia(
    $request->file('audio'),
    ['mp3', 'wav', 'm4a'],
    10
);

// Video files (max 50MB)
$videoFile = Utils::uploadMedia(
    $request->file('video'),
    ['mp4', 'mov', 'avi'],
    50
);

// PDF documents (max 10MB)
$pdfFile = Utils::uploadMedia(
    $request->file('pdf'),
    ['pdf'],
    10
);
```

## Method Signature
```php
/**
 * @param \Illuminate\Http\UploadedFile $file - The uploaded file
 * @param array|null $allowedExtensions - Optional array of allowed extensions
 * @param int $maxSizeMB - Optional max file size in MB (default 10MB)
 * @return string|null - Returns filename on success, null on failure
 */
public static function uploadMedia($file, $allowedExtensions = null, $maxSizeMB = 10)
```

## File Storage
- **Upload Directory:** `public/storage/images/`
- **Filename Pattern:** `{timestamp}-{random}.{extension}`
  - Example: `1732951234-567890.jpg`
- **Database Storage:** Store only the filename (e.g., `1732951234-567890.jpg`)
- **Frontend Access:** Use `Utils.img(filename)` which prepends base URL

## URL Structure

### Backend (Laravel)
**Database stores:** `1732951234-567890.jpg` (filename only)

**API returns:** `images/1732951234-567890.jpg` (relative path)

```php
// In formatQuestion/formatAnswer methods
'image_url' => $question->has_image === 'Yes' 
    ? 'images/' . $question->image_url 
    : null
```

### Frontend (Flutter)
**API receives:** `images/1732951234-567890.jpg`

**Display with:** `Utils.img('images/1732951234-567890.jpg')`

**Result:** `http://base-url/images/1732951234-567890.jpg`

```dart
CachedNetworkImage(
  imageUrl: Utils.img(_question!.imageUrl),
  // Utils.img() prepends base URL automatically
)
```

## Implementation Example

### FarmerQuestionController.php
```php
// Old way (manual upload)
if ($request->hasFile('image')) {
    $file = $request->file('image');
    $ext = $file->getClientOriginalExtension();
    $file_name = time() . "-" . rand(100000, 1000000) . "." . $ext;
    $file->move(Utils::docs_root() . '/storage/images/', $file_name);
    $data['has_image'] = 'Yes';
    $data['image_url'] = $file_name;
}

// New way (centralized)
if ($request->hasFile('image')) {
    $file_name = Utils::uploadMedia($request->file('image'), ['jpeg', 'jpg', 'png'], 5);
    if ($file_name) {
        $data['has_image'] = 'Yes';
        $data['image_url'] = $file_name;
    }
}
```

## Validation

### File Types
Pass an array of allowed extensions (lowercase):
```php
// Images only
['jpeg', 'jpg', 'png']

// Audio only
['mp3', 'wav', 'm4a']

// Video only
['mp4', 'mov', 'avi']

// Documents
['pdf', 'doc', 'docx']

// Multiple types
['jpeg', 'png', 'pdf', 'mp4']

// Any type (null or omit parameter)
null
```

### File Size
Specify maximum file size in MB:
```php
Utils::uploadMedia($file, ['jpg'], 5);   // Max 5MB
Utils::uploadMedia($file, ['mp4'], 50);  // Max 50MB
Utils::uploadMedia($file, null, 10);     // Max 10MB (default)
```

## Error Handling
The function returns `null` on failure:
```php
$filename = Utils::uploadMedia($request->file('image'), ['jpg', 'png'], 5);

if ($filename) {
    // Success - save to database
    $model->image_url = $filename;
} else {
    // Failure - handle error
    // Possible reasons:
    // - Invalid file
    // - Wrong file type
    // - File too large
}
```

## Current Implementations
This centralized upload is currently used in:
- ✅ `FarmerQuestionController.php` - Question images and audio
- ✅ `FarmerQuestionController.php` - Answer images, audio, video, PDFs

## Migration Guide
To migrate existing upload code to use centralized system:

1. **Replace manual upload logic:**
```php
// Before
$file = $request->file('image');
$ext = $file->getClientOriginalExtension();
$file_name = time() . "-" . rand(100000, 1000000) . "." . $ext;
$file->move(Utils::docs_root() . '/storage/images/', $file_name);

// After
$file_name = Utils::uploadMedia($request->file('image'), ['jpeg', 'jpg', 'png'], 5);
if (!$file_name) return $this->error('Upload failed');
```

2. **Update format methods to return `images/` path:**
```php
// Before
'image_url' => url('storage/' . $question->image_url)
// or
'image_url' => 'storage/images/' . $question->image_url

// After
'image_url' => 'images/' . $question->image_url
```

3. **Frontend already uses Utils.img():**
```dart
// No changes needed - Utils.img() handles the path
CachedNetworkImage(
  imageUrl: Utils.img(question.imageUrl),
)
```

## Benefits
- **Consistency:** All uploads follow the same pattern
- **Maintainability:** Single place to update upload logic
- **Validation:** Built-in type and size checking
- **Clean URLs:** Simplified path structure (`images/` instead of `storage/images/`)
- **Frontend Compatible:** Works seamlessly with `Utils.img()` on mobile app
- **Error Handling:** Graceful failure with null returns

## Best Practices
1. Always check return value for null before saving to database
2. Use appropriate file size limits based on media type
3. Specify allowed extensions for security
4. Store only filename in database (not full path)
5. Let frontend handle URL construction with `Utils.img()`

## Notes
- Files are stored in `public/storage/images/` (physical location)
- Database stores filename only (e.g., `1732951234-567890.jpg`)
- API returns relative path (e.g., `images/1732951234-567890.jpg`)
- Frontend constructs full URL (e.g., `http://base-url/images/1732951234-567890.jpg`)
- This separation allows flexible base URL configuration on frontend

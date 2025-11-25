<?php

namespace App\Http\Controllers;

use App\Models\ImportTask;
use App\Models\User;
use App\Models\FfsGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportController extends Controller
{
    /**
     * Validate the CSV file and show preview
     */
    public function validateImport($id)
    {
        $task = ImportTask::findOrFail($id);
        
        if ($task->status !== 'pending') {
            return view('imports.error', [
                'message' => 'This import task has already been processed or is currently processing.'
            ]);
        }

        try {
            $filePath = storage_path('app/public/' . $task->file_path);
            
            if (!file_exists($filePath)) {
                return view('imports.error', [
                    'message' => 'CSV file not found.'
                ]);
            }

            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0); // First row is header
            
            $records = iterator_to_array($csv->getRecords());
            $mapping = $task->mapping;

            // Validate rows
            $validatedRows = [];
            $validCount = 0;
            $invalidCount = 0;

            foreach ($records as $index => $record) {
                $rowNumber = $index + 2; // +2 because of header and 0-based index
                $row = array_values($record);
                
                $validation = $this->validateRow($row, $mapping, $rowNumber);
                
                $validatedRows[] = [
                    'row_number' => $rowNumber,
                    'name' => $this->getColumnValue($row, $mapping['name_column'] ?? null),
                    'phone' => $this->getColumnValue($row, $mapping['phone_column'] ?? null),
                    'group' => $this->getColumnValue($row, $mapping['group_column'] ?? null),
                    'gender' => $this->getColumnValue($row, $mapping['gender_column'] ?? null),
                    'email' => $this->getColumnValue($row, $mapping['email_column'] ?? null),
                    'role' => $this->getColumnValue($row, $mapping['role_column'] ?? null),
                    'status' => $validation['valid'] ? 'valid' : 'invalid',
                    'errors' => $validation['errors'],
                ];

                if ($validation['valid']) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
            }

            // Update task with total rows
            $task->update(['total_rows' => count($records)]);

            return view('imports.validate', [
                'task' => $task,
                'rows' => $validatedRows,
                'summary' => [
                    'total' => count($records),
                    'valid' => $validCount,
                    'invalid' => $invalidCount,
                ],
            ]);

        } catch (\Exception $e) {
            return view('imports.error', [
                'message' => 'Error reading CSV file: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Process the import
     */
    public function process($id)
    {
        $task = ImportTask::findOrFail($id);
        
        if ($task->status !== 'pending') {
            return view('imports.error', [
                'message' => 'This import task cannot be processed. Current status: ' . $task->status
            ]);
        }

        // Set time and memory limits
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Update status to processing
        $task->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $filePath = storage_path('app/public/' . $task->file_path);
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            
            $records = iterator_to_array($csv->getRecords());
            $mapping = $task->mapping;

            $importedCount = 0;
            $failedCount = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($records as $index => $record) {
                    $rowNumber = $index + 2;
                    $row = array_values($record);

                    $validation = $this->validateRow($row, $mapping, $rowNumber);

                    if (!$validation['valid']) {
                        $failedCount++;
                        $errors[] = "Row $rowNumber: " . implode(', ', $validation['errors']);
                        continue;
                    }

                    // Extract data
                    $name = trim($this->getColumnValue($row, $mapping['name_column']));
                    $phone = $this->normalizePhone(trim($this->getColumnValue($row, $mapping['phone_column'])));
                    $groupName = trim($this->getColumnValue($row, $mapping['group_column']));
                    $gender = trim($this->getColumnValue($row, $mapping['gender_column'] ?? null));
                    $email = trim($this->getColumnValue($row, $mapping['email_column'] ?? null));
                    $role = trim($this->getColumnValue($row, $mapping['role_column'] ?? null));

                    // Check if user exists
                    $existingUser = User::where(function($query) use ($phone) {
                        $query->where('phone_number', $phone)
                              ->orWhere('phone_number_2', $phone);
                    })->first();

                    if ($existingUser) {
                        $failedCount++;
                        $errors[] = "Row $rowNumber: User with phone $phone already exists";
                        continue;
                    }

                    // Find or create group
                    $group = FfsGroup::where('name', $groupName)->first();
                    
                    if (!$group) {
                        // Create new group
                        $group = FfsGroup::create([
                            'name' => $groupName,
                            'type' => 'Farmer Field School',
                            'status' => 'Active',
                            'district_id' => 1, // Default, adjust as needed
                            'created_by' => $task->initiated_by,
                        ]);
                    }

                    // Create user
                    $user = User::create([
                        'name' => $name,
                        'phone_number' => $phone,
                        'phone_number_2' => $phone,
                        'email' => $email ?: null,
                        'sex' => $this->normalizeGender($gender),
                        'dob' => null,
                        'address' => null,
                        'status' => 'Active',
                        'username' => $phone,
                        'password' => bcrypt('12345678'), // Default password
                        'remember_token' => \Illuminate\Support\Str::random(60),
                        'ffs_group_id' => $group->id,
                        'avatar' => 'default.png',
                    ]);

                    $importedCount++;
                }

                DB::commit();

                // Update task status
                $task->update([
                    'status' => 'completed',
                    'imported_rows' => $importedCount,
                    'failed_rows' => $failedCount,
                    'completed_at' => now(),
                    'message' => "Import completed. Imported: $importedCount, Failed: $failedCount",
                ]);

                return view('imports.complete', [
                    'task' => $task,
                    'imported' => $importedCount,
                    'failed' => $failedCount,
                    'errors' => $errors,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $task->update([
                'status' => 'failed',
                'completed_at' => now(),
                'message' => 'Import failed: ' . $e->getMessage(),
            ]);

            return view('imports.error', [
                'message' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Validate a single row
     */
    private function validateRow($row, $mapping, $rowNumber)
    {
        $errors = [];
        $valid = true;

        // Validate name
        $name = trim($this->getColumnValue($row, $mapping['name_column'] ?? null));
        if (empty($name)) {
            $errors[] = 'Name is required';
            $valid = false;
        }

        // Validate phone
        $phone = trim($this->getColumnValue($row, $mapping['phone_column'] ?? null));
        if (empty($phone)) {
            $errors[] = 'Phone number is required';
            $valid = false;
        } else {
            $normalizedPhone = $this->normalizePhone($phone);
            if (!$this->isValidUgandaPhone($normalizedPhone)) {
                $errors[] = 'Invalid Uganda phone number';
                $valid = false;
            } else {
                // Check if phone exists
                $exists = User::where(function($query) use ($normalizedPhone) {
                    $query->where('phone_number', $normalizedPhone)
                          ->orWhere('phone_number_2', $normalizedPhone);
                })->exists();

                if ($exists) {
                    $errors[] = 'Phone number already exists';
                    $valid = false;
                }
            }
        }

        // Validate group
        $group = trim($this->getColumnValue($row, $mapping['group_column'] ?? null));
        if (empty($group)) {
            $errors[] = 'Group name is required';
            $valid = false;
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Get column value by letter
     */
    private function getColumnValue($row, $columnLetter)
    {
        if (empty($columnLetter)) {
            return '';
        }

        $index = ord(strtoupper($columnLetter)) - 65; // A=0, B=1, etc.
        return $row[$index] ?? '';
    }

    /**
     * Normalize phone number to Uganda format
     */
    private function normalizePhone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // Remove country code if present
        if (substr($phone, 0, 3) === '256') {
            $phone = substr($phone, 3);
        }

        // Add country code
        return '256' . $phone;
    }

    /**
     * Validate Uganda phone number
     */
    private function isValidUgandaPhone($phone)
    {
        // Should be 12 digits (256 + 9 digits)
        if (strlen($phone) !== 12) {
            return false;
        }

        // Should start with 256
        if (substr($phone, 0, 3) !== '256') {
            return false;
        }

        // Valid Uganda prefixes: 70, 75, 76, 77, 78, 79 (MTN), 20, 25, 39 (Airtel), 31 (Africell)
        $prefix = substr($phone, 3, 2);
        $validPrefixes = ['70', '75', '76', '77', '78', '79', '20', '25', '39', '31'];

        return in_array($prefix, $validPrefixes);
    }

    /**
     * Normalize gender value
     */
    private function normalizeGender($gender)
    {
        $gender = strtolower(trim($gender));
        
        if (in_array($gender, ['male', 'm', 'man'])) {
            return 'Male';
        }
        
        if (in_array($gender, ['female', 'f', 'woman'])) {
            return 'Female';
        }

        return null;
    }
}

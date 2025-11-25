<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Validation - {{ $task->task_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; padding: 15px; font-size: 13px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
        .header h1 { font-size: 20px; margin-bottom: 5px; }
        .header p { font-size: 12px; opacity: 0.9; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 15px; background: #fafafa; border-bottom: 1px solid #eee; }
        .summary-card { background: white; padding: 12px; border-radius: 4px; border-left: 3px solid #667eea; }
        .summary-card h3 { font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .summary-card p { font-size: 22px; font-weight: bold; color: #333; }
        .summary-card.valid { border-color: #10b981; }
        .summary-card.valid p { color: #10b981; }
        .summary-card.invalid { border-color: #ef4444; }
        .summary-card.invalid p { color: #ef4444; }
        .table-container { padding: 15px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        thead { background: #f9fafb; position: sticky; top: 0; }
        th { padding: 8px 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; font-size: 11px; text-transform: uppercase; }
        td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; }
        tr:hover { background: #f9fafb; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        .status-valid { background: #d1fae5; color: #065f46; }
        .status-invalid { background: #fee2e2; color: #991b1b; }
        .error-list { font-size: 11px; color: #dc2626; line-height: 1.5; }
        .actions { padding: 15px; border-top: 2px solid #e5e7eb; background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #10b981; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .row-number { font-weight: 600; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Import Validation: {{ $task->task_name }}</h1>
            <p>File: {{ basename($task->file_path) }} • Created: {{ $task->created_at->format('M d, Y H:i') }}</p>
        </div>

        <div class="summary">
            <div class="summary-card">
                <h3>Total Rows</h3>
                <p>{{ $summary['total'] }}</p>
            </div>
            <div class="summary-card valid">
                <h3>Valid Rows</h3>
                <p>{{ $summary['valid'] }}</p>
            </div>
            <div class="summary-card invalid">
                <h3>Invalid Rows</h3>
                <p>{{ $summary['invalid'] }}</p>
            </div>
            <div class="summary-card">
                <h3>Success Rate</h3>
                <p>{{ $summary['total'] > 0 ? round(($summary['valid'] / $summary['total']) * 100, 1) : 0 }}%</p>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Group</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr>
                        <td class="row-number">{{ $row['row_number'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['phone'] }}</td>
                        <td>{{ $row['group'] }}</td>
                        <td>{{ $row['gender'] }}</td>
                        <td>{{ $row['email'] }}</td>
                        <td>{{ $row['role'] }}</td>
                        <td>
                            <span class="status-badge status-{{ $row['status'] }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                        <td>
                            @if(!empty($row['errors']))
                            <div class="error-list">
                                @foreach($row['errors'] as $error)
                                • {{ $error }}<br>
                                @endforeach
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="actions">
            <a href="/admin/import-tasks" class="btn btn-secondary">← Back to Import Tasks</a>
            @if($summary['valid'] > 0)
            <a href="{{ route('import.process', $task->id) }}" class="btn btn-primary" onclick="return confirm('Are you sure you want to import {{ $summary[\'valid\'] }} users? This cannot be undone.')">
                ✓ Start Import ({{ $summary['valid'] }} users)
            </a>
            @endif
        </div>
    </div>
</body>
</html>

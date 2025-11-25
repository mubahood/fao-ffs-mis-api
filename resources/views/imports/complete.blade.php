<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Complete - {{ $task->task_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { max-width: 700px; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; }
        .header .icon { font-size: 48px; margin-bottom: 10px; }
        .header h1 { font-size: 24px; margin-bottom: 10px; }
        .header p { font-size: 14px; opacity: 0.9; }
        .content { padding: 30px; }
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f9fafb; padding: 20px; border-radius: 6px; text-align: center; }
        .stat-card h3 { font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 8px; }
        .stat-card p { font-size: 32px; font-weight: bold; }
        .stat-card.success p { color: #10b981; }
        .stat-card.failed p { color: #ef4444; }
        .errors { margin-top: 20px; }
        .errors h3 { font-size: 14px; margin-bottom: 10px; color: #374151; }
        .error-list { max-height: 200px; overflow-y: auto; background: #fef2f2; padding: 15px; border-radius: 4px; border-left: 3px solid #ef4444; }
        .error-list p { font-size: 12px; color: #991b1b; margin-bottom: 5px; line-height: 1.5; }
        .actions { padding: 20px 30px; border-top: 1px solid #e5e7eb; background: #fafafa; text-align: center; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; background: #6b7280; color: white; }
        .btn:hover { background: #4b5563; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">✅</div>
            <h1>Import Completed Successfully!</h1>
            <p>{{ $task->task_name }}</p>
        </div>

        <div class="content">
            <div class="stats">
                <div class="stat-card success">
                    <h3>Successfully Imported</h3>
                    <p>{{ $imported }}</p>
                </div>
                <div class="stat-card failed">
                    <h3>Failed</h3>
                    <p>{{ $failed }}</p>
                </div>
            </div>

            <p style="text-align: center; color: #6b7280; font-size: 14px;">
                Import completed at {{ $task->completed_at->format('M d, Y H:i:s') }}
            </p>

            @if(!empty($errors))
            <div class="errors">
                <h3>⚠️ Errors ({{ count($errors) }})</h3>
                <div class="error-list">
                    @foreach($errors as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="actions">
            <a href="/admin/import-tasks" class="btn">← Back to Import Tasks</a>
        </div>
    </div>
</body>
</html>

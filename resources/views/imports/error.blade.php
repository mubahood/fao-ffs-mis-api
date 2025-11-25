<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { max-width: 600px; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 30px; text-align: center; }
        .header .icon { font-size: 48px; margin-bottom: 10px; }
        .header h1 { font-size: 24px; }
        .content { padding: 30px; text-align: center; }
        .content p { font-size: 14px; color: #6b7280; line-height: 1.6; }
        .error-message { background: #fef2f2; padding: 20px; border-radius: 6px; border-left: 3px solid #ef4444; margin: 20px 0; text-align: left; }
        .error-message p { color: #991b1b; font-family: monospace; font-size: 13px; }
        .actions { padding: 20px 30px; border-top: 1px solid #e5e7eb; background: #fafafa; text-align: center; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; background: #6b7280; color: white; }
        .btn:hover { background: #4b5563; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">❌</div>
            <h1>Import Error</h1>
        </div>

        <div class="content">
            <p>An error occurred while processing your import.</p>
            
            <div class="error-message">
                <p>{{ $message }}</p>
            </div>

            <p>Please check your CSV file and try again. If the problem persists, contact support.</p>
        </div>

        <div class="actions">
            <a href="/admin/import-tasks" class="btn">← Back to Import Tasks</a>
        </div>
    </div>
</body>
</html>

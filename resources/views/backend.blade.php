<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOURX - Backend API Server</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            color: #000000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 480px;
            padding: 30px;
            border: 2px solid #000000;
            background: #ffffff;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tagline {
            color: #555555;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid #000000;
            background-color: #eeeeee;
            color: #000000;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            text-align: left;
            margin-bottom: 20px;
        }

        .meta-item {
            background: #eeeeee;
            border: 1px solid #cccccc;
            padding: 12px;
        }

        .meta-label {
            font-size: 10px;
            color: #555555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: bold;
        }

        .meta-value {
            font-size: 14px;
            font-weight: bold;
            color: #000000;
        }

        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: #555555;
            border-top: 1px solid #000000;
            padding-top: 16px;
            text-align: center;
        }

        .footer a {
            color: #000000;
            text-decoration: underline;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>TOURX</h1>
        <p class="tagline">Tripura Tourism Backend API Gateway</p>

        <div class="status-badge">
            Server Operational
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <div class="meta-label">Environment</div>
                <div class="meta-value">Production</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">API Version</div>
                <div class="meta-value">v1 (Active)</div>
            </div>
        </div>

        <div class="footer">
            Powered by Laravel
        </div>
    </div>
</body>
</html>
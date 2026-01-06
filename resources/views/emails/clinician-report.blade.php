<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Inter', 'Helvetica Neue', sans-serif;
            color: #111;
        }
        .container {
            padding: 20px;
        }
        h1 {
            color: #10734a;
        }
        .contents {
            margin-top: 10px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MigraineAI Clinician Report</h1>
        <p class="contents">
            Hello {{ $clinicianName }},
        </p>
        <p class="contents">
            {{ $requestedBy }} has shared their migraine analytics with you. The attached PDF contains summaries of recent episodes,
            triggers, and locations to support your evaluation.
        </p>
        <p class="contents">
            If you have any questions about the report or need further data, feel free to reach out via the MigraineAI portal.
        </p>
        <p class="contents">
            Regards,<br>
            MigraineAI Team
        </p>
    </div>
</body>
</html>

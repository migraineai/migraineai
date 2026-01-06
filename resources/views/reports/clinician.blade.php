<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <style>
        :root {
            font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a;
        }

        body {
            margin: 0;
            background: #f5f7fb;
        }

        .page {
            padding: 30px 32px 48px;
            max-width: 940px;
            margin: 0 auto;
        }

        .hero {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .hero h1 {
            margin: 8px 0 4px;
            font-size: 32px;
            color: #0b5de6;
        }

        .hero .subtitle {
            margin: 0;
            color: #475569;
            font-size: 14px;
        }

        .numbers-panel {
            margin-top: 10px;
            background: #e8f2ff;
            border-radius: 14px;
            padding: 12px 12px 8px;
            text-align: center;
            page-break-inside: avoid;
            font-size: 0; /* removes whitespace for inline-block children */
        }

        .number-card {
            text-align: center;
            color: #0b1e3b;
            padding: 10px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #dbeafe;
            box-shadow: 0 4px 12px rgba(11, 93, 230, 0.08);
            display: flex;
            flex-direction: column;
            gap: 6px;
            box-sizing: border-box;
        }

        .metric-cell {
            display: inline-block;
            vertical-align: top;
            width: 32%;
            min-width: 150px;
            max-width: 220px;
            margin: 0 0.6% 8px;
            box-sizing: border-box;
        }

        .number-card .number {
            font-size: 22px;
            font-weight: 700;
            color: #0b5de6;
        }

        .number-card small {
            display: block;
            margin-top: 4px;
            color: #475569;
            font-size: 11px;
        }

        .metric-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0b5de6, #1c7ef2);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .section {
            margin-top: 22px;
            background: #fff;
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .section:first-of-type {
            margin-top: 10px;
        }

        .section-title {
            font-size: 18px;
            margin: 0 0 4px;
            color: #0b1e3b;
        }

        .section-subtitle {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 12px;
        }

        .divider-title {
            color: #0b5de6;
            font-weight: 700;
            margin: 28px 0 10px;
            border-bottom: 3px solid #0b5de6;
            padding-bottom: 6px;
        }

        .chart-shell {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            background: #f8fafc;
            page-break-inside: avoid;
        }

        .axis {
            position: relative;
            padding-left: 32px;
            padding-bottom: 18px;
            min-height: 180px;
        }

        .axis-lines {
            position: absolute;
            inset: 0 0 18px 32px;
        }

        .axis-lines div {
            border-top: 1px dashed #e2e8f0;
            height: calc(100% / 4);
        }

        .axis-y {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 18px;
            width: 32px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #475569;
            font-size: 11px;
            text-align: right;
            padding-right: 4px;
        }

        .axis-x {
            position: absolute;
            left: 32px;
            right: 0;
            bottom: 0;
            display: flex;
            justify-content: space-between;
            color: #475569;
            font-size: 11px;
        }

        .bar-chart {
            position: relative;
            display: flex;
            align-items: flex-end;
            gap: 18px;
            height: 100%;
            padding: 0 12px 18px 12px;
        }

        .v-bar {
            flex: 1;
            background: #0b5de6;
            border-radius: 8px 8px 4px 4px;
            position: relative;
            min-height: 8px;
        }

        .v-bar small {
            position: absolute;
            top: -18px;
            left: 50%;
            transform: translateX(-50%);
            color: #0b1e3b;
            font-size: 11px;
            font-weight: 600;
        }

        .horizontal-bars {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bar-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #0b1e3b;
        }

        .bar-label {
            flex: 0 0 150px;
        }

        .bar-label small {
            display: block;
            font-size: 11px;
            color: #64748b;
        }

        .bar-track {
            flex: 1;
            height: 12px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
        }

        .bar-fill {
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, #1777f2, #38bdf8);
        }

        .bar-value {
            width: 60px;
            text-align: right;
            font-weight: 600;
            color: #0b5de6;
        }

        .trend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .trend-card {
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
            font-size: 12px;
        }

        .trend-card strong {
            display: block;
            color: #0b1e3b;
            font-size: 13px;
        }

        .legend {
            margin-top: 8px;
            color: #475569;
            font-size: 12px;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
        }

        .profile-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 14px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            font-size: 13px;
        }

        .tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #0b1e3b;
            font-size: 11px;
            margin-right: 6px;
            margin-bottom: 6px;
        }

        .row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: stretch;
        }

        .row > * {
            flex: 1 1 280px;
        }

        .mini-bar {
            height: 10px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin: 6px 0 4px;
        }

        .mini-bar span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #0b5de6, #14b8a6);
        }

        .donut {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: conic-gradient(#0b5de6 0deg 240deg, #e2e8f0 240deg 360deg);
            position: relative;
            margin: 0 auto 8px;
        }

        .donut::after {
            content: '';
            position: absolute;
            inset: 18px;
            background: #fff;
            border-radius: 50%;
        }

        .donut-label {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0b1e3b;
            font-size: 14px;
        }

        .pill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-top: 8px;
        }

        .pill {
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 6px 12px rgba(15, 23, 42, 0.05);
            font-size: 13px;
            color: #0b1e3b;
        }

        .trigger-card {
            border-left: 5px solid #0b5de6;
            background: #f1f5f9;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .insights {
            background: #e8fff4;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 12px;
            color: #065f46;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
        }

        thead {
            background: #0b5de6;
            color: #fff;
        }

        th,
        td {
            padding: 9px 10px;
            border: 1px solid #e2e8f0;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .footer {
            font-size: 11px;
            color: #475569;
            margin-top: 22px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $maxWeekly = collect($weekly_trend)->max('count') ?: 1;
        $maxIntensityCount = collect($intensity_distribution)->max('count') ?: 1;
    @endphp
    <div class="page">
        <div class="hero">
            <img src="file://{{ $logo_path }}" alt="MigraineAI" height="52" />
            <h1>Your Migraine Summary</h1>
            <p class="subtitle">Report Period: {{ $meta['period'] }} • Generated on {{ $summary['generated_at'] }}</p>
        </div>

        <div class="section" style="margin-top:0;">
            <p class="section-title">Patient Snapshot</p>
            <p class="section-subtitle">Current user details for clinician reference</p>
            <div class="card-grid">
                <div class="profile-card">
                    <strong>Patient</strong>
                    <div>{{ $user->name }}</div>
                </div>
                {{-- <div class="profile-card">
                    <strong>Contact</strong>
                    <div>{{ $user->email }}</div>
                </div>
                <div class="profile-card">
                    <strong>Requested By</strong>
                    <div>{{ $clinician['requested_by'] }}</div>
                    <div class="tag">Clinician: {{ $clinician['name'] ?? 'N/A' }}</div>
                </div> --}}
            </div>
            <div class="numbers-panel" style="margin-top:16px;">
                @php
                    $metrics = [
                        ['icon' => 'TE', 'value' => $summary['total_episodes'], 'label' => 'Total Episodes'],
                        ['icon' => 'AI', 'value' => $summary['average_intensity'], 'label' => 'Avg Intensity'],
                        ['icon' => 'MD', 'value' => $summary['median_duration'], 'label' => 'Median Duration'],
                        ['icon' => 'TR', 'value' => $summary['primary_trigger'], 'label' => 'Top Trigger'],
                        ['icon' => 'PL', 'value' => $summary['primary_location'], 'label' => 'Top Pain Location'],
                        ['icon' => 'TS', 'value' => $summary['total_duration'], 'label' => 'Total Time Symptomatic'],
                    ];
                @endphp
                @foreach ($metrics as $metric)
                    <div class="metric-cell">
                        <div class="number-card">
                            <span class="metric-icon">{{ $metric['icon'] }}</span>
                            <div class="number">{{ $metric['value'] }}</div>
                            <small>{{ $metric['label'] }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- <p class="divider-title">Visual Analysis</p> --}}

        {{-- <div class="section">
            <p class="section-title">Distribution Overview</p>
            <p class="section-subtitle">Clean, single-page snapshot of your patterns</p>
            <div class="row">
                <div class="chart-shell">
                    <p class="section-title" style="font-size:15px;">Intensity Distribution</p>
                    <p class="section-subtitle">Counts by severity</p>
                    <div class="horizontal-bars">
                        @foreach ($intensity_distribution as $label => $data)
                            @php
                                $width = $maxIntensityCount > 0 ? ($data['count'] / $maxIntensityCount) * 100 : 0;
                            @endphp
                            <div class="bar-row">
                                <div class="bar-label">
                                    <strong>{{ $label }}</strong>
                                    <small>{{ $data['count'] }} episodes</small>
                                </div>
                                <div class="bar-track">
                                    <span class="bar-fill" style="width: {{ max(4, $width) }}%;"></span>
                                </div>
                                <div class="bar-value">{{ $data['percent'] }}%</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="chart-shell">
                    <p class="section-title" style="font-size:15px;">Weekly Episode Trend</p>
                    <p class="section-subtitle">Recent pattern at a glance</p>
                    @if (empty($weekly_trend))
                        <p class="section-subtitle" style="margin:0;">No weekly data logged.</p>
                    @else
                        <div class="trend-grid">
                            @foreach ($weekly_trend as $item)
                                <div class="trend-card">
                                    <strong>{{ $item['label'] }}</strong>
                                    <div style="font-size:20px; font-weight:700; color:#0b5de6;">{{ $item['count'] }}</div>
                                    <div class="mini-bar" style="height:6px; margin-top:6px;">
                                        @php
                                            $barWidth = $maxWeekly > 0 ? ($item['count'] / $maxWeekly) * 100 : 0;
                                        @endphp
                                        <span style="width: {{ max(4, $barWidth) }}%;"></span>
                                    </div>
                                    <small style="color:#64748b;">episodes logged</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div> --}}

        <div class="section">
            <p class="section-title">Your Top Triggers</p>
            <p class="section-subtitle">These triggers appear most frequently in your logs</p>
            @forelse ($triggers as $trigger)
                <div class="trigger-card" style="border-left-color: {{ $loop->first ? '#f59e0b' : '#cbd5e1' }}; background: {{ $loop->first ? '#fff7ed' : '#f8fafc' }};">
                    <strong style="text-transform: capitalize;">{{ $trigger['label'] }}</strong><br />
                    Present in {{ $trigger['percent'] ?? 0 }}% of attacks ({{ $trigger['count'] }} times)
                </div>
            @empty
                <p class="section-subtitle" style="margin:0;">No triggers recorded yet.</p>
            @endforelse
        </div>

        <div class="section">
            <p class="section-title">Top Symptoms</p>
            <p class="section-subtitle">Most frequently reported symptoms</p>
            @forelse ($symptoms as $symptom)
                <div class="trigger-card" style="border-left-color: {{ $loop->first ? '#f59e0b' : '#cbd5e1' }}; background: {{ $loop->first ? '#fff7ed' : '#f8fafc' }};">
                    <strong style="text-transform: capitalize;">{{ $symptom['label'] }}</strong><br />
                    Present in {{ $symptom['percent'] ?? 0 }}% of attacks ({{ $symptom['count'] }} times)
                </div>
            @empty
                <p class="section-subtitle" style="margin:0;">No symptoms recorded yet.</p>
            @endforelse
        </div>

        {{-- <div class="section">
            <p class="section-title">Key Insights</p>
            <p class="section-subtitle">Highlights to guide your next visit</p>
            <div class="insights">
                <ul style="margin:0; padding-left:18px;">
                    <li>Most common trigger: {{ $summary['primary_trigger'] ?? 'N/A' }}</li>
                   
                    <li>Frequent pain location: {{ $summary['primary_location'] ?? 'N/A' }}</li>
                </ul>
            </div>
        </div> --}}

        <div class="section">
            <p class="section-title">Common Locations</p>
            <p class="section-subtitle">Where migraine pain most often occurs</p>
            <div class="pill-grid">
                @forelse ($locations as $location)
                    <div class="pill">
                        <strong>{{ $location['label'] }}</strong><br />
                        {{ $location['count'] }} mentions • {{ $location['percent'] }}%
                    </div>
                @empty
                    <p class="section-subtitle" style="margin:0;">No location data logged.</p>
                @endforelse
            </div>
        </div>

        <div class="section">
            <p class="section-title">Recent Episodes</p>
            <p class="section-subtitle">Latest recorded logs with context</p>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Intensity</th>
                        <th>Triggers</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent_episodes as $episode)
                        <tr>
                            <td>{{ $episode['date'] }}</td>
                            <td>{{ $episode['intensity'] }}</td>
                            <td>{{ $episode['triggers'] }}</td>
                            <td>{{ $episode['notes'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;">No episodes logged yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- <div class="section">
            <p class="section-title">Clinician Delivery</p>
            <p class="section-subtitle">Secure delivery details for this report</p>
            <div class="pill-grid">
                <div class="pill"><strong>Clinician:</strong> {{ $clinician['name'] ?? 'Not provided' }}</div>
                <div class="pill"><strong>Email:</strong> {{ $clinician['email'] ?? 'Not provided' }}</div>
                <div class="pill"><strong>Requested By:</strong> {{ $clinician['requested_by'] }}</div>
            </div>
            <div class="notes" style="margin-top:12px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:10px; font-size:12px; color:#475569;">
                This report summarizes user-provided diary entries to support clinical conversations. Please review alongside clinical history and medication plans before making treatment decisions.
            </div>
        </div> --}}

        <div class="footer">
            Privacy: Your data is yours. Download or delete this report anytime in app settings.
            •
            Disclaimer: This is informational and not a substitute for medical advice or emergency care.
        </div>
    </div>
</body>
</html>

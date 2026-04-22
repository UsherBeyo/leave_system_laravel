<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports Print View</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111827;margin:24px}h1{font-size:26px;margin:0 0 8px}p.meta{color:#6b7280;margin:0 0 20px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #cbd5e1;padding:8px 10px;font-size:12px;text-align:left}th{background:#e2e8f0}.pill{display:inline-block;padding:6px 10px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:999px;font-size:12px;font-weight:700;color:#1d4ed8}.summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:18px}.summary-card{border:1px solid #dbe3ef;border-radius:12px;padding:14px}.muted{color:#6b7280}.print-actions{margin-bottom:18px}@media print{.print-actions{display:none}body{margin:0.5in}}
    </style>
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>
    <h1>Leave System Report</h1>
    <p class="meta">Generated {{ now()->format('F d, Y h:i A') }}</p>

    @if($reportType === 'summary')
        <div class="summary-grid">
            <div class="summary-card"><div class="muted">Total Employees</div><div class="pill">{{ $summary['totalEmployees'] }}</div></div>
            <div class="summary-card"><div class="muted">Pending Requests</div><div class="pill">{{ $summary['pendingRequests'] }}</div></div>
            <div class="summary-card"><div class="muted">Approved Requests</div><div class="pill">{{ $summary['approvedRequests'] }}</div></div>
            <div class="summary-card"><div class="muted">Average Vacational Balance</div><div class="pill">{{ number_format((float)$summary['avgAnnualBalance'], 3) }} days</div></div>
        </div>
    @elseif($reportType === 'balance')
        <h2>Leave Balance Report</h2>
        <table>
            <thead><tr><th>Name</th><th>Department</th><th>Vacational Balance</th><th>Sick Balance</th><th>Force Balance</th></tr></thead>
            <tbody>
                @foreach($reportData as $row)
                    <tr>
                        <td>{{ $row->fullName() }}</td>
                        <td>{{ $row->department ?: '—' }}</td>
                        <td>{{ number_format((float)$row->annual_balance, 3) }}</td>
                        <td>{{ number_format((float)$row->sick_balance, 3) }}</td>
                        <td>{{ number_format((float)$row->force_balance, 3) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($reportType === 'usage')
        <h2>Leave Usage Report</h2>
        <table>
            <thead><tr><th>Department</th><th>Leave Type</th><th>Request Count</th><th>Total Days</th></tr></thead>
            <tbody>
                @foreach($reportData as $row)
                    <tr>
                        <td>{{ $row['department'] }}</td>
                        <td>{{ $row['leave_type'] }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>{{ number_format((float)$row['total_days'], 3) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($reportType === 'leave_card' && $selectedEmployee)
        <h2>Leave Card - {{ $selectedEmployee->fullName() }}</h2>
        <p class="meta">Department: {{ $selectedEmployee->department ?: '—' }} · Position: {{ $selectedEmployee->position ?: '—' }}</p>
        <table>
            <thead><tr><th>Date</th><th>Particulars</th><th>Vac Earned</th><th>Vac Deducted</th><th>Vac Balance</th><th>Sick Earned</th><th>Sick Deducted</th><th>Sick Balance</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($reportData as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['particulars'] }}</td>
                        <td>{{ ($row['vac_earned'] ?? 0) != 0 ? number_format((float)$row['vac_earned'],3) : '' }}</td>
                        <td>{{ ($row['vac_deducted'] ?? 0) != 0 ? number_format((float)$row['vac_deducted'],3) : '' }}</td>
                        <td>{{ $row['vac_balance'] === '' ? '' : number_format((float)$row['vac_balance'],3) }}</td>
                        <td>{{ ($row['sick_earned'] ?? 0) != 0 ? number_format((float)$row['sick_earned'],3) : '' }}</td>
                        <td>{{ ($row['sick_deducted'] ?? 0) != 0 ? number_format((float)$row['sick_deducted'],3) : '' }}</td>
                        <td>{{ $row['sick_balance'] === '' ? '' : number_format((float)$row['sick_balance'],3) }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <script>window.addEventListener('load',()=>{setTimeout(()=>window.print(),250);});</script>
</body>
</html>

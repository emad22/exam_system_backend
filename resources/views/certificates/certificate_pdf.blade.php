<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @page {
            size: A4 landscape;
            margin: 22px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        /* Outer border frame */
        .cert-box {
            border: 2px solid #1e293b;
            padding: 14px 18px 10px 18px;
            width: 98%;
        }

        /* ── Row 1: Logo | Title | Photo ── */
        .header-table {
            width: 100%;
            table-layout: fixed;
        }

        .header-table td {
            vertical-align: top;
            padding: 0;
        }

        .col-logo {
            width: 140px;
        }

        .col-logo img {
            width: 115px;
            height: auto;
        }

        .col-logo-text {
            font-size: 13px;
            font-weight: 900;
            color: #1e293b;
        }

        .col-title {
            text-align: center;
            vertical-align: middle;
            padding-top: 8px;
        }

        .col-title h1 {
            font-family: Georgia, serif;
            font-size: 26px;
            font-weight: 900;
            margin: 0 0 2px 0;
            color: #000;
            letter-spacing: 1px;
        }

        .col-title p {
            font-size: 13px;
            font-style: italic;
            margin: 0;
            color: #475569;
        }

        .col-photo {
            width: 105px;
            text-align: right;
            vertical-align: top;
        }

        .photo-box {
            width: 90px;
            height: 105px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #94a3b8;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            line-height: 105px;
        }

        /* ── Student Name ── */
        .student-name {
            text-align: center;
            font-size: 28px;
            font-weight: 900;
            text-decoration: underline;
            color: #000;
            margin: 5px 0 4px 0;
        }

        /* ── Description ── */
        .description {
            text-align: center;
            font-size: 11px;
            font-style: italic;
            color: #334155;
            margin: 4px 60px;
        }

        /* ── Scores table ── */
        .scores-table {
            width: 100%;
            margin-top: 8px;
            border-collapse: collapse;
            font-size: 10px;
            table-layout: fixed;
        }

        .scores-table th {
            border: 1px solid #444;
            padding: 5px 4px;
            background-color: #f8fafc;
            font-weight: 900;
            text-transform: uppercase;
            text-align: center;
            font-size: 9px;
        }

        .scores-table th:first-child {
            text-align: left;
            padding-left: 8px;
        }

        .scores-table td {
            border: 1px solid #444;
            padding: 5px 4px;
            text-align: center;
            font-size: 10px;
        }

        .scores-table td:first-child {
            text-align: left;
            padding-left: 8px;
        }

        .overall-row td {
            font-weight: 900;
            background-color: #f1f5f9;
        }

        /* ── Signatures ── */
        .signatures-table {
            width: 100%;
            margin-top: 12px;
            table-layout: fixed;
        }

        .signatures-table td {
            text-align: center;
            vertical-align: bottom;
            padding: 0 6px;
            width: 33.33%;
        }

        .sig-sign {
            font-size: 17px;
            color: #2563eb;
            font-style: italic;
            margin: 0;
        }

        .sig-name {
            border-top: 1px solid #cbd5e1;
            padding-top: 3px;
            font-size: 11px;
            font-weight: 900;
            margin: 0;
        }

        .sig-title {
            font-size: 9px;
            color: #64748b;
            font-weight: bold;
            margin: 0;
        }

        .sig-address {
            font-size: 7px;
            color: #64748b;
            margin: 0;
            line-height: 1.5;
        }

        /* ── Footer ── */
        .footer-table {
            width: 100%;
            margin-top: 8px;
            table-layout: fixed;
        }

        .footer-table td {
            vertical-align: middle;
            padding: 0;
        }

        .footer-left {
            text-align: left;
            width: 60%;
        }

        .footer-right {
            text-align: right;
            width: 40%;
        }

        .qr-img {
            width: 52px;
            height: 52px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .cert-sn {
            font-size: 8px;
            font-weight: bold;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            vertical-align: middle;
        }

        .issue-date {
            font-size: 9px;
            font-weight: 900;
            font-style: italic;
            color: #1e293b;
        }
    </style>
</head>

<body>
    <div class="cert-box">

        {{-- ── Row 1: Logo | Title | Photo ── --}}
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="col-logo">
                    @if(!empty($logoPath))
                        <img src="{{ $logoPath }}" alt="Logo" />
                    @else
                        <span class="col-logo-text">ARAB ACADEMY</span>
                    @endif
                </td>
                <td class="col-title">
                    <h1>ARAB ACADEMY</h1>
                    <p>certifies that</p>
                </td>
                <td class="col-photo">
                    
                </td>
                
            </tr>
        </table>

        {{-- ── Student Name ── --}}
        <div class="student-name">{{ $studentName }}</div>

        {{-- ── Description ── --}}
        <div class="description">
            Has sat for the Arabic Language Proficiency Test (ALPT) and attained the following scores:
        </div>

        {{-- ── Scores Table ── --}}
        <table class="scores-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Score</th>
                    <th>Score%</th>
                    <th>Level (CEFR)</th>
                    <th>Level (ACTFL)</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($skills as $skill)
                    <tr>
                        <td>Section: {{ ucfirst($skill['name']) }}</td>
                        <td>{{ $skill['points'] }}/900</td>
                        <td>{{ number_format($skill['score'], 1) }}%</td>
                        <td>{{ $skill['cefr'] }}</td>
                        <td>{{ $skill['actfl'] }}</td>
                        <td>{{ $skill['date'] }}</td>
                    </tr>
                @endforeach
                <tr class="overall-row">
                    <td>Overall Score (Sections Listening, Reading &amp; Structure)</td>
                    <td>{{ $totalPoints }}/900</td>
                    <td>{{ number_format($score, 1) }}%</td>
                    <td>{{ $cefr }}</td>
                    <td>{{ $actfl }}</td>
                    <td>{{ $issueDate }}</td>
                </tr>
            </tbody>
        </table>

        {{-- ── Signatures ── --}}
        <table class="signatures-table" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <p class="sig-sign">Sayed Ramadan</p>
                    <p class="sig-name">Sayed Ramadan</p>
                    <p class="sig-title">Program Director</p>
                </td>
                <td>
                    <p class="sig-address">3 alif Al-Nabataat Street,</p>
                    <p class="sig-address">Garden City, Cairo, Egypt</p>
                </td>
                <td>
                    <p class="sig-sign">Hanan Dawah</p>
                    <p class="sig-name">Hanan Dawah</p>
                    <p class="sig-title">Registrar</p>
                </td>
            </tr>
        </table>

        {{-- ── Footer ── --}}
        <table class="footer-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="footer-left">
                    @if(!empty($qrImage))
                        <img class="qr-img" src="data:image/png;base64,{{ $qrImage }}" alt="QR" />
                    @endif
                    <span class="cert-sn">Certificate S.N.: {{ $certNumber }}</span>
                </td>
                <td class="footer-right">
                    <span class="issue-date">Certificate Awarded on: {{ $issueDate }}</span>
                </td>
            </tr>
        </table>

    </div>
</body>

</html>
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CertificateTemplate;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        CertificateTemplate::create([
            'name' => 'Institutional Academic Certificate',
            'is_default' => true,
            'content_html' => '
                <style>
                    .cert-container { position: relative; width: 100%; height: 100%; padding: 20px; border: 2px solid #333; }
                    .header-logo { width: 150px; position: absolute; top: 10px; left: 10px; }
                    .student-photo { width: 120px; height: 150px; border: 1px solid #ddd; position: absolute; top: 10px; right: 10px; background: #f8fafc; text-align: center; line-height: 150px; font-size: 10px; color: #94a3b8; }
                    .main-title { text-align: center; margin-top: 40px; }
                    .main-title h1 { font-family: serif; font-size: 38px; margin-bottom: 5px; color: #000; }
                    .main-title p { font-style: italic; font-size: 18px; margin: 0; }
                    .student-name { text-align: center; margin-top: 15px; font-size: 32px; font-weight: bold; font-family: sans-serif; text-decoration: underline; }
                    .description { text-align: center; margin-top: 30px; font-size: 16px; font-style: italic; line-height: 1.6; padding: 0 40px; }
                    
                    .scores-table { width: 100%; margin-top: 30px; border-collapse: collapse; font-size: 12px; }
                    .scores-table th, .scores-table td { border: 1px solid #444; padding: 8px; text-align: center; }
                    .scores-table th { background-color: #f1f5f9; font-weight: bold; }
                    .overall-row { font-weight: bold; background-color: #f8fafc; }

                    .signatures { margin-top: 50px; width: 100%; }
                    .signature-box { width: 33%; display: inline-block; text-align: center; vertical-align: bottom; }
                    .signature-img { width: 120px; height: 40px; margin-bottom: 5px; }
                    .signature-name { font-weight: bold; font-size: 14px; margin: 0; }
                    .signature-title { font-size: 12px; color: #64748b; margin: 0; }

                    .footer { position: absolute; bottom: 20px; width: 100%; font-size: 10px; }
                    .qr-code { float: left; width: 80px; height: 80px; background: #eee; }
                    .cert-info { float: left; margin-left: 20px; margin-top: 40px; }
                    .award-date { float: right; margin-top: 40px; margin-right: 40px; font-weight: bold; font-style: italic; }
                </style>

                <div class="cert-container">
                    <!-- Logos & Photos -->
                    <div class="header-logo">
                        <img src="https://www.arabacademy.com/wp-content/uploads/2021/04/arab-academy-logo.png" style="width: 100%;" />
                    </div>
                    <div class="student-photo">PHOTO</div>

                    <!-- Titles -->
                    <div class="main-title">
                        <h1>ARAB ACADEMY</h1>
                        <p>certifies that</p>
                    </div>

                    <div class="student-name">{name}</div>

                    <div class="description">
                        Has sat for the Arabic Language Proficiency Test (ALPT) and attained the following scores:
                    </div>

                    <!-- Detailed Table -->
                    <table class="scores-table">
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
                            {skills_table}
                            <tr class="overall-row">
                                <td>Overall Score</td>
                                <td>{total_points}/900</td>
                                <td>{score}%</td>
                                <td>{cefr}</td>
                                <td>{actfl}</td>
                                <td>{date}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Signatures -->
                    <div class="signatures">
                        <div class="signature-box">
                            <p class="signature-name" style="font-style: italic; color: #2563eb;">Sayed Ramadan</p>
                            <p class="signature-name">Sayed Ramadan</p>
                            <p class="signature-title">Program Director</p>
                        </div>
                        <div class="signature-box">
                            <img src="https://www.arabacademy.com/wp-content/uploads/2021/04/arab-academy-logo.png" style="width: 80px;" />
                            <p style="font-size: 8px; margin: 0;">3 alif Al-Nabataat Street,</p>
                            <p style="font-size: 8px; margin: 0;">Garden City, Cairo, Egypt</p>
                        </div>
                        <div class="signature-box">
                            <p class="signature-name" style="font-style: italic; color: #2563eb;">Hanan Dawah</p>
                            <p class="signature-name">Hanan Dawah</p>
                            <p class="signature-title">Registrar</p>
                        </div>
                    </div>

                    <!-- Footer Info -->
                    <div class="footer">
                        <div class="qr-code">
                            <!-- Placeholder for QR - usually handled by dompdf helper or image -->
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={verification_url}" />
                        </div>
                        <div class="cert-info">
                            Certificate S.N.: {number}
                        </div>
                        <div class="award-date">
                            Certificate Awarded on: {date}
                        </div>
                    </div>
                </div>
            '
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CertificateTemplate;

class SampleCertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        CertificateTemplate::create([
            'name' => 'Arab Academy Official (Sample)',
            'is_default' => false,
            'elements_json' => json_encode([]),
            'content_html' => <<<HTML
<style>
  .cert-wrap { width: 1123px; height: 794px; margin: 0 auto; position: relative; font-family: 'DejaVu Sans', sans-serif; }
  .header { text-align: center; margin-top: 40px; }
  .header h1 { font-family: serif; font-size: 36px; margin: 0; letter-spacing: 1px; }
  .sub { text-align: center; margin-top: 4px; font-style: italic; color: #374151; }
  .student-name { text-align: center; font-size: 32px; font-weight: 800; margin-top: 18px; text-decoration: underline; }
  .description { text-align: center; margin-top: 18px; color: #475569; }
  .scores { width: 80%; margin: 24px auto 12px auto; border-collapse: collapse; font-size: 12px; }
  .scores th, .scores td { border: 1px solid #2b3440; padding: 8px; }
  .scores th { background:#f1f5f9; font-weight:800; }
  .signatures { width: 90%; margin: 40px auto 0 auto; display:flex; justify-content:space-between; align-items:flex-end; }
  .sig-box { width: 30%; text-align:center; }
  .qr { width:120px; }
  .footer-note { font-size: 11px; color:#475569; text-align:center; margin-top:18px; }
</style>

<div class="cert-wrap">
  <div class="header">
    <h1>ARAB ACADEMY</h1>
    <div class="sub">certifies that</div>
    <div class="student-name">{name}</div>
  </div>

  <div class="description">Has sat for the Arabic Language Proficiency Test (ALPT) and attained the following scores:</div>

  <table class="scores">
    <thead>
      <tr>
        <th style="width:45%">TEST</th>
        <th style="width:15%">SCORE</th>
        <th style="width:15%">SCORE%</th>
        <th style="width:12%">LEVEL (CEFR)</th>
        <th style="width:13%">DATE</th>
      </tr>
    </thead>
    <tbody>
      {skills_table}
      <tr>
        <td style="font-weight:800">Overall Score</td>
        <td style="font-weight:800">{total_points}/900</td>
        <td style="font-weight:800">{score}%</td>
        <td style="font-weight:800">{cefr}</td>
        <td style="font-weight:800">{date}</td>
      </tr>
    </tbody>
  </table>

  <div class="signatures">
    <div class="sig-box">
      <div style="font-family: 'Brush Script MT', cursive; color:#2563eb; font-size:18px;">Sayed Ramadan</div>
      <div style="font-weight:800">Sayed Ramadan</div>
      <div style="font-size:12px; color:#64748b">Program Director</div>
    </div>

    <div class="sig-box" style="text-align:center">
      <img src="/storage/placeholder-qr.png" class="qr" />
      <div style="font-size:11px; color:#94a3b8; margin-top:6px">CERTIFICATE S.N.: {number}</div>
    </div>

    <div class="sig-box">
      <div style="font-family: 'Brush Script MT', cursive; color:#2563eb; font-size:18px;">Hanan Dawah</div>
      <div style="font-weight:800">Hanan Dawah</div>
      <div style="font-size:12px; color:#64748b">Registrar</div>
    </div>
  </div>

  <div class="footer-note">Certificate Awarded on: {date}</div>
</div>
HTML
        ]);
    }
}

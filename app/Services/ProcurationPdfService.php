<?php

declare(strict_types=1);

namespace AgVote\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genere le PDF de procuration (pouvoir de representation) pour une delegation de vote.
 *
 * Conforme aux pratiques des associations loi 1901.
 * Utilise Dompdf avec styles inline pour compatibilite maximale.
 */
final class ProcurationPdfService
{
    /**
     * Genere le HTML du document de procuration.
     *
     * @param array  $proxy   Tableau avec cles: id, giver_name, receiver_name
     * @param array  $meeting Tableau avec cles: title, scheduled_at
     * @param string $orgName Nom de l'organisation / association
     */
    public function renderHtml(array $proxy, array $meeting, string $orgName): string
    {
        $giverName    = htmlspecialchars((string) ($proxy['giver_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $receiverName = htmlspecialchars((string) ($proxy['receiver_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $meetingTitle = htmlspecialchars((string) ($meeting['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $orgNameSafe  = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');
        $proxyId      = htmlspecialchars((string) ($proxy['id'] ?? ''), ENT_QUOTES, 'UTF-8');

        $scheduledAt = $meeting['scheduled_at'] ?? null;
        $dateFormatted = $scheduledAt ? date('d/m/Y', strtotime($scheduledAt)) : '—';

        $generatedAt = date('d/m/Y à H:i');

        // Build header text for @page @top-center.
        // D-03: format "[Titre séance] — [JJ/MM/YYYY]" (em-dash U+2014).
        // The plain (non-HTML-escaped) meeting title is used to compose a CSS
        // string literal — htmlspecialchars would inject &quot; entities that
        // dompdf does not decode inside @page content. Instead we apply a
        // CSS-string-safe escape (backslash-escape " and \) to keep the text
        // literal while preserving UTF-8 accents (DejaVu Sans renders them).
        $rawTitle = (string) ($meeting['title'] ?? '');
        $headerText = $rawTitle !== '' && $scheduledAt
            ? $rawTitle . ' — ' . $dateFormatted
            : ($rawTitle !== '' ? $rawTitle : 'Procuration');
        $headerCss = self::escapeCssString($headerText);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
@page {
    margin-top: 3cm;
    margin-bottom: 2cm;
    margin-left: 2cm;
    margin-right: 2cm;
    @top-center {
        content: "{$headerCss}";
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9pt;
        color: #666;
    }
    @bottom-center {
        content: "Page " counter(page) " sur " counter(pages);
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9pt;
        color: #888;
    }
}
/* Preserve page-break rules (v2.3 P2 EDITORIAL-07 compatibility) */
.legal-box, .signature-table, .info-table { page-break-inside: avoid; }
body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 12pt;
    color: #1a1a1a;
    line-height: 1.6;
}
h1 {
    text-align: center;
    font-size: 18pt;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 6px;
}
.org-name {
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
    margin-bottom: 4px;
}
.subtitle {
    text-align: center;
    font-size: 10pt;
    color: #555;
    margin-bottom: 24px;
}
.section {
    margin-bottom: 18px;
}
.section-title {
    font-size: 10pt;
    font-weight: bold;
    text-transform: uppercase;
    color: #555;
    border-bottom: 1px solid #ccc;
    padding-bottom: 3px;
    margin-bottom: 10px;
}
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
}
.info-table td {
    padding: 5px 8px;
    vertical-align: top;
}
.info-table .label {
    font-weight: bold;
    width: 38%;
    color: #333;
}
.info-table .value {
    width: 62%;
}
.legal-box {
    border: 1px dashed #888;
    padding: 12px 16px;
    margin: 20px 0;
    font-size: 10pt;
    color: #333;
    background: #fafafa;
}
.legal-box .legal-title {
    font-weight: bold;
    margin-bottom: 6px;
    text-transform: uppercase;
    font-size: 9pt;
    color: #555;
}
.signature-table {
    width: 100%;
    margin-top: 32px;
    border-collapse: collapse;
}
.signature-table td {
    width: 50%;
    vertical-align: top;
    padding: 0 12px;
}
.signature-label {
    font-weight: bold;
    margin-bottom: 48px;
}
.signature-line {
    border-bottom: 1px solid #333;
    height: 1px;
    margin-top: 40px;
}
.footer {
    text-align: center;
    font-size: 8pt;
    color: #888;
    margin-top: 32px;
    border-top: 1px solid #eee;
    padding-top: 8px;
}
</style>
</head>
<body>

<div class="org-name">{$orgNameSafe}</div>
<h1>Pouvoir de Représentation</h1>
<div class="subtitle">Procuration pour assemblée générale — Document officiel</div>

<div class="section">
  <div class="section-title">Identité des parties</div>
  <table class="info-table">
    <tr>
      <td class="label">Je soussigné(e) :</td>
      <td class="value">{$giverName}</td>
    </tr>
    <tr>
      <td class="label">Donne procuration à :</td>
      <td class="value">{$receiverName}</td>
    </tr>
  </table>
</div>

<div class="section">
  <div class="section-title">Séance concernée</div>
  <table class="info-table">
    <tr>
      <td class="label">Pour la séance :</td>
      <td class="value">{$meetingTitle}</td>
    </tr>
    <tr>
      <td class="label">Date :</td>
      <td class="value">{$dateFormatted}</td>
    </tr>
    <tr>
      <td class="label">Organisation :</td>
      <td class="value">{$orgNameSafe}</td>
    </tr>
  </table>
</div>

<div class="legal-box">
  <div class="legal-title">Mention légale</div>
  Conformément aux dispositions légales applicables aux associations et collectivités,
  le présent pouvoir autorise le mandataire à voter au nom du mandant pour toutes
  les résolutions inscrites à l'ordre du jour de la séance susmentionnée.
  Ce document est conforme aux pratiques des associations régies par la loi de 1901.
</div>

<table class="signature-table">
  <tr>
    <td>
      <div class="signature-label">Signature du mandant :</div>
      <div class="signature-line"></div>
    </td>
    <td>
      <div class="signature-label">Signature du mandataire :</div>
      <div class="signature-line"></div>
    </td>
  </tr>
</table>

<div class="footer">
  Document généré par AG-VOTE le {$generatedAt} — Identifiant : {$proxyId}
</div>

</body>
</html>
HTML;

        return $html;
    }

    /**
     * Genere le PDF binaire de la procuration.
     *
     * @param array  $proxy   Tableau avec cles: id, giver_name, receiver_name
     * @param array  $meeting Tableau avec cles: title, scheduled_at
     * @param string $orgName Nom de l'organisation / association
     * @return string Contenu binaire PDF
     */
    public function generatePdf(array $proxy, array $meeting, string $orgName): string
    {
        $html = $this->renderHtml($proxy, $meeting, $orgName);

        // F16: dompdf hardening. Three layers:
        //  - isRemoteEnabled=false → never fetch HTTP/HTTPS resources from
        //    inside the rendered HTML (no SSRF, no exfiltration).
        //  - isPhpEnabled=false → refuse to evaluate `<script type="text/php">`
        //    blocks, which dompdf historically supported and which led to
        //    several CVEs (CVE-2014-2383, CVE-2022-41343, CVE-2023-22897).
        //  - setChroot() → restrict file:// reads to a fixed asset directory.
        //    Even if a relative path slips through, dompdf cannot escape it.
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $chroot = realpath(__DIR__ . '/../Templates') ?: __DIR__ . '/../Templates';
        $options->setChroot([$chroot]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Escape a string for safe inclusion inside a CSS double-quoted string literal
     * (e.g. `@page @top-center { content: "..."; }`).
     *
     * Backslashes and double-quotes are escaped per CSS spec. Newlines are stripped
     * (CSS strings cannot contain raw newlines). UTF-8 accents are preserved as-is —
     * dompdf renders them via the configured DejaVu Sans font.
     */
    private static function escapeCssString(string $s): string
    {
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $s);
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('"', '\\"', $s);
        return $s;
    }
}

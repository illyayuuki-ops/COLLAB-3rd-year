<?php
require_once dirname(__DIR__).'/config/app.php';
require_once __DIR__.'/domain.php';
require_once __DIR__.'/reports.php';
$u = g_actor();
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    $format = rp_text($_GET['format'] ?? 'csv');
    if (!in_array($format, ['csv', 'print'], true)) { rp_fail('Unknown report format.'); }
    $validated = rp_filters($u, $_GET);
    gx(g_rate('guidance_reports_export_'.$u['id'], 30), 'Too many exports. Try again later.', 429);
    $report = rp_report($u, $validated);
    $f = $report['filters'];
    g_audit($u, 'reports_'.$format.'_export', json_encode(array_intersect_key($f, array_flip(['mode','from','to','category','counselor','program','academic_program','year_level','section']))));
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="guidance-'.$f['mode'].'-'.$f['from'].'-to-'.$f['to'].'.csv"');
        echo "\xEF\xBB\xBF".g_csv(array_merge([['Section','Measure','Result','Basis / definition']], rp_export_rows($report)));
    } else {
        require_once __DIR__.'/reports-view.php';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($report['title']).' · LCCT</title><style>'.file_get_contents(__DIR__.'/assets/reports.css').'</style></head><body class="rp-print"><div class="rp-print-toolbar"><button type="button" onclick="window.print()">Print / save as PDF</button><span>Use your browser’s print dialog. Keep this restricted report private.</span></div><main>'.rp_render($report).'</main></body></html>';
    }
} catch (RuntimeException $ex) {
    $status = in_array($ex->getCode(), [400,403,404,422,429], true) ? $ex->getCode() : 500;
    g_denied($status === 500 ? 'The report could not be generated. Please contact the Guidance Head.' : $ex->getMessage(), $status);
}

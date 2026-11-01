<?php
if (!function_exists('rp_report')) { http_response_code(404); exit; }
function rp_render(array $report): string
{
    $f = $report['filters'];
    $h = '<article class="rp-report"><header class="rp-heading"><div><p class="rp-eyebrow">LCCT · GUIDANCE OFFICE</p><h2>'.e($report['title']).'</h2><p>'.e($report['scope']).'</p></div><div class="rp-period"><strong>'.e($f['from']).' — '.e($f['to']).'</strong><span>'.e($f['days']).' days · inclusive</span><span>Generated '.e($report['generated']).'</span></div></header><p class="rp-selection">'.e($report['selection']).'</p><aside class="rp-privacy"><strong>Privacy & interpretation</strong><p>'.e($report['privacy']).'</p></aside><div class="rp-grid">';
    foreach ($report['sections'] as $index => $section) {
        $h .= '<section class="rp-section'.($index === 0 ? ' rp-summary' : '').'"><h3>'.e($section['title']).'</h3><p class="rp-note">'.e($section['note']).'</p>';
        if (!$section['items']) { $h .= '<p class="rp-empty">No matching groups in this scope.</p>'; }
        elseif ($index === 0) {
            $h .= '<div class="rp-kpis">';
            foreach ($section['items'] as $item) { $h .= '<div class="rp-kpi"><span>'.e($item['label']).'</span><strong>'.e($item['display']).'</strong><small>'.e($item['note']).'</small></div>'; }
            $h .= '</div>';
        } else {
            $max = max(array_merge([0], array_map(fn ($i) => $i['unit'] === 'count' ? ($i['value'] ?? 0) : 0, $section['items'])));
            $h .= '<table><caption class="rp-sr">'.e($section['title']).'</caption><thead><tr><th scope="col">Measure / group</th><th scope="col">Result</th></tr></thead><tbody>';
            foreach ($section['items'] as $item) {
                $bar = $item['unit'] === 'count' && $item['value'] !== null && $item['value'] >= 0 && $max > 0 ? '<span class="rp-track" aria-hidden="true"><i style="width:'.round(100 * $item['value'] / $max, 2).'%"></i></span>' : '';
                $h .= '<tr><th scope="row">'.e($item['label']).($item['note'] ? '<small>'.e($item['note']).'</small>' : '').$bar.'</th><td'.($item['value'] === null ? ' class="rp-muted"' : '').'>'.e($item['display']).'</td></tr>';
            }
            $h .= '</tbody></table>';
            if ($max > 0) { $h .= '<p class="rp-scale">Bars are relative to the largest released count in this section.</p>'; }
        }
        $h .= '</section>';
    }
    $h .= '</div><section class="rp-definitions"><h3>How to read this report</h3><ul>';
    foreach ($report['definitions'] as $text) { $h .= '<li>'.e($text).'</li>'; }
    return $h.'</ul><p>Prepared from current system records. Review data quality before using these measures for planning or staff evaluation. Distribution is restricted to authorized Guidance staff.</p></section><footer class="rp-footer">LCCT ServiceDesk · Guidance reports · '.$report['generated'].'</footer></article>';
}
function rp_controls(array $report): string
{
    $f = $report['filters'];
    $base = ['view' => 'reports', 'preset' => $f['preset'], 'from' => $f['from'], 'to' => $f['to']];
    $h = '<div class="rp-controls">';
    if ($f['role'] === 'head') {
        $h .= '<nav class="rp-tabs" aria-label="Report area">';
        foreach (['clinical' => 'Services & appointments', 'activities' => 'Activities & programs'] as $mode => $label) {
            $h .= '<a'.($mode === $f['mode'] ? ' aria-current="page"' : '').' href="'.e(url('guidance/index.php?'.http_build_query($base + ['mode' => $mode]))).'">'.e($label).'</a>';
        }
        $h .= '</nav>';
    }
    $h .= '<form method="get" class="rp-filters">'.gh('view', 'reports').gh('mode', $f['mode']).'<div class="rp-fields">'.gs('preset', 'Reporting period', ['30' => 'Last 30 days', '90' => 'Last 90 days', 'month' => 'Month to date', 'year' => 'Year to date', 'custom' => 'Custom dates'], $f['preset']).gf('from', 'From · inclusive', 'date', $f['from'], 'required').gf('to', 'To · inclusive', 'date', $f['to'], 'required');
    if ($f['mode'] === 'clinical') {
        $h .= gs('category', 'Current service', [0 => 'All services'] + array_column(gall("SELECT id,name FROM categories WHERE office_code='guide' ORDER BY name"), 'name', 'id'), $f['category']);
        if ($f['role'] === 'head') { $h .= gs('counselor', 'Current assignment', [0 => 'All counselors / unassigned'] + array_column(gall("SELECT u.id,u.full_name FROM gd_staff s JOIN users u ON u.id=s.user_id WHERE s.guidance_role IN ('head','counselor') AND u.office_code='guide' ORDER BY u.full_name"), 'full_name', 'id'), $f['counselor']); }
    } else { $h .= gs('program', 'Activity program', [0 => 'All programs / standalone'] + array_column(gall('SELECT id,title FROM gd_programs ORDER BY title'), 'title', 'id'), $f['program']); }
    $h .= '</div>';
    if ($f['mode'] === 'clinical' && function_exists('gr_ready') && gr_ready()) {
        $hasCohort = $f['academic_program'] !== '' || $f['year_level'] !== '' || $f['section'] !== '';
        $h .= '<details class="rp-cohort"'.($hasCohort ? ' open' : '').'><summary>Academic cohort · current recorded metadata'.($hasCohort ? ' · filters applied' : '').'</summary><div class="rp-fields">'.gf('academic_program', 'Academic program', 'text', $f['academic_program'], 'maxlength="120"').gf('year_level', 'Year level', 'text', $f['year_level'], 'maxlength="40"').gf('section', 'Section', 'text', $f['section'], 'maxlength="80"').'</div><p class="rp-note">Exact recorded labels. These are current cohort filters, not historical enrollment snapshots; small groups stay protected.</p></details>';
    }
    $h .= '<p class="rp-note">Philippine time · maximum 366 days. Choose Custom dates when editing the date fields. Reports are snapshots; Apply refreshes the results.</p><div class="rp-actions"><button class="btn btn-primary" type="submit">Apply / refresh</button><a class="btn" href="'.e(url('guidance/index.php?view=reports&mode='.$f['mode'])).'">Reset</a></div></form>';
    $query = array_intersect_key($f, array_flip(['mode', 'from', 'to', 'category', 'counselor', 'program', 'academic_program', 'year_level', 'section'])); $query['preset'] = 'custom';
    // Fix absolute dates in exports: preset windows cannot roll over between page and download.
    $endpoint = url('guidance/reports-export.php?'.http_build_query($query));
    return $h.'<div class="rp-actions rp-downloads"><a class="btn" href="'.e($endpoint.'&format=csv').'">Download report CSV</a><a class="btn" href="'.e($endpoint.'&format=print').'" target="_blank" rel="noopener">Print / save as PDF</a><span>Same filters and privacy rules. Regenerated at download time.</span></div></div>';
}

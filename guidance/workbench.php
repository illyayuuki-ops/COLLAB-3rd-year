<?php
// Read/query helpers for the Guidance restoration. No new shared tables or writes.
if (!function_exists('g_actor')) { http_response_code(404); exit; }
function gw_text(array $p, string $key, int $max = 120): string {
    $v = $p[$key] ?? '';
    gx(is_string($v) && mb_check_encoding($v, 'UTF-8') && mb_strlen($v) <= $max, 'Invalid filter: '.$key, 400);
    return trim($v);
}
function gw_choice(array $p, string $key, array $allowed, string $default = ''): string {
    $v = gw_text($p, $key, 60);
    if ($v === '') return $default;
    gx(in_array($v, $allowed, true), 'Choose a valid '.$key.' filter.', 400);
    return $v;
}
function gw_id(array $p, string $key): int {
    $v = gw_text($p, $key, 12);
    gx($v === '' || (ctype_digit($v) && (int)$v <= 2147483647), 'Invalid '.$key.' filter.', 400);
    return (int)$v;
}
function gw_dates(array $p): array {
    $out = [];
    foreach (['from','to'] as $key) {
        $s = gw_text($p, $key, 10);
        $date = $s === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        gx($s === '' || ($date && $date->format('Y-m-d') === $s && $s >= '1970-01-01' && $s <= '9998-12-31'), 'Choose a valid '.$key.' date.', 400);
        $out[$key] = $s;
    }
    gx(!$out['from'] || !$out['to'] || $out['from'] <= $out['to'], 'The start date must be on or before the end date.', 400);
    return $out;
}
function gw_filters(array $p): array {
    return gw_dates($p) + [
        'q'=>gw_text($p,'q'), 'status'=>gw_choice($p,'status',STATUSES),
        'priority'=>gw_choice($p,'priority',gr_priorities()), 'category'=>gw_id($p,'category'),
        'assigned'=>gw_choice($p,'assigned',['all','me','unassigned','person'],'all'), 'counselor'=>gw_id($p,'counselor'),
        'due'=>gw_choice($p,'due',['all','overdue','today'],'all'),
        'sort'=>gw_choice($p,'sort',['recent','oldest','priority','due'],'recent'),
        'layout'=>gw_choice($p,'layout',['list','board'],'list'),
        'page'=>max(1,gw_id($p,'page'))
    ];
}
function gw_cases(array $u): array {
    $role = g_staff($u);
    if ($role === 'head') { $where = '1=1'; $args = []; }
    elseif ($role === 'counselor') { $where = 't.assigned_to=?'; $args = [$u['id']]; }
    elseif (is_filer($u)) { $where = 't.user_id=?'; $args = [$u['id']]; }
    else return [];
    return array_map('gr_enrich', gall("SELECT t.*,c.name AS category,u.full_name AS requester,u.school_id AS requester_school_id,a.full_name AS counselor FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN users a ON a.id=t.assigned_to LEFT JOIN categories c ON c.id=t.category_id WHERE t.office_code='guide' AND $where ORDER BY t.updated_at DESC,t.id DESC", $args));
}
function gw_due(array $t): int { return strtotime($t['created_at']) + ($t['sla_hours'] ?? SLA_HOURS[$t['priority']] ?? SLA_HOURS['Normal']) * 3600; }
function gw_open(array $t): bool { return in_array($t['status'], ['Open','Reopened','In Progress','Waiting for User','Escalated'], true); }
function gw_filter_cases(array $cases, array $f, array $u): array {
    $rows = array_values(array_filter($cases, function($t) use($f,$u) {
        if ($f['q'] !== '' && mb_stripos(implode(' ', [$t['code'],$t['subject'],$t['requester'],$t['category'] ?? '',$t['counselor'] ?? '',$t['requester_school_id'] ?? '']), $f['q']) === false) return false;
        if ($f['status'] && $t['status'] !== $f['status']) return false;
        if ($f['priority'] && $t['priority'] !== $f['priority']) return false;
        if ($f['category'] && (int)$t['category_id'] !== $f['category']) return false;
        if ($f['assigned'] === 'me' && (int)$t['assigned_to'] !== (int)$u['id']) return false;
        if ($f['assigned'] === 'unassigned' && $t['assigned_to']) return false;
        if ($f['assigned'] === 'person' && (int)$t['assigned_to'] !== $f['counselor']) return false;
        $day = substr($t['created_at'],0,10);
        if (($f['from'] && $day < $f['from']) || ($f['to'] && $day > $f['to'])) return false;
        if ($f['due'] !== 'all') {
            if (!in_array($t['status'], WORKING_STATUSES, true)) return false;
            if ($f['due'] === 'overdue' && gw_due($t) > time()) return false;
            if ($f['due'] === 'today' && date('Y-m-d',gw_due($t)) !== date('Y-m-d')) return false;
        }
        return true;
    }));
    $weights = ['Urgent'=>0,'High'=>1,'Normal'=>2,'Low'=>3];
    usort($rows, function($a,$b) use($f,$weights) {
        $v = match($f['sort']) {
            'oldest'=>strcmp($a['created_at'],$b['created_at']),
            'priority'=>($weights[$a['priority']] ?? 9) <=> ($weights[$b['priority']] ?? 9),
            'due'=>gw_due($a) <=> gw_due($b),
            default=>strcmp($b['updated_at'],$a['updated_at'])
        };
        return $v ?: (int)$b['id'] <=> (int)$a['id'];
    });
    return $rows;
}
function gw_link(string $view, array $filters = [], array $changes = []): string {
    $p = array_merge($filters,$changes); unset($p['view']);
    return url('guidance/index.php?'.http_build_query(['view'=>$view]+$p));
}
function gw_page(array $rows, int $page, int $size = 25): array {
    $pages = max(1,(int)ceil(count($rows)/$size)); $page = min($page,$pages);
    return ['rows'=>array_slice($rows,($page-1)*$size,$size),'page'=>$page,'pages'=>$pages,'total'=>count($rows)];
}
function gw_directory(array $u): array {
    $role = g_staff($u); gx(in_array($role,['head','counselor'],true));
    if ($role === 'head') return gall("SELECT id,full_name,school_id,email,status FROM users WHERE role='student' AND verification='verified' ORDER BY full_name,id");
    return gall("SELECT u.id,u.full_name,u.school_id,u.email,u.status FROM users u WHERE u.role='student' AND u.verification='verified' AND (EXISTS(SELECT 1 FROM tickets t WHERE t.user_id=u.id AND t.office_code='guide' AND t.assigned_to=?) OR EXISTS(SELECT 1 FROM gd_appointments a WHERE a.user_id=u.id AND a.counselor_id=?)) ORDER BY u.full_name,u.id",[$u['id'],$u['id']]);
}
function gw_report_filters(array $p): array {
    return gw_dates($p) + ['category'=>gw_id($p,'category'),'counselor'=>gw_id($p,'counselor')] + (gr_ready()?['program'=>gw_text($p,'program'),'year_level'=>gw_text($p,'year_level',40),'section'=>gw_text($p,'section',80)]:[]);
}
function gw_suppressed(int $n): string|int { return $n < 5 ? 'Suppressed (<5)' : $n; }
function gw_average(array $values, int $digits = 1): string|float {
    return count($values) < 5 ? 'Suppressed (<5)' : round(array_sum($values)/count($values),$digits);
}
/** Aggregate-only output: raw case/student IDs, text and rating comments never leave this function. */
function gw_analytics(array $u, array $f): array {
    require_once __DIR__.'/reports.php';
    $report = rp_report($u, $f);
    $out = [];
    foreach ($report['sections'] as $section) {
        $out[$section['title']] = array_map(fn ($item) => [$item['label'], $item['display']], $section['items']);
    }
    return $out;
}

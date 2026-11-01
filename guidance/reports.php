<?php
/** Restricted report model. Only released aggregate values leave this file. */
if (!function_exists('g_staff')) { http_response_code(404); exit; }

function rp_fail(string $message, int $status = 400): never { throw new RuntimeException($message, $status); }

function rp_role(array $u): string
{
    $role = g_staff($u);
    if ($role === 'administrator') { $role = 'operations'; }
    if (!in_array($role, ['head', 'counselor', 'operations'], true)) {
        rp_fail('Staff reports are not available for this account.', 403);
    }
    return $role;
}

function rp_text($value): string
{
    if (is_array($value) || is_object($value)) { rp_fail('Invalid report filter.'); }
    if (preg_match('/[\x00-\x1F\x7F]/', (string) $value)) { rp_fail('Invalid control character in report filter.'); }
    return trim((string) $value);
}

function rp_id($value): int
{
    $s = rp_text($value);
    if ($s === '') { return 0; }
    if (!preg_match('/^\d{1,9}$/D', $s)) { rp_fail('Invalid report selection.'); }
    return (int) $s;
}

function rp_filters(array $u, array $input): array
{
    $role = rp_role($u);
    $mode = rp_text($input['mode'] ?? ($role === 'operations' ? 'activities' : 'clinical'));
    if (!in_array($mode, ['clinical', 'activities'], true)) { rp_fail('Unknown report area.'); }
    if (($role === 'operations' && $mode !== 'activities') || ($role === 'counselor' && $mode !== 'clinical')) {
        rp_fail('This report area is not available for your role.', 403);
    }
    $preset = rp_text($input['preset'] ?? (isset($input['from']) || isset($input['to']) ? 'custom' : '30'));
    $today = new DateTimeImmutable('today');
    switch ($preset) {
        case '30': $from = $today->modify('-29 days'); $to = $today; break;
        case '90': $from = $today->modify('-89 days'); $to = $today; break;
        case 'month': $from = $today->modify('first day of this month'); $to = $today; break;
        case 'year': $from = $today->setDate((int) $today->format('Y'), 1, 1); $to = $today; break;
        case 'custom':
            $dates = [];
            foreach (['from', 'to'] as $key) {
                $s = rp_text($input[$key] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $s)) { rp_fail('Use valid report dates in YYYY-MM-DD format.'); }
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
                if (!$d || $d->format('Y-m-d') !== $s || $s < '1971-01-01' || $s > '9998-12-31') {
                    rp_fail('Use valid report dates in YYYY-MM-DD format.');
                }
                $dates[] = $d;
            }
            [$from, $to] = $dates;
            break;
        default: rp_fail('Unknown reporting period.');
    }
    if ($from > $to || $from->diff($to)->days > 365) { rp_fail('Choose a reporting period of 1 to 366 days.'); }
    $category = rp_id($input['category'] ?? 0);
    $counselor = rp_id($input['counselor'] ?? 0);
    $academic = [];
    foreach (['academic_program' => 120, 'year_level' => 40, 'section' => 80] as $key => $limit) {
        $academic[$key] = rp_text($input[$key] ?? '');
        if (mb_strlen($academic[$key]) > $limit) { rp_fail('Academic report filter is too long.'); }
    }
    // Preserve legacy clinical program-name URLs; activity programs use numeric IDs only.
    $legacyProgram = rp_text($input['program'] ?? '');
    if ($mode === 'clinical' && $academic['academic_program'] === '' && !in_array($legacyProgram, ['', '0'], true)) {
        $academic['academic_program'] = $legacyProgram;
        if (mb_strlen($legacyProgram) > 120) { rp_fail('Academic report filter is too long.'); }
    }
    if ($mode === 'clinical' && !in_array($legacyProgram, ['', '0', $academic['academic_program']], true)) { rp_fail('Conflicting academic program filters.'); }
    if (array_filter($academic, fn ($v) => $v !== '')) {
        if ($mode !== 'clinical') { rp_fail('Academic cohort filters apply to clinical reports only.', $role === 'operations' ? 403 : 400); }
        if (!function_exists('gr_ready') || !gr_ready()) { rp_fail('Academic metadata is not enabled in this installation.'); }
    }
    $program = $mode === 'activities' ? rp_id($input['program'] ?? 0) : 0;
    if ($role === 'operations' && ($category || $counselor)) { rp_fail('Clinical filters are not available for Operations.', 403); }
    if ($role === 'counselor') {
        if ($counselor && $counselor !== (int) $u['id']) { rp_fail('Counselors can report only on their own assignments.', 403); }
        $counselor = (int) $u['id'];
    }
    if ($mode === 'activities' && ($category || $counselor)) { rp_fail('Clear clinical filters before selecting activity reports.'); }
    if ($mode === 'clinical' && $program) { rp_fail('Program filters apply to activity reports only.'); }
    if ($category && !gone('SELECT id FROM categories WHERE id=? AND office_code=?', [$category, 'guide'])) { rp_fail('Unknown Guidance service.'); }
    if ($role === 'head' && $counselor && !gone("SELECT s.user_id FROM gd_staff s JOIN users u ON u.id=s.user_id WHERE s.user_id=? AND s.guidance_role IN ('head','counselor') AND u.office_code=?", [$counselor, 'guide'])) { rp_fail('Unknown Guidance counselor.'); }
    if ($program && !gone('SELECT id FROM gd_programs WHERE id=?', [$program])) { rp_fail('Unknown activity program.'); }
    $days = $from->diff($to)->days + 1;
    return ['role' => $role, 'mode' => $mode, 'preset' => $preset, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
        'category' => $category, 'counselor' => $counselor, 'program' => $program, 'days' => $days,
        'previous_from' => $from->modify('-'.$days.' days')->format('Y-m-d'), 'previous_to' => $from->modify('-1 day')->format('Y-m-d')] + $academic;
}

function rp_safe_label(string $value): string
{
    // Configuration labels only; never feed student-provided case/event narrative here.
    return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value), 0, 160);
}

function rp_support(array $rows): int
{
    return count(array_unique(array_column($rows, 'student')));
}

function rp_measure(string $label, array $rows, string $note = '', ?float $value = null, string $unit = 'count'): array
{
    $allowed = count($rows) === 0 || rp_support($rows) >= 5;
    $n = $value ?? count($rows);
    if ($unit !== 'count' && count($rows) === 0) {
        return ['label' => $label, 'value' => null, 'display' => 'Not available', 'unit' => $unit, 'note' => $note];
    }
    return ['label' => $label, 'value' => $allowed ? $n : null,
        'display' => $allowed ? ($unit === 'count' ? number_format($n) : number_format($n, 1).($unit === 'percent' ? '%' : ($unit === 'score' ? ' / 5' : ' h'))) : 'Withheld',
        'unit' => $unit, 'note' => $note];
}

function rp_public(string $label, int $n, string $note = ''): array
{
    return ['label' => $label, 'value' => $n, 'display' => number_format($n), 'unit' => 'count', 'note' => $note];
}

function rp_breakdown(string $title, array $groups, string $note = ''): array
{
    $safe = true;
    foreach ($groups as $rows) { if ($rows && rp_support($rows) < 5) { $safe = false; } }
    $items = [];
    foreach ($groups as $label => $rows) {
        $items[] = ['label' => rp_safe_label((string) $label), 'value' => $safe ? count($rows) : null,
            'display' => $safe ? number_format(count($rows)) : 'Withheld', 'unit' => 'count', 'note' => ''];
    }
    return ['title' => $title, 'note' => $note.($safe ? '' : ' Entire breakdown withheld because one or more groups has fewer than five distinct students.'), 'items' => $items];
}

function rp_partition(array $rows, string $key, array $labels): array
{
    $groups = array_fill_keys(array_values($labels), []);
    foreach ($rows as $r) { $label = $labels[$r[$key]] ?? 'Other'; $groups[$label][] = $r; }
    return $groups;
}

function rp_relation(array $total, array $part, string $totalLabel, string $partLabel, string $note): array
{
    $ids = array_fill_keys(array_column($part, '_key'), true);
    $rest = array_filter($total, fn ($r) => !isset($ids[$r['_key']]));
    $safe = (!$part || rp_support($part) >= 5) && (!$rest || rp_support($rest) >= 5);
    $a = rp_measure($totalLabel, $total, $note);
    $b = rp_measure($partLabel, $part, $note);
    $rate = rp_measure($partLabel.' rate', $total, $note, $total ? 100 * count($part) / count($total) : 0, 'percent');
    if (!$safe) { foreach ([&$b, &$rate] as &$item) { $item['value'] = null; $item['display'] = 'Withheld'; } unset($item); }
    return [$a, $b, $rate];
}

function rp_fetch(string $sql, array $params): array
{
    // Never silently truncate a report. These predicates are indexed/bounded by period or active status.
    $rows = gall($sql.' LIMIT 20001', $params);
    if (count($rows) > 20000) { rp_fail('This report exceeds the safe processing limit. Narrow the period or filters; no partial totals were produced.', 422); }
    return $rows;
}

function rp_cohort_where(array $f, string $studentColumn, array &$params): string
{
    $conditions = [];
    foreach (['academic_program' => 'program', 'year_level' => 'year_level', 'section' => 'section'] as $key => $column) {
        if ($f[$key] !== '') { $conditions[] = 'm.'.$column.'=?'; $params[] = $f[$key]; }
    }
    return $conditions ? 'EXISTS(SELECT 1 FROM gd_plus_students m WHERE m.user_id='.$studentColumn.' AND '.implode(' AND ', $conditions).')' : '';
}

function rp_case_rows(array $f, string $start, string $end, bool $queue = false, bool $minimal = false): array
{
    $where = ['t.office_code=?']; $params = ['guide'];
    if ($queue) { $where[] = "t.status IN ('Open','Reopened','In Progress','Waiting for User','Escalated')"; }
    else { $where[] = 't.created_at>=? AND t.created_at<?'; $params[] = $start.' 00:00:00'; $params[] = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d').' 00:00:00'; }
    if ($f['category']) { $where[] = 't.category_id=?'; $params[] = $f['category']; }
    if ($f['counselor']) { $where[] = 't.assigned_to=?'; $params[] = $f['counselor']; }
    $cohort = rp_cohort_where($f, 't.user_id', $params);
    if ($cohort) { $where[] = $cohort; }
    if ($minimal) { return rp_fetch('SELECT t.user_id AS student FROM tickets t WHERE '.implode(' AND ', $where), $params); }
    $plus = function_exists('gr_ready') && gr_ready();
    $priority = $plus ? "COALESCE(NULLIF(pc.priority_override,''),t.priority)" : 't.priority';
    $target = $plus ? "CASE $priority WHEN 'Urgent' THEN COALESCE(ps.urgent_hours,24) WHEN 'High' THEN COALESCE(ps.high_hours,48) WHEN 'Low' THEN COALESCE(ps.low_hours,120) ELSE COALESCE(ps.normal_hours,72) END" : "CASE t.priority WHEN 'Urgent' THEN 24 WHEN 'Low' THEN 120 ELSE 72 END";
    $join = $plus ? ' LEFT JOIN gd_plus_cases pc ON pc.ticket_id=t.id LEFT JOIN gd_plus_services ps ON ps.category_id=t.category_id' : '';
    return rp_fetch("SELECT t.id AS _key,t.user_id AS student,t.status,$priority AS priority,t.created_at,t.resolved_at,t.assigned_to,fb.rating,
        COALESCE(c.name,'Uncategorized') AS service,COALESCE(a.full_name,'Unassigned') AS counselor,$target AS target_hours,
        (SELECT MIN(r.created_at) FROM replies r JOIN users ru ON ru.id=r.user_id WHERE r.ticket_id=t.id AND r.is_internal=0 AND ru.role='admin' AND r.created_at>=t.created_at) AS first_reply
        FROM tickets t LEFT JOIN gd_feedback fb ON fb.ticket_id=t.id LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN users a ON a.id=t.assigned_to $join WHERE ".implode(' AND ', $where), $params);
}

function rp_report(array $u, array $input): array
{
    global $pdo;
    $own = !$pdo->inTransaction();
    if ($own) { $pdo->beginTransaction(); }
    try {
        $result = rp_build($u, $input);
        if ($own) { $pdo->commit(); }
        return $result;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function rp_build(array $u, array $input): array
{
    // Revalidation here is intentional: callers cannot bypass authorization by constructing filter arrays.
    $f = rp_filters($u, $input);
    $now = new DateTimeImmutable();
    $report = ['filters' => $f, 'title' => $f['mode'] === 'activities' ? 'Activity operations report' : ($f['role'] === 'counselor' ? 'My caseload report' : 'Guidance service report'),
        'scope' => $f['role'] === 'counselor' ? 'Your currently assigned cases and appointments only' : ($f['mode'] === 'activities' ? 'Guidance activities and programs only' : 'Guidance-wide clinical services'),
        'generated' => $now->format('Y-m-d H:i:s').' · Asia/Manila (UTC'.$now->format('P').')', 'sections' => [], 'definitions' => [],
        'privacy' => 'Restricted staff use. Withheld means fewer than five distinct students support a sensitive value or related breakdown. Zero means no matching records; Not available means no eligible sample. This is not anonymous public data: repeated filters and outside knowledge can still reveal patterns.'];
    $selections = [];
    if ($f['category']) { $selections[] = 'Service: '.rp_safe_label(gone('SELECT name FROM categories WHERE id=?', [$f['category']])['name']); }
    if ($f['role'] === 'head' && $f['counselor']) { $selections[] = 'Counselor: '.rp_safe_label(gone('SELECT full_name AS name FROM users WHERE id=?', [$f['counselor']])['name']); }
    if ($f['program']) { $selections[] = 'Program: '.rp_safe_label(gone('SELECT title FROM gd_programs WHERE id=?', [$f['program']])['title']); }
    foreach (['academic_program' => 'Academic program', 'year_level' => 'Year level', 'section' => 'Section'] as $key => $label) {
        if ($f[$key] !== '') { $selections[] = $label.': '.rp_safe_label($f[$key]); }
    }
    $report['selection'] = $selections ? implode(' · ', $selections) : ($f['mode'] === 'activities' ? 'All activity programs, including standalone activities' : ($f['role'] === 'counselor' ? 'All services · your current assignments only' : 'All permitted services and assignments'));
    if ($f['mode'] === 'activities') { rp_activities($report, $f, $now); }
    else { rp_clinical($report, $f, $now); }
    return $report;
}

function rp_clinical(array &$out, array $f, DateTimeImmutable $now): void
{
    $rows = rp_case_rows($f, $f['from'], $f['to']);
    $prior = rp_case_rows($f, $f['previous_from'], $f['previous_to'], false, true);
    $queue = rp_case_rows($f, '', '', true);
    $current = rp_measure('Cases received', $rows, 'Cases created during the selected period; current assignment and service filters.');
    $previous = rp_measure('Previous-period cases received', $prior, $f['previous_from'].' to '.$f['previous_to'].' — equal-length intake window.');
    $delta = ['label' => 'Intake change', 'value' => null, 'display' => 'Withheld', 'unit' => 'text', 'note' => 'Volume comparison only; not a performance score.'];
    if ($current['value'] !== null && $previous['value'] !== null) {
        $d = count($rows) - count($prior);
        if ($d !== 0 && abs($d) < 5) { $previous['value'] = null; $previous['display'] = 'Withheld'; $delta['note'] .= ' Small changes are withheld.'; }
        else { $delta['display'] = ($d > 0 ? '+' : '').number_format($d).' cases'; if (count($prior)) { $delta['display'] .= ' ('.($d > 0 ? '+' : '').number_format(100 * $d / count($prior), 1).'%)'; } else { $delta['display'] .= '; no previous baseline'; } }
    }
    $out['sections'][] = ['title' => 'Selected-period intake', 'note' => 'Created '.$f['from'].' through '.$f['to'].' inclusive. Status, service and assignment are current, not a historical end-of-period snapshot.', 'items' => [$current, $previous, $delta]];
    $out['sections'][] = rp_breakdown('Current status of selected intake', rp_partition($rows, 'status', ['Open' => 'Open', 'In Progress' => 'In Progress', 'Reopened' => 'Reopened', 'Waiting for User' => 'Waiting for User', 'Escalated' => 'Escalated', 'Cancelled' => 'Cancelled', 'Resolved' => 'Resolved', 'Closed' => 'Closed']));
    $out['sections'][] = rp_breakdown('Selected intake by service', rp_partition($rows, 'service', array_combine(array_unique(array_column($rows, 'service')), array_unique(array_column($rows, 'service')))));
    // Month bins include zero months. All bins are suppressed together if any occupied bin is small.
    $months = []; $cursor = (new DateTimeImmutable($f['from']))->modify('first day of this month'); $last = substr($f['to'], 0, 7);
    while ($cursor->format('Y-m') <= $last) { $months[$cursor->format('Y-m')] = []; $cursor = $cursor->modify('+1 month'); }
    foreach ($rows as $r) { $months[substr($r['created_at'], 0, 7)][] = $r; }
    $out['sections'][] = rp_breakdown('Monthly intake trend', $months, 'Cases created in each month within the selected dates. First and last month may be partial.');
    $reply = []; $resolved = [];
    foreach ($rows as $r) {
        $created = strtotime($r['created_at']);
        if ($r['first_reply'] && strtotime($r['first_reply']) <= $now->getTimestamp()) { $reply[] = ['student' => $r['student'], 'hours' => (strtotime($r['first_reply']) - $created) / 3600]; }
        if (in_array($r['status'], ['Resolved', 'Closed'], true) && $r['resolved_at'] && strtotime($r['resolved_at']) >= $created && strtotime($r['resolved_at']) <= $now->getTimestamp()) {
            $resolved[] = ['student' => $r['student'], 'hours' => (strtotime($r['resolved_at']) - $created) / 3600];
        }
    }
    $timings = [];
    foreach ([['First public staff response', $reply], ['Resolution', $resolved]] as [$label, $sample]) {
        $hours = array_column($sample, 'hours'); sort($hours, SORT_NUMERIC); $n = count($hours);
        $median = $n ? ($n % 2 ? $hours[intdiv($n, 2)] : ($hours[$n / 2 - 1] + $hours[$n / 2]) / 2) : 0;
        $timings[] = rp_measure($label.' — median', $sample, 'Selected intake with a valid timestamp; calendar hours, not business hours.', $median, 'hours');
        $timings[] = rp_measure($label.' — measured cases', $sample, 'Missing, future or invalid timestamps excluded; reopened cases excluded from resolution.');
    }
    $out['sections'][] = ['title' => 'Response and resolution', 'note' => 'These are observed results for the selected intake as of generation, not same-period completions. Newer cohorts have had less time to mature.', 'items' => $timings];
    $ratings = array_values(array_filter($rows, fn ($r) => $r['rating'] !== null && (int) $r['rating'] >= 1 && (int) $r['rating'] <= 5));
    $out['sections'][] = ['title' => 'Student feedback', 'note' => 'Voluntary numeric feedback on the selected intake. Not representative of students who did not respond; no feedback text is queried.', 'items' => [
        rp_measure('Feedback responses', $ratings), rp_measure('Mean satisfaction', $ratings, 'Five-point scale; distinct-student threshold applies.', $ratings ? array_sum(array_column($ratings, 'rating')) / count($ratings) : 0, 'score')]];
    $active = rp_measure('Active cases now', $queue, 'All creation dates; Open, Reopened, In Progress, Waiting for User or Escalated. Same current service/assignment filters.');
    $out['sections'][] = ['title' => 'Current workload snapshot', 'note' => 'As of '.$out['generated'].'. The selected intake dates do NOT limit this snapshot.', 'items' => [$active]];
    $age = ['0–7 days' => [], '8–30 days' => [], '31–90 days' => [], '91+ days' => []];
    $targets = ['Within current target' => [], 'Beyond current target' => [], 'Waiting for User (excluded)' => []];
    foreach ($queue as $r) {
        $hours = max(0, ($now->getTimestamp() - strtotime($r['created_at'])) / 3600); $days = floor($hours / 24);
        $age[$days <= 7 ? '0–7 days' : ($days <= 30 ? '8–30 days' : ($days <= 90 ? '31–90 days' : '91+ days'))][] = $r;
        $targets[$r['status'] === 'Waiting for User' ? 'Waiting for User (excluded)' : ($hours > (int) $r['target_hours'] ? 'Beyond current target' : 'Within current target')][] = $r;
    }
    $out['sections'][] = rp_breakdown('Current queue age', $age, 'Elapsed calendar days since creation.');
    $out['sections'][] = rp_breakdown('Current queue against service targets', $targets, 'Age against CURRENT configured priority/service target. Waiting for User is excluded from overdue targets. No accumulated paused-time adjustment or historical SLA compliance is implied.');
    $out['sections'][] = rp_breakdown('Current queue priority', rp_partition($queue, 'priority', ['Low' => 'Low', 'Normal' => 'Normal', 'High' => 'High', 'Urgent' => 'Urgent']));
    if ($f['role'] === 'head') {
        $staffGroups = [];
        foreach ($queue as $r) { $label = $r['assigned_to'] ? rp_safe_label($r['counselor']).' (staff '.$r['assigned_to'].')' : 'Unassigned'; $staffGroups[$label][] = $r; }
        $out['sections'][] = rp_breakdown('Current counselor workload', $staffGroups, 'Staff names only; counts follow the same small-group protections.');
    }
    $params = [$f['from'].' 00:00:00', (new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
    $where = 'starts_at>=? AND starts_at<?';
    if ($f['counselor']) { $where .= ' AND counselor_id=?'; $params[] = $f['counselor']; }
    $cohort = rp_cohort_where($f, 'gd_appointments.user_id', $params);
    if ($cohort) { $where .= ' AND '.$cohort; }
    if (!$f['category']) {
        $appts = rp_fetch('SELECT id AS _key,user_id AS student,status FROM gd_appointments WHERE '.$where, $params);
        $out['sections'][] = rp_breakdown('Appointments in selected period', rp_partition($appts, 'status', ['requested' => 'Requested', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']), 'Appointment start date; current status. Same counselor and current academic cohort scope as cases. No concerns or participant identities.');
    } else { $out['definitions'][] = 'Appointment totals are omitted when a service filter is used: appointments do not store a reliable clinical service category.'; }
    $out['definitions'][] = 'A case is a ticket, not a unique student. The privacy threshold counts distinct students behind each result; repeated tickets from one student cannot meet it.';
    $out['definitions'][] = 'First response uses the earliest non-private reply by a currently classified staff account after case creation; automated messages without such an account are excluded. Resolution requires current Resolved/Closed status and a valid resolved timestamp.';
    $out['definitions'][] = 'Academic program, year and section use current Guidance-recorded student metadata, not historical enrollment. They apply to intake, preceding intake, current queue and appointments within the same staff authorization. Verify cohort metadata against the school source before interpreting results.';
    $out['definitions'][] = 'Filters use current case category/assignment. Reassignment or recategorization can change both current and previous-period results. Queue figures are not a historical backlog series.';
}

function rp_activities(array &$out, array $f, DateTimeImmutable $now): void
{
    $params = [$f['from'].' 00:00:00', (new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
    $where = 'e.starts_at>=? AND e.starts_at<?';
    if ($f['program']) { $where .= ' AND e.program_id=?'; $params[] = $f['program']; }
    $events = rp_fetch('SELECT e.id,e.status,e.kind,e.capacity,e.starts_at,e.ends_at,e.certificates_enabled FROM gd_events e WHERE '.$where, $params);
    $eventMap = array_column($events, null, 'id');
    $items = [rp_public('Scheduled activities', count($events), 'Activities starting within selected dates, including cancelled and draft.')];
    foreach (['draft', 'published', 'completed', 'cancelled'] as $status) { $items[] = rp_public(ucfirst($status).' activities', count(array_filter($events, fn ($e) => $e['status'] === $status))); }
    $out['sections'][] = ['title' => 'Activity portfolio', 'note' => 'Activity start dates '.$f['from'].' through '.$f['to'].'. Activity counts are exact; participant measures use distinct-student protections.', 'items' => $items];
    $waitJoin = function_exists('gr_ready') && gr_ready() ? ' LEFT JOIN gd_plus_waitlist w ON w.invite_id=i.id' : '';
    $stateSql = $waitJoin ? "CASE WHEN i.state<>'registered' AND w.state='waiting' THEN 'waitlisted' ELSE i.state END" : 'i.state';
    $invites = rp_fetch('SELECT i.id AS _key,i.user_id AS student,i.event_id,' . $stateSql . ' AS state,i.attendance,c.id AS certificate FROM gd_invites i JOIN gd_events e ON e.id=i.event_id LEFT JOIN gd_certificates c ON c.event_id=i.event_id AND c.user_id=i.user_id' . $waitJoin . ' WHERE '.$where." AND e.status<>'cancelled'", $params);
    $previousParams = [$f['previous_from'].' 00:00:00', (new DateTimeImmutable($f['previous_to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
    if ($f['program']) { $previousParams[] = $f['program']; }
    $previousCount = (int) gq('SELECT COUNT(*) FROM gd_events e WHERE '.$where, $previousParams)->fetchColumn();
    $out['sections'][] = ['title' => 'Portfolio comparison', 'note' => 'Previous equal-length window '.$f['previous_from'].' to '.$f['previous_to'].'. All statuses; measures scheduling volume, not impact.', 'items' => [rp_public('Previous-period activities', $previousCount), rp_public('Activity count change', count($events) - $previousCount)]];
    $out['sections'][] = ['title' => 'Participation pipeline', 'note' => 'Invitation records for non-cancelled activities starting in the period; a student participating in two activities contributes two records.', 'items' => [rp_measure('Participant records', $invites)]];
    $out['definitions'][] = 'Waitlisted means a current waiting-list entry for a non-registered invite; it is separated from invited/declined states and is not a confirmed seat.';
    $out['sections'][] = rp_breakdown('Current registration states', rp_partition($invites, 'state', ['invited' => 'Invited / no response', 'registered' => 'Registered', 'waitlisted' => 'Waitlisted', 'declined' => 'Declined']));
    $capacity = array_sum(array_map(fn ($e) => in_array($e['status'], ['published','completed'], true) ? (int) $e['capacity'] : 0, $events));
    $registered = array_values(array_filter($invites, fn ($i) => $i['state'] === 'registered' && in_array($eventMap[$i['event_id']]['status'], ['published','completed'], true)));
    $utilization = rp_measure('Registration / configured seats', $registered, 'Can exceed 100% if capacity was reduced after registrations.', $capacity ? 100 * count($registered) / $capacity : 0, 'percent');
    if (!$registered && $capacity) { $utilization['display'] = '0.0%'; $utilization['value'] = 0; }
    if (!$capacity) { $utilization['display'] = 'Not available'; $utilization['value'] = null; }
    $out['sections'][] = ['title' => 'Registration capacity', 'note' => 'Published and completed activities only. Seats are summed across activities, not unique people; draft and cancelled capacities excluded.', 'items' => [rp_public('Configured seats', $capacity), rp_measure('Confirmed registrations', $registered), $utilization]];
    $ended = array_values(array_filter($invites, fn ($i) => $i['state'] === 'registered' && strtotime($eventMap[$i['event_id']]['ends_at']) <= $now->getTimestamp()));
    $out['sections'][] = rp_breakdown('Attendance marking — ended activities', rp_partition($ended, 'attendance', ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused', 'pending' => 'Pending marking']), 'Registered participants only; cancelled activities excluded. Pending is not silently treated as absent.');
    $denom = array_values(array_filter($ended, fn ($i) => $i['attendance'] !== 'excused'));
    $attended = array_values(array_filter($denom, fn ($i) => in_array($i['attendance'], ['present', 'late'], true)));
    $out['sections'][] = ['title' => 'Attendance coverage', 'note' => 'Attended = present + late. Denominator = registered in ended activities, excluding excused. Pending marks remain in the denominator, so this rate can change.', 'items' => rp_relation($denom, $attended, 'Eligible attendance records', 'Attended records', 'Participant-event records; not unique attendees.')];
    $eligible = array_values(array_filter($invites, fn ($i) => $eventMap[$i['event_id']]['status'] === 'completed' && (int) $eventMap[$i['event_id']]['certificates_enabled'] === 1 && $i['state'] === 'registered' && in_array($i['attendance'], ['present', 'late'], true)));
    $gap = array_values(array_filter($eligible, fn ($i) => !$i['certificate']));
    $out['sections'][] = ['title' => 'Certificate follow-through', 'note' => 'Exact issuance eligibility: completed activity + certificates enabled + registered + present/late. Existing certificates are excluded from the gap.', 'items' => rp_relation($eligible, $gap, 'Currently eligible certificate records', 'Certificates still to issue', 'Current eligibility for activities starting in the selected dates; not certificates issued during the period.')];
    $monthly = [];
    foreach ($events as $e) { $key = substr($e['starts_at'], 0, 7); $monthly[$key] = ($monthly[$key] ?? 0) + 1; } ksort($monthly);
    $out['sections'][] = ['title' => 'Activities by month', 'note' => 'All statuses; partial months follow the selected dates.', 'items' => array_map(fn ($month, $n) => rp_public($month, $n), array_keys($monthly), array_values($monthly))];
    $out['definitions'][] = 'The date filter applies to activity start dates, not invitation, registration, attendance-marking or certificate-issuance timestamps. Changes to those records update this report.';
    $out['definitions'][] = 'Cancelled activities remain visible in portfolio totals but are excluded from participant, attendance and certificate measures. No individual participant list or clinical dataset is queried for this report.';
}

function rp_export_rows(array $report): array
{
    $f = $report['filters'];
    $rows = [['Report', 'Title', $report['title'], ''], ['Report', 'Scope', $report['scope'], $report['selection']],
        ['Report', 'Period', $f['from'].' to '.$f['to'], 'Inclusive dates'], ['Report', 'Generated', $report['generated'], ''], ['Report', 'Privacy', $report['privacy'], '']];
    foreach ($report['sections'] as $section) {
        if (!$section['items']) { $rows[] = [$section['title'], 'No matching groups', 'No data', $section['note']]; }
        foreach ($section['items'] as $item) { $rows[] = [$section['title'], $item['label'], $item['display'], trim($section['note'].' '.$item['note'])]; }
    }
    foreach ($report['definitions'] as $text) { $rows[] = ['Definitions', '', $text, '']; }
    return $rows;
}

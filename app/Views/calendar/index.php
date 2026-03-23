<?php
$basePath = $basePath ?? '';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Compliance Calendar</h1>
        <p class="page-subtitle">Compliance Management Overview</p>
    </div>
    <a href="<?= $basePath ?>/dashboard" class="btn btn-secondary btn-sm">Dashboard</a>
</div>

<div class="dashboard-calendar-span calendar-page-standalone">
    <div class="card compliance-calendar-card compliance-calendar-ref">
        <div class="card-header compliance-cal-header-ref">
            <h3 class="card-title">Compliance Calendar</h3>
            <div class="cal-month-nav-ref">
                <?php
                $calMonth = $calendarMonth ?? date('Y-m');
                $prevMonth = date('Y-m', strtotime($calMonth . '-01 -1 month'));
                $nextMonth = date('Y-m', strtotime($calMonth . '-01 +1 month'));
                $monthLabel = date('F Y', strtotime($calMonth . '-01'));
                ?>
                <a href="?cal_month=<?= htmlspecialchars($prevMonth) ?>" class="cal-nav-arrow" aria-label="Previous month">&lt;</a>
                <span class="cal-month-title-ref"><?= htmlspecialchars($monthLabel) ?></span>
                <a href="?cal_month=<?= htmlspecialchars($nextMonth) ?>" class="cal-nav-arrow" aria-label="Next month">&gt;</a>
            </div>
        </div>
        <div class="compliance-calendar-grid cal-grid-ref">
            <?php
            foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dh) {
                echo '<span class="cal-head cal-head-ref">' . $dh . '</span>';
            }
            $calStartM = ($calendarMonth ?? date('Y-m')) . '-01';
            $daysInMonth = (int) date('t', strtotime($calStartM));
            $firstDow = (int) date('w', strtotime($calStartM));
            for ($i = 0; $i < $firstDow; $i++) {
                echo '<span class="cal-cell cal-empty cal-cell-ref-empty"></span>';
            }
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = ($calendarMonth ?? date('Y-m')) . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $events = $calendarEvents[$date] ?? [];
                $hasEvent = count($events) > 0;
                echo '<div class="cal-cell cal-cell-ref" data-date="' . htmlspecialchars($date) . '" data-events="' . htmlspecialchars(json_encode($events)) . '" role="button" tabindex="0">';
                echo '<span class="cal-num-ref">' . $day . '</span>';
                if ($hasEvent) {
                    echo '<span class="cal-event-square" aria-hidden="true"></span>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="card calendar-day-events-card">
        <div class="card-header calendar-day-events-header">
            <h3 class="card-title" id="cal-selected-title-ref-cal">—</h3>
        </div>
        <div class="calendar-day-events-body" id="cal-selected-body-ref-cal">
            <div class="cal-selected-empty">
                <i class="far fa-calendar-alt cal-selected-empty-icon"></i>
                <p class="text-muted mb-0">No events on this date</p>
            </div>
        </div>
    </div>

    <div class="card upcoming-events-panel upcoming-events-ref">
        <div class="card-header">
            <h3 class="card-title">Upcoming Events</h3>
            <a href="<?= $basePath ?>/compliance?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d', strtotime('+30 days')) ?>" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <?php if (!empty($upcomingDue)): ?>
        <ul class="upcoming-events-list-ref">
            <?php foreach (array_slice($upcomingDue, 0, 10) as $u): ?>
            <?php
            $due = $u['due_date'];
            $st = $u['status'] ?? '';
            if (in_array($st, ['approved', 'completed'], true)) {
                $pillClass = 'upcoming-pill-approved';
                $pillText = 'approved';
            } elseif (in_array($st, ['submitted', 'under_review'], true)) {
                $pillClass = 'upcoming-pill-review';
                $pillText = 'submitted for review';
            } else {
                $pillClass = 'upcoming-pill-pending';
                $pillText = 'pending';
            }
            $rangeStart = !empty($u['start_date']) ? $u['start_date'] : (!empty($u['expected_date']) ? $u['expected_date'] : date('Y-m-d', strtotime($due . ' -6 days')));
            if ($rangeStart > $due) {
                $rangeStart = $due;
            }
            $rangeLabel = date('M j', strtotime($rangeStart)) . ' - ' . date('M j', strtotime($due));
            ?>
            <li class="upcoming-event-row-ref">
                <a href="<?= $basePath ?>/compliance/view/<?= (int)$u['id'] ?>" class="upcoming-event-link-ref">
                    <span class="upcoming-event-main-ref">
                        <span class="upcoming-event-title-ref"><?= htmlspecialchars($u['title']) ?></span>
                        <span class="upcoming-event-dates-ref"><?= htmlspecialchars($rangeLabel) ?></span>
                    </span>
                    <span class="upcoming-pill <?= $pillClass ?>"><?= htmlspecialchars($pillText) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-muted mb-0 px-card-pad">No upcoming events.</p>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    var basePath = <?= json_encode($basePath ?? '') ?>;
    var cells = document.querySelectorAll('.calendar-page-standalone .cal-grid-ref .cal-cell-ref[data-date]');
    var titleEl = document.getElementById('cal-selected-title-ref-cal');
    var bodyEl = document.getElementById('cal-selected-body-ref-cal');
    if (!cells.length || !titleEl || !bodyEl) return;
    function dedupeEvents(events) {
        var map = {};
        (events || []).forEach(function(ev) { if (!map[ev.compliance_id]) map[ev.compliance_id] = ev; });
        return Object.keys(map).map(function(k) { return map[k]; });
    }
    function renderPanel(dateStr, events) {
        var d = dateStr ? new Date(dateStr + 'T12:00:00') : null;
        titleEl.textContent = d ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        var list = dedupeEvents(events);
        if (!list.length) {
            bodyEl.innerHTML = '<div class="cal-selected-empty"><i class="far fa-calendar-alt cal-selected-empty-icon"></i><p class="text-muted mb-0">No events on this date</p></div>';
            return;
        }
        var html = '<ul class="cal-day-events-list-ref">';
        list.forEach(function(ev) {
            var typeLabel = { due: 'Due', overdue: 'Overdue', submitted: 'Submitted', review_pending: 'Review pending', approval_pending: 'Approval pending', completed: 'Completed', escalated: 'Escalated' }[ev.type] || (ev.type || '');
            html += '<li><a href="' + basePath + '/compliance/view/' + ev.compliance_id + '" class="cal-day-event-link-ref">';
            html += '<span class="cal-day-event-name-ref">' + (ev.title || ev.compliance_code || '') + '</span>';
            html += '<span class="cal-day-event-meta-ref">' + (ev.department || '') + ' · ' + typeLabel + '</span></a></li>';
        });
        html += '</ul>';
        bodyEl.innerHTML = html;
    }
    function selectCell(cell) {
        cells.forEach(function(c) { c.classList.remove('cal-selected-ref'); });
        if (cell) cell.classList.add('cal-selected-ref');
    }
    var calYm = cells[0].getAttribute('data-date').slice(0, 7);
    var today = new Date();
    var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
    var initial = null;
    if (todayStr.slice(0, 7) === calYm) {
        cells.forEach(function(c) { if (c.getAttribute('data-date') === todayStr) initial = c; });
    }
    if (!initial) {
        cells.forEach(function(c) {
            try { if (JSON.parse(c.getAttribute('data-events') || '[]').length && !initial) initial = c; } catch (e) {}
        });
    }
    if (!initial) initial = cells[0];
    selectCell(initial);
    renderPanel(initial.getAttribute('data-date'), JSON.parse(initial.getAttribute('data-events') || '[]'));
    cells.forEach(function(cell) {
        cell.addEventListener('click', function() {
            selectCell(this);
            var ev = [];
            try { ev = JSON.parse(this.getAttribute('data-events') || '[]'); } catch (e) {}
            renderPanel(this.getAttribute('data-date'), ev);
        });
        cell.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
    });
})();
</script>

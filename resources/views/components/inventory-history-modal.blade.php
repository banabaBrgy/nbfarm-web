<div class="modal-overlay" id="historyModal">
    <div class="modal-box history-modal-box">
        <div class="history-modal-head">
            <div>
                <h3>Stock History</h3>
                <p class="history-item-line" id="historyItemName">…</p>
            </div>
            <button type="button" class="modal-x" onclick="closeHistoryModal()" aria-label="Close">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="history-modal-body">
        <div class="history-toolbar">
        <div class="history-range-select">
            <span id="historyRangeLabel">Date</span>
            <div class="history-dropdown" id="historyRange" data-value="all">
                <button type="button" class="history-dropdown-toggle" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="historyRangeLabel">
                    <span class="history-dropdown-value">All dates</span>
                </button>
                <ul class="history-dropdown-menu" role="listbox" hidden>
                    <li><button type="button" role="option" data-range="all" aria-selected="true">All dates</button></li>
                    <li><button type="button" role="option" data-range="daily">Daily</button></li>
                    <li><button type="button" role="option" data-range="weekly">Weekly</button></li>
                    <li><button type="button" role="option" data-range="monthly">Monthly</button></li>
                    <li><button type="button" role="option" data-range="custom">Custom Date Range</button></li>
                </ul>
            </div>
        </div>

        <div id="historyCustomRange" class="history-custom-range" hidden>
            <div class="history-calendar" id="historyCalendar">
                <button type="button" class="history-calendar-toggle" aria-haspopup="dialog" aria-expanded="false" aria-label="Custom date range">
                    <i class="fas fa-calendar-days" aria-hidden="true"></i>
                    <span id="historyCalendarLabel">Select date range</span>
                </button>
                <div class="history-calendar-menu" hidden>
                    <button type="button" class="history-cal-nav is-prev" data-cal="prev" aria-label="Previous month">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="history-cal-nav is-next" data-cal="next" aria-label="Next month">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                    <div class="history-calendar-months" id="historyCalendarMonths"></div>
                </div>
            </div>
            <input type="hidden" id="historyFrom">
            <input type="hidden" id="historyTo">
            <button type="button" id="historyApply" class="history-apply">Apply</button>
        </div>
        </div>
        <p id="historyPeriod" class="history-period" hidden></p>

        <div class="history-table-wrap">
            <table class="inventory-table history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Change</th>
                        <th>Stock after</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <tr><td colspan="4" style="text-align:center; color:#999;">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="history-pagination" id="historyPagination" hidden>
            <div class="history-pagination-info" id="historyPageInfo"></div>
            <div class="history-pagination-controls">
                <button type="button" class="history-page-btn" id="historyPrev">Prev</button>
                <span class="history-page-label" id="historyPageLabel"></span>
                <button type="button" class="history-page-btn" id="historyNext">Next</button>
            </div>
        </div>
        </div>
    </div>
</div>

<script>
const historyUrlTemplate = @json(route(\App\Models\User::routeRole() . '.inventory.history', ['id' => 999999]));
let historyItemId = null;
let historyPage = 1;

function escapeHistoryHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function currentHistoryRange() {
    return document.getElementById('historyRange').dataset.value || 'all';
}

function closeHistoryDropdown() {
    document.querySelectorAll('.history-dropdown-menu, .history-calendar-menu').forEach(function (menu) {
        menu.hidden = true;
    });
    document.querySelectorAll('.history-dropdown-toggle, .history-calendar-toggle').forEach(function (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
    });
}

const historyWeekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
let calendarCursor = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
let rangeStart = null;
let rangeEnd = null;

function sameDay(left, right) {
    return left && right && left.getTime() === right.getTime();
}

function isoDate(value) {
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return value.getFullYear() + '-' + month + '-' + day;
}

function displayDate(value) {
    const day = String(value.getDate()).padStart(2, '0');
    const month = String(value.getMonth() + 1).padStart(2, '0');
    return day + '/' + month + '/' + value.getFullYear();
}

function updateCalendarLabel() {
    const label = document.getElementById('historyCalendarLabel');
    document.getElementById('historyFrom').value = rangeStart ? isoDate(rangeStart) : '';
    document.getElementById('historyTo').value = rangeEnd ? isoDate(rangeEnd) : '';
    if (rangeStart && rangeEnd) {
        label.textContent = displayDate(rangeStart) + ' - ' + displayDate(rangeEnd);
    } else if (rangeStart) {
        label.textContent = displayDate(rangeStart);
    } else {
        label.textContent = 'Select date range';
    }
}

function renderMonth(firstOfMonth) {
    const month = document.createElement('div');
    month.className = 'history-cal-month';
    const title = document.createElement('div');
    title.className = 'history-cal-title';
    title.textContent = firstOfMonth.toLocaleString('en-US', { month: 'short', year: 'numeric' });
    month.appendChild(title);

    const week = document.createElement('div');
    week.className = 'history-cal-week';
    historyWeekdays.forEach(function (name) {
        const cell = document.createElement('span');
        cell.textContent = name;
        week.appendChild(cell);
    });
    month.appendChild(week);

    const grid = document.createElement('div');
    grid.className = 'history-cal-grid';
    const start = new Date(firstOfMonth);
    const mondayOffset = (start.getDay() + 6) % 7;
    start.setDate(1 - mondayOffset);

    for (let index = 0; index < 42; index += 1) {
        const day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'history-cal-day';
        button.textContent = String(day.getDate());
        button.dataset.date = isoDate(day);
        if (day.getMonth() !== firstOfMonth.getMonth()) {
            button.classList.add('is-outside');
        }
        if (sameDay(day, rangeStart)) {
            button.classList.add('is-start');
        }
        if (sameDay(day, rangeEnd)) {
            button.classList.add('is-end');
        }
        if (rangeStart && rangeEnd && day > rangeStart && day < rangeEnd) {
            button.classList.add('is-between');
        }
        grid.appendChild(button);
    }
    month.appendChild(grid);
    return month;
}

function renderCalendar() {
    const host = document.getElementById('historyCalendarMonths');
    const left = new Date(calendarCursor.getFullYear(), calendarCursor.getMonth(), 1);
    const right = new Date(calendarCursor.getFullYear(), calendarCursor.getMonth() + 1, 1);
    host.replaceChildren(renderMonth(left), renderMonth(right));
    updateCalendarLabel();
}

function resetCalendar() {
    const today = new Date();
    calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1);
    rangeStart = null;
    rangeEnd = null;
    renderCalendar();
}

function chooseCalendarDay(value) {
    const parts = value.split('-').map(Number);
    const picked = new Date(parts[0], parts[1] - 1, parts[2]);
    if (!rangeStart || rangeEnd) {
        rangeStart = picked;
        rangeEnd = null;
    } else if (picked < rangeStart) {
        rangeEnd = rangeStart;
        rangeStart = picked;
    } else {
        rangeEnd = picked;
    }
    renderCalendar();
}

resetCalendar();

document.querySelector('.history-calendar-toggle').addEventListener('click', function () {
    const menu = document.querySelector('.history-calendar-menu');
    const willOpen = menu.hidden;
    closeHistoryDropdown();
    menu.hidden = !willOpen;
    this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
});

document.querySelector('.history-calendar-menu').addEventListener('click', function (event) {
    event.stopPropagation();
    const nav = event.target.closest('[data-cal]');
    if (nav) {
        calendarCursor = new Date(
            calendarCursor.getFullYear(),
            calendarCursor.getMonth() + (nav.dataset.cal === 'next' ? 1 : -1),
            1
        );
        renderCalendar();
        return;
    }
    const day = event.target.closest('.history-cal-day');
    if (day) {
        chooseCalendarDay(day.dataset.date);
    }
});

function setHistoryRange(range) {
    const root = document.getElementById('historyRange');
    const match = root.querySelector('[data-range="' + range + '"]');
    root.dataset.value = range;
    root.querySelector('.history-dropdown-value').textContent = match ? match.textContent : 'All dates';
    root.querySelectorAll('[data-range]').forEach(function (option) {
        option.setAttribute('aria-selected', option.dataset.range === range ? 'true' : 'false');
    });
    document.getElementById('historyCustomRange').hidden = range !== 'custom';
    closeHistoryDropdown();
}

function historyQuery() {
    const range = currentHistoryRange();
    const params = new URLSearchParams();

    if (range && range !== 'all') {
        params.set('range', range);
    }

    if (range === 'custom') {
        const from = document.getElementById('historyFrom').value;
        const to = document.getElementById('historyTo').value;
        if (from) params.set('from', from);
        if (to) params.set('to', to);
    }

    if (historyPage > 1) {
        params.set('page', String(historyPage));
    }

    return params.toString();
}

function renderHistoryPagination(pagination) {
    const bar = document.getElementById('historyPagination');
    if (!pagination || !pagination.total) {
        bar.hidden = true;
        return;
    }

    bar.hidden = false;
    document.getElementById('historyPageInfo').textContent =
        'Showing ' + pagination.from + '–' + pagination.to + ' of ' + pagination.total;
    document.getElementById('historyPageLabel').textContent =
        'Page ' + pagination.current + ' of ' + pagination.last;

    const prev = document.getElementById('historyPrev');
    const next = document.getElementById('historyNext');
    prev.disabled = pagination.current <= 1;
    next.disabled = pagination.current >= pagination.last;
    prev.classList.toggle('disabled', prev.disabled);
    next.classList.toggle('disabled', next.disabled);
}

function loadItemHistory(page) {
    const body = document.getElementById('historyTableBody');
    const period = document.getElementById('historyPeriod');

    if (!historyItemId) {
        return;
    }

    historyPage = page || 1;
    body.innerHTML = '<tr><td colspan="4" class="history-empty">Loading…</td></tr>';
    period.hidden = true;
    period.textContent = '';

    const query = historyQuery();
    const historyUrl = historyUrlTemplate.replace('999999', historyItemId) + (query ? ('?' + query) : '');

    fetch(historyUrl, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(res => {
            if (!res.ok) throw new Error('Could not load history');
            return res.json();
        })
        .then(data => {
            document.getElementById('historyItemName').textContent = data.item + ' (' + data.unit + ')';
            if (data.period) {
                period.textContent = data.period;
                period.hidden = false;
            }
            if (!data.history.length) {
                body.innerHTML = '<tr><td colspan="4" class="history-empty">No history for this period.</td></tr>';
                renderHistoryPagination(data.pagination);
                return;
            }
            body.innerHTML = data.history.map(row => {
                const change = Number(row.change);
                const sign = change > 0 ? '+' : '';
                const changeClass = change > 0 ? 'history-up' : (change < 0 ? 'history-down' : '');
                return '<tr>' +
                    '<td>' + escapeHistoryHtml(row.when) + '</td>' +
                    '<td>' + escapeHistoryHtml(row.action) + (row.note ? '<div class="history-note">' + escapeHistoryHtml(row.note) + '</div>' : '') + '</td>' +
                    '<td class="' + changeClass + '">' + sign + Number(change).toLocaleString() + '</td>' +
                    '<td>' + Number(row.after).toLocaleString() + '</td>' +
                    '</tr>';
            }).join('');
            renderHistoryPagination(data.pagination);
        })
        .catch(() => {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#c0392b;">Could not load history.</td></tr>';
            document.getElementById('historyPagination').hidden = true;
        });
}

function openHistoryModal(itemId) {
    historyItemId = itemId;
    setHistoryRange('all');
    document.getElementById('historyFrom').value = '';
    document.getElementById('historyTo').value = '';
    resetCalendar();
    document.getElementById('historyItemName').textContent = '…';
    document.getElementById('historyModal').classList.add('active');
    loadItemHistory(1);
}

function closeHistoryModal() {
    closeHistoryDropdown();
    document.getElementById('historyModal').classList.remove('active');
}

document.querySelector('#historyRange .history-dropdown-toggle').addEventListener('click', function () {
    const menu = document.querySelector('#historyRange .history-dropdown-menu');
    const willOpen = menu.hidden;
    closeHistoryDropdown();
    menu.hidden = !willOpen;
    this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
});

document.querySelector('#historyRange .history-dropdown-menu').addEventListener('click', function (event) {
    const option = event.target.closest('[data-range]');
    if (!option || option.dataset.range === currentHistoryRange()) {
        closeHistoryDropdown();
        return;
    }
    setHistoryRange(option.dataset.range);
    if (option.dataset.range !== 'custom') {
        loadItemHistory(1);
    }
});

document.addEventListener('click', function (event) {
    const path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target];
    const inside = path.some(function (node) {
        return node instanceof Element && (node.closest('#historyRange') || node.closest('#historyCalendar'));
    });
    if (!inside) {
        closeHistoryDropdown();
    }
});

document.getElementById('historyApply').addEventListener('click', function () {
    loadItemHistory(1);
});

document.getElementById('historyPrev').addEventListener('click', function () {
    if (!this.disabled) {
        loadItemHistory(historyPage - 1);
    }
});

document.getElementById('historyNext').addEventListener('click', function () {
    if (!this.disabled) {
        loadItemHistory(historyPage + 1);
    }
});
</script>

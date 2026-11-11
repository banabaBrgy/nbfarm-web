(function () {
    const form = document.querySelector('[data-egg-summary-form]');
    const root = document.getElementById('eggProductionSummary');

    if (!form || !root || form.dataset.bound === '1') {
        return;
    }

    form.dataset.bound = '1';

    const body = root.querySelector('[data-egg-summary-body]');
    const status = root.querySelector('[data-egg-summary-status]');
    let requestId = 0;

    function cell(text, strong) {
        const td = document.createElement('td');

        if (strong) {
            const el = document.createElement('strong');
            el.textContent = text;
            td.appendChild(el);
        } else {
            td.textContent = text;
        }

        return td;
    }

    function render(data) {
        body.replaceChildren();

        (data.rows || []).forEach(function (row) {
            const tr = document.createElement('tr');
            const percent = Number(row.percent);
            const safePercent = Number.isFinite(percent) ? percent : 0;
            const barCell = document.createElement('td');
            const bar = document.createElement('div');
            const fill = document.createElement('div');
            const label = document.createElement('span');

            bar.className = 'mini-bar';
            fill.className = 'mini-bar-fill';
            fill.style.width = safePercent + '%';
            label.className = 'mini-bar-label';
            label.textContent = safePercent + '%';
            bar.appendChild(fill);
            barCell.append(bar, label);
            tr.append(cell(row.label || '', false), cell(String(row.value ?? '0'), true), barCell);
            body.appendChild(tr);
        });

        const total = document.createElement('tr');
        total.className = 'table-total-row';
        total.append(
            cell('TOTAL', true),
            cell(String(data.grandTotal ?? '0'), true),
            cell('100%', false)
        );
        body.appendChild(total);

        const year = form.querySelector('[name="year"]');
        const month = form.querySelector('[name="month"]');

        if (year && data.year != null) {
            year.value = String(data.year);
        }

        if (month && data.month != null) {
            month.value = String(data.month);
        }

        const url = new URL(window.location.href);
        url.searchParams.set('year', data.year);
        url.searchParams.set('month', data.month);
        history.replaceState(null, '', url);
    }

    function showError(message) {
        if (!status) {
            return;
        }

        status.hidden = message === '';
        status.textContent = message;
    }

    function load() {
        const id = ++requestId;
        const url = new URL(window.location.href);
        const year = form.querySelector('[name="year"]');
        const month = form.querySelector('[name="month"]');

        if (year) {
            url.searchParams.set('year', year.value);
        }

        if (month) {
            url.searchParams.set('month', month.value);
        }

        root.classList.add('is-loading');
        root.setAttribute('aria-busy', 'true');
        showError('');

        fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not load summary');
                }

                return response.json();
            })
            .then(function (data) {
                if (id !== requestId) {
                    return;
                }

                render(data);
            })
            .catch(function () {
                if (id !== requestId) {
                    return;
                }

                showError('Could not update the summary. Try again.');
            })
            .finally(function () {
                if (id !== requestId) {
                    return;
                }

                root.classList.remove('is-loading');
                root.removeAttribute('aria-busy');
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        load();
    });

    form.addEventListener('change', function (event) {
        if (event.target instanceof HTMLSelectElement) {
            load();
        }
    });
})();

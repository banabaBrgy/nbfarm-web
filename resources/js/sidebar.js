(function () {
  const config = window.NB_REVENUE || {};
  let chartData = config.chartData || [];

  /* ══════════════ HELPERS ══════════════ */

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  function formatMoney(value) {
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  function formatDate(dateStr) {
    // Accepts either "YYYY-MM-DD" or a full ISO timestamp from Eloquent's date cast.
    const d = new Date(dateStr.length > 10 ? dateStr : dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  /* ══════════════ CHART ══════════════ */

  function buildChart() {
    const wrap = document.getElementById('chartWrap');
    if (!wrap || chartData.length === 0) return;

    const max = Math.max(...chartData.map(d => d.amount), 1);
    const niceMax = Math.max(Math.ceil(max / 2000) * 2000, 2000);

    const width = 560;
    const height = 220;
    const padLeft = 42;
    const padRight = 10;
    const padTop = 26;
    const padBottom = 26;
    const plotW = width - padLeft - padRight;
    const plotH = height - padTop - padBottom;

    const gridCount = 4;
    let gridlines = '';
    let axisLabels = '';
    for (let i = 0; i <= gridCount; i++) {
      const y = padTop + (plotH / gridCount) * i;
      const val = Math.round(niceMax - (niceMax / gridCount) * i);
      gridlines += `<line class="chart-gridline" x1="${padLeft}" y1="${y}" x2="${width - padRight}" y2="${y}" />`;
      axisLabels += `<text class="chart-axis-label" x="${padLeft - 8}" y="${y + 4}">${(val / 1000).toFixed(0)}k</text>`;
    }

    const barGap = 14;
    const barWidth = (plotW - barGap * (chartData.length - 1)) / chartData.length;

    let bars = '';
    chartData.forEach((d, i) => {
      const barH = Math.max((d.amount / niceMax) * plotH, d.amount > 0 ? 2 : 0);
      const x = padLeft + i * (barWidth + barGap);
      const y = padTop + plotH - barH;
      const radius = Math.min(6, barWidth / 2);

      bars += `
        <g class="chart-bar-group">
          <path class="chart-bar" d="
            M ${x} ${y + radius}
            Q ${x} ${y} ${x + radius} ${y}
            L ${x + barWidth - radius} ${y}
            Q ${x + barWidth} ${y} ${x + barWidth} ${y + radius}
            L ${x + barWidth} ${padTop + plotH}
            L ${x} ${padTop + plotH}
            Z
          " />
          <text class="chart-value-label" x="${x + barWidth / 2}" y="${y - 8}">₱${d.amount.toLocaleString()}</text>
          <text class="chart-bar-label" x="${x + barWidth / 2}" y="${height - 6}">${d.day}</text>
        </g>`;
    });

    wrap.innerHTML = `
      <svg class="chart-svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet">
        <defs>
          <linearGradient id="barGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#4a9b68" />
            <stop offset="100%" stop-color="#2e6b47" />
          </linearGradient>
        </defs>
        ${gridlines}
        ${axisLabels}
        ${bars}
      </svg>`;
  }

  /* ══════════════ STATS ══════════════ */

  function updateStats(stats) {
    if (!stats) return;
    document.getElementById('stat-today').textContent = formatMoney(stats.today);
    document.getElementById('stat-week').textContent = formatMoney(stats.week);
    document.getElementById('stat-month').textContent = formatMoney(stats.month);
    document.getElementById('stat-pending').textContent = formatMoney(stats.pending);
  }

  /* ══════════════ TRANSACTIONS TABLE ══════════════ */

  function renderTransactionsTable(transactions) {
    const body = document.getElementById('transactionsBody');
    if (!body) return;

    if (!transactions || transactions.length === 0) {
      body.innerHTML = `<tr><td colspan="5" class="transactions-empty">No transactions yet.</td></tr>`;
      return;
    }

    body.innerHTML = transactions.map(t => `
      <tr>
        <td class="tx-customer">${escapeHtml(t.customer)}</td>
        <td>${t.quantity}</td>
        <td>${formatMoney(t.price_per_tray)}</td>
        <td>${formatDate(t.transaction_date)}</td>
        <td><span class="status-pill status-${t.status.toLowerCase()}">${t.status}</span></td>
      </tr>`).join('');
  }

  /* ══════════════ FORM ══════════════ */

  function calcTotal() {
    const qty = parseFloat(document.getElementById('f-qty').value) || 0;
    const price = parseFloat(document.getElementById('f-price').value) || 0;
    document.getElementById('totalDisplay').textContent = formatMoney(qty * price);
  }

  function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('error', isError);
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2800);
  }

  function showFormErrors(errors) {
    const box = document.getElementById('formErrors');
    if (!box) return;
    const messages = Object.values(errors).flat();
    box.innerHTML = messages.join('<br>');
    box.style.display = messages.length ? 'block' : 'none';
  }

  async function submitTransaction() {
    const btn = document.getElementById('btnSubmit');
    const customer = document.getElementById('f-customer').value.trim();
    const transaction_date = document.getElementById('f-date').value;
    const quantity = document.getElementById('f-qty').value;
    const price_per_tray = document.getElementById('f-price').value;
    const status = document.getElementById('f-status').value;

    showFormErrors({});

    if (!customer || !transaction_date || !quantity || !price_per_tray) {
      showFormErrors({ form: ['Please fill in all fields before saving.'] });
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
      const response = await fetch(config.routes.store, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': config.csrfToken,
        },
        body: JSON.stringify({ customer, transaction_date, quantity, price_per_tray, status }),
      });

      const data = await response.json();

      if (!response.ok) {
        showFormErrors(data.errors || { form: [data.message || 'Something went wrong.'] });
        showToast('Could not save transaction.', true);
        return;
      }

      // Reset form
      document.getElementById('f-customer').value = '';
      document.getElementById('f-qty').value = '';
      document.getElementById('f-price').value = '';
      document.getElementById('f-status').value = 'Paid';
      document.getElementById('totalDisplay').textContent = '₱0.00';

      // Refresh everything from what the server actually has in the DB
      updateStats(data.stats);
      chartData = data.chartData;
      buildChart();
      renderTransactionsTable(data.transactions);

      showToast('✓ ' + data.message);
    } catch (err) {
      showToast('Network error. Please try again.', true);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Save Transaction';
    }
  }

  /* ══════════════ SEARCH ══════════════ */

  let searchDebounce = null;

  function renderSearchResults(results) {
    const box = document.getElementById('searchResults');
    if (!box) return;

    if (results.length === 0) {
      box.innerHTML = '<div class="search-empty">No transactions found.</div>';
    } else {
      box.innerHTML = results.map(r => `
        <div class="search-result-row">
          <div>
            <div class="search-result-name">${escapeHtml(r.customer)}</div>
            <div class="search-result-meta">${formatDate(r.transaction_date)} · ${r.quantity} trays · ${r.status}</div>
          </div>
          <div class="search-result-amount">${formatMoney(r.total_amount)}</div>
        </div>`).join('');
    }
    box.classList.add('show');
  }

  async function performSearch(term) {
    try {
      const url = new URL(config.routes.search, window.location.origin);
      url.searchParams.set('term', term);
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      renderSearchResults(data.results || []);
    } catch (err) {
      // Fail silently — search is a non-critical enhancement
    }
  }

  function initSearch() {
    const input = document.getElementById('f-search');
    const box = document.getElementById('searchResults');
    if (!input || !box) return;

    input.addEventListener('input', () => {
      const term = input.value.trim();
      clearTimeout(searchDebounce);

      if (term.length === 0) {
        box.classList.remove('show');
        return;
      }

      searchDebounce = setTimeout(() => performSearch(term), 300);
    });

    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !box.contains(e.target)) {
        box.classList.remove('show');
      }
    });
  }

  /* ══════════════ INIT ══════════════ */

  document.addEventListener('DOMContentLoaded', () => {
    buildChart();
    initSearch();

    const dateInput = document.getElementById('f-date');
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    const qty = document.getElementById('f-qty');
    const price = document.getElementById('f-price');
    if (qty) qty.addEventListener('input', calcTotal);
    if (price) price.addEventListener('input', calcTotal);

    const btn = document.getElementById('btnSubmit');
    if (btn) btn.addEventListener('click', submitTransaction);
  });
})();

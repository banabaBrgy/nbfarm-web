(function () {
  const config = window.NB_REVENUE || {};
  let chartData = config.chartData || [];

  /* ══════════════ HELPERS ══════════════ */

  function formatMoney(value) {
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2 });
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
            <stop offset="100%" stop-color="#34853F" />
          </linearGradient>
        </defs>
        ${gridlines}
        ${axisLabels}
        ${bars}
      </svg>`;
  }

  /* ══════════════ FORM ══════════════ */

  function calcTotal() {
    const qtyEl = document.getElementById('f-qty');
    const priceEl = document.getElementById('f-price');
    const totalEl = document.getElementById('totalDisplay');
    if (!qtyEl || !priceEl || !totalEl) return;

    const qty = parseFloat(qtyEl.value) || 0;
    const price = parseFloat(priceEl.value) || 0;
    totalEl.textContent = formatMoney(qty * price);
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

    const customerEl = document.getElementById('f-customer');
    const dateEl = document.getElementById('f-date');
    const qtyEl = document.getElementById('f-qty');
    const priceEl = document.getElementById('f-price');
    const statusEl = document.getElementById('f-status');

    // Guard against missing fields instead of throwing on null.value
    if (!customerEl || !dateEl || !priceEl || !statusEl) {
      showFormErrors({ form: ['Form is missing required fields. Check the modal markup.'] });
      return;
    }

    const customer = customerEl.value.trim();
    const transaction_date = dateEl.value;
    const quantity = qtyEl ? qtyEl.value : null;
    const price = priceEl.value;
    const status = statusEl.value;

    showFormErrors({});

    if (!customer || !transaction_date || !price) {
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
        body: JSON.stringify({ customer, transaction_date, quantity, price, status }),
      });

      const data = await response.json();

      if (!response.ok) {
        showFormErrors(data.errors || { form: [data.message || 'Something went wrong.'] });
        showToast('Could not save transaction.', true);
        btn.disabled = false;
        btn.textContent = 'Save Transaction';
        return;
      }

      // Keep the toast message across the reload so the user still sees it.
      sessionStorage.setItem('nb_toast', data.message);

      // Reload the current page (keeps whatever search/sort/page is in the URL)
      // so stats, chart, and the transactions table all reflect the latest DB state.
      window.location.reload();
    } catch (err) {
      console.error('submitTransaction error:', err);
      showToast('Network error. Please try again.', true);
      btn.disabled = false;
      btn.textContent = 'Save Transaction';
    }
  }

  /* ══════════════ INIT ══════════════ */

  document.addEventListener('DOMContentLoaded', () => {
    buildChart();

    // Show a toast left over from a save that just triggered a reload.
    const pendingToast = sessionStorage.getItem('nb_toast');
    if (pendingToast) {
      showToast('✓ ' + pendingToast);
      sessionStorage.removeItem('nb_toast');
    }

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
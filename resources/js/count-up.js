document.addEventListener('DOMContentLoaded', () => {
    const nodes = document.querySelectorAll([
        '.stat-info > h2',
        '.dashboard-card-small h2',
        '.stat-card > .stat-value',
        '.dsp-stat-value',
        '.rpt-stat-value',
        '.summary-card h3',
        '.production-page .card-body h2',
    ].join(', '));

    if (nodes.length === 0) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const duration = 1100;

    const parseMetric = (text) => {
        const raw = (text || '').trim().replace(/\u00a0/g, ' ');
        const match = raw.match(/^([^0-9-]*)(-?\d[\d,]*)(?:\.(\d+))?(.*)$/);
        if (!match) {
            return null;
        }

        const decimals = match[3] ? match[3].length : 0;
        const value = Number(`${match[2].replace(/,/g, '')}.${match[3] || '0'}`);
        if (Number.isNaN(value)) {
            return null;
        }

        return {
            prefix: match[1],
            suffix: match[4],
            decimals,
            value,
        };
    };

    const formatMetric = (metric, current) => {
        const abs = Math.abs(current);
        const formatted = abs.toLocaleString('en-US', {
            minimumFractionDigits: metric.decimals,
            maximumFractionDigits: metric.decimals,
        });
        const sign = current < 0 ? '-' : '';
        return `${metric.prefix}${sign}${formatted}${metric.suffix}`;
    };

    const easeOutCubic = (t) => 1 - ((1 - t) ** 3);

    const animate = (el, metric, delay) => {
        const start = performance.now() + delay;
        const tick = (now) => {
            if (now < start) {
                requestAnimationFrame(tick);
                return;
            }
            const progress = Math.min(1, (now - start) / duration);
            const current = metric.value * easeOutCubic(progress);
            el.textContent = formatMetric(metric, progress === 1 ? metric.value : current);
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };
        requestAnimationFrame(tick);
    };

    const play = (el, index) => {
        if (el.dataset.countUpDone === '1') {
            return;
        }

        const metric = parseMetric(el.textContent);
        if (!metric) {
            return;
        }

        el.dataset.countUpDone = '1';
        if (reduceMotion || metric.value === 0) {
            el.textContent = formatMetric(metric, metric.value);
            return;
        }

        el.textContent = formatMetric(metric, 0);
        animate(el, metric, Math.min(index * 80, 320));
    };

    if (!('IntersectionObserver' in window)) {
        nodes.forEach((el, index) => play(el, index));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            const el = entry.target;
            const index = Number(el.dataset.countUpIndex || 0);
            play(el, index);
            observer.unobserve(el);
        });
    }, { threshold: 0.35 });

    nodes.forEach((el, index) => {
        el.dataset.countUpIndex = String(index);
        observer.observe(el);
    });
});

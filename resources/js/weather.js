const REFRESH_MS = 5 * 60 * 1000;
const ICON_MODIFIERS = ['sun', 'cloud', 'rain', 'storm', 'fog'];

function relativeUpdated(iso) {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) {
        return '';
    }

    const minutes = Math.max(0, Math.round((Date.now() - then) / 60000));

    if (minutes < 1) {
        return 'Updated just now';
    }

    if (minutes === 1) {
        return 'Updated 1 min ago';
    }

    return `Updated ${minutes} min ago`;
}

function renderForecast(days) {
    return (days || []).map((day) => `
        <div class="farm-weather__day${day.day === 'Today' ? ' is-today' : ''}">
            <span class="farm-weather__day-name">${day.day}</span>
            <span class="farm-weather__day-num">${day.day_num ?? ''}</span>
            <span class="farm-weather__day-icon" aria-hidden="true">
                <i class="fas ${day.icon}"></i>
            </span>
            <span class="farm-weather__day-temps">
                <strong>${day.max}°</strong>
                <span>${day.min}°</span>
            </span>
        </div>
    `).join('');
}

function weatherScope(root) {
    const overlay = root._weatherOverlay
        || root.querySelector('.farm-weather__overlay')
        || document.querySelector('.farm-weather__overlay');

    return {
        root,
        overlay,
        queryAll(selector) {
            const fromRoot = Array.from(root.querySelectorAll(selector));
            const fromOverlay = overlay && overlay.parentElement !== root
                ? Array.from(overlay.querySelectorAll(selector))
                : [];

            return [...fromRoot, ...fromOverlay];
        },
        query(selector) {
            return root.querySelector(selector)
                || (overlay && overlay.parentElement !== root ? overlay.querySelector(selector) : null);
        },
    };
}

function setTextAll(scope, selector, value) {
    scope.queryAll(selector).forEach((el) => {
        el.textContent = value;
    });
}

function applyWeather(root, data) {
    const scope = weatherScope(root);
    root.classList.remove('farm-weather--empty');
    root.dataset.updatedAt = data.updated_at || '';
    const modifier = data.modifier || 'cloud';

    scope.queryAll('[data-weather-icon-wrap]').forEach((wrap) => {
        ICON_MODIFIERS.forEach((name) => wrap.classList.remove(`farm-weather__icon--${name}`));
        wrap.classList.add(`farm-weather__icon--${modifier}`);
    });

    scope.queryAll('[data-weather-icon]').forEach((icon) => {
        icon.className = `fas ${data.icon || 'fa-cloud-sun'}`;
    });

    setTextAll(scope, '[data-weather-temp]', data.temperature ?? '--');
    setTextAll(scope, '[data-weather-condition]', data.condition || 'Weather unavailable');
    setTextAll(scope, '[data-weather-location]', data.location || '');
    setTextAll(scope, '[data-weather-humidity]', data.humidity ?? '--');
    setTextAll(scope, '[data-weather-date]', data.date_label || '');
    setTextAll(scope, '[data-weather-updated]', relativeUpdated(data.updated_at));

    const forecast = scope.query('[data-weather-forecast]');
    if (forecast) {
        forecast.innerHTML = renderForecast(data.forecast);
    }
}

async function refreshWeather(root) {
    const url = root.dataset.url;
    if (!url || !window.axios) {
        return;
    }

    try {
        const response = await window.axios.get(url);
        if (response.data && response.data.ok) {
            applyWeather(root, response.data);
        }
    } catch (error) {
        // Keep the last successful reading on screen.
    }
}

function ensureWeatherOverlayOnBody(root) {
    const overlay = root.querySelector('.farm-weather__overlay') || root._weatherOverlay;
    if (!overlay) {
        return null;
    }

    root._weatherOverlay = overlay;
    if (overlay.parentElement !== document.body) {
        document.body.appendChild(overlay);
    }

    return overlay;
}

function openWeather(root) {
    root.classList.add('is-open');
    document.body.classList.add('farm-weather-open');
    const toggle = root.querySelector('[data-weather-toggle]');
    const overlay = ensureWeatherOverlayOnBody(root);
    const dialog = document.getElementById('farm-weather-dialog');
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'true');
    }
    overlay?.setAttribute('aria-hidden', 'false');
    dialog?.focus();
}

function closeWeather(root) {
    if (!root.classList.contains('is-open')) {
        return;
    }

    root.classList.remove('is-open');
    document.body.classList.remove('farm-weather-open');
    const toggle = root.querySelector('[data-weather-toggle]');
    const overlay = root._weatherOverlay || document.querySelector('.farm-weather__overlay');
    overlay?.setAttribute('aria-hidden', 'true');
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    }
}

function bindWeatherDialog(root) {
    const toggle = root.querySelector('[data-weather-toggle]');
    const overlay = ensureWeatherOverlayOnBody(root);

    toggle?.addEventListener('click', () => {
        if (root.classList.contains('is-open')) {
            closeWeather(root);
            return;
        }
        openWeather(root);
    });

    overlay?.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeWeather(root);
        }
    });

    weatherScope(root).queryAll('[data-weather-close]').forEach((el) => {
        el.addEventListener('click', () => closeWeather(root));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeWeather(root);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('farm-weather');
    if (!root) {
        return;
    }

    bindWeatherDialog(root);

    const scope = weatherScope(root);
    const updated = scope.query('[data-weather-updated]');
    if (updated) {
        updated.textContent = relativeUpdated(root.dataset.updatedAt) || updated.textContent;
    }

    setInterval(() => {
        const label = weatherScope(root).query('[data-weather-updated]');
        if (label) {
            label.textContent = relativeUpdated(root.dataset.updatedAt) || label.textContent;
        }
    }, 30000);

    setInterval(() => refreshWeather(root), REFRESH_MS);
});

const REFRESH_MS = 5 * 60 * 1000;

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
            <i class="fas ${day.icon}" aria-hidden="true"></i>
            <span class="farm-weather__day-temps">
                <strong>${day.max}°</strong>
                <span>${day.min}°</span>
            </span>
        </div>
    `).join('');
}

function applyWeather(root, data) {
    root.classList.remove('farm-weather--empty');
    root.dataset.updatedAt = data.updated_at || '';

    const iconWrap = root.querySelector('.farm-weather__icon');
    const icon = root.querySelector('[data-weather-icon]');
    const temp = root.querySelector('[data-weather-temp]');
    const condition = root.querySelector('[data-weather-condition]');
    const location = root.querySelector('[data-weather-location]');
    const humidity = root.querySelector('[data-weather-humidity]');
    const date = root.querySelector('[data-weather-date]');
    const forecast = root.querySelector('[data-weather-forecast]');
    const updated = root.querySelector('[data-weather-updated]');

    if (iconWrap) {
        iconWrap.className = `farm-weather__icon farm-weather__icon--${data.modifier || 'cloud'}`;
    }

    if (icon) {
        icon.className = `fas ${data.icon || 'fa-cloud-sun'}`;
    }

    if (temp) {
        temp.textContent = data.temperature ?? '--';
    }

    if (condition) {
        condition.textContent = data.condition || 'Weather unavailable';
    }

    if (location) {
        location.textContent = data.location || '';
    }

    if (humidity) {
        humidity.textContent = data.humidity ?? '--';
    }

    if (date) {
        date.textContent = data.date_label || '';
    }

    if (forecast) {
        forecast.innerHTML = renderForecast(data.forecast);
    }

    if (updated) {
        updated.textContent = relativeUpdated(data.updated_at);
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

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('farm-weather');
    if (!root) {
        return;
    }

    const updated = root.querySelector('[data-weather-updated]');
    if (updated) {
        updated.textContent = relativeUpdated(root.dataset.updatedAt) || updated.textContent;
    }

    setInterval(() => {
        if (updated) {
            updated.textContent = relativeUpdated(root.dataset.updatedAt) || updated.textContent;
        }
    }, 30000);

    setInterval(() => refreshWeather(root), REFRESH_MS);
});

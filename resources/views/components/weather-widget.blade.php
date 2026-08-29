@php
    $weather = app(\App\Services\WeatherService::class)->current();
@endphp

<div
    id="farm-weather"
    class="farm-weather{{ $weather ? '' : ' farm-weather--empty' }}"
    data-url="{{ route('weather.current') }}"
    @if($weather)
        data-updated-at="{{ $weather['updated_at'] }}"
    @endif
>
    <div class="farm-weather__inner">
        <header class="farm-weather__header">
            <div class="farm-weather__header-title">
                <i class="fas fa-cloud-sun" aria-hidden="true"></i>
                <span>Farm Weather</span>
            </div>
            <span class="farm-weather__location-pill" data-weather-location>
                {{ $weather['location'] ?? config('services.weather.location') }}
            </span>
        </header>

        <div class="farm-weather__body">
            <div class="farm-weather__now">
                <div class="farm-weather__icon farm-weather__icon--{{ $weather['modifier'] ?? 'cloud' }}" aria-hidden="true">
                    <i class="fas {{ $weather['icon'] ?? 'fa-cloud-sun' }}" data-weather-icon></i>
                </div>
                <div class="farm-weather__now-copy">
                    <div class="farm-weather__temp-row">
                        <span class="farm-weather__temp" data-weather-temp>{{ $weather['temperature'] ?? '--' }}</span>
                        <span class="farm-weather__unit">°C</span>
                    </div>
                    <p class="farm-weather__condition" data-weather-condition>{{ $weather['condition'] ?? 'Weather unavailable' }}</p>
                    <p class="farm-weather__date" data-weather-date>{{ $weather['date_label'] ?? '' }}</p>
                    <div class="farm-weather__chips">
                        <span class="farm-weather__chip">
                            <i class="fas fa-droplet" aria-hidden="true"></i>
                            <span data-weather-humidity>{{ $weather['humidity'] ?? '--' }}</span>% humidity
                        </span>
                    </div>
                </div>
            </div>

            <div class="farm-weather__forecast-panel">
                <p class="farm-weather__forecast-title">5-Day Forecast</p>
                <div class="farm-weather__forecast" data-weather-forecast>
                    @if(!empty($weather['forecast']))
                        @foreach($weather['forecast'] as $day)
                            <div class="farm-weather__day{{ $day['day'] === 'Today' ? ' is-today' : '' }}">
                                <span class="farm-weather__day-name">{{ $day['day'] }}</span>
                                <span class="farm-weather__day-num">{{ $day['day_num'] }}</span>
                                <span class="farm-weather__day-icon" aria-hidden="true">
                                    <i class="fas {{ $day['icon'] }}"></i>
                                </span>
                                <span class="farm-weather__day-temps">
                                    <strong>{{ $day['max'] }}°</strong>
                                    <span>{{ $day['min'] }}°</span>
                                </span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <footer class="farm-weather__credit">
            <span class="farm-weather__updated">
                <i class="fas fa-rotate" aria-hidden="true"></i>
                <span data-weather-updated>@if($weather) Updated just now @endif</span>
            </span>
            <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo</a>
        </footer>
    </div>
</div>

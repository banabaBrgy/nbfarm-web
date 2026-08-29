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
                <p class="farm-weather__meta">
                    <span data-weather-location>{{ $weather['location'] ?? config('services.weather.location') }}</span>
                    <span class="farm-weather__dot" aria-hidden="true"></span>
                    <span><span data-weather-humidity>{{ $weather['humidity'] ?? '--' }}</span>% humidity</span>
                </p>
            </div>
        </div>

        <div class="farm-weather__forecast" data-weather-forecast>
            @if(!empty($weather['forecast']))
                @foreach($weather['forecast'] as $day)
                    <div class="farm-weather__day{{ $day['day'] === 'Today' ? ' is-today' : '' }}">
                        <span class="farm-weather__day-name">{{ $day['day'] }}</span>
                        <span class="farm-weather__day-num">{{ $day['day_num'] }}</span>
                        <i class="fas {{ $day['icon'] }}" aria-hidden="true"></i>
                        <span class="farm-weather__day-temps">
                            <strong>{{ $day['max'] }}°</strong>
                            <span>{{ $day['min'] }}°</span>
                        </span>
                    </div>
                @endforeach
            @endif
        </div>

        <p class="farm-weather__credit">
            <span data-weather-updated>@if($weather) Updated just now @endif</span>
            <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo</a>
        </p>
    </div>
</div>

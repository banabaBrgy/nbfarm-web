@php
    $effectivenessAction = $effectivenessAction ?? url()->current();
    $kept = request()->except(['effectiveness_from', 'effectiveness_to', 'effectiveness_open']);
    $monthUrl = $effectivenessAction.'?'.http_build_query(array_merge($kept, ['effectiveness_open' => 1]));
    $openEffectiveness = $errors->has('effectiveness_from')
        || $errors->has('effectiveness_to')
        || request()->boolean('effectiveness_open');
@endphp

<button
    type="button"
    class="effectiveness-open"
    data-bs-toggle="modal"
    data-bs-target="#systemEffectivenessModal"
>
    <i class="fas fa-gauge-high" aria-hidden="true"></i>
    System Effectiveness
</button>

<div
    class="modal fade"
    id="systemEffectivenessModal"
    tabindex="-1"
    aria-labelledby="systemEffectivenessLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="systemEffectivenessLabel">System Effectiveness</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="effectiveness-header">
                    <p>
                        Each figure is counted from saved records for
                        {{ $effectiveness['from_label'] }} to {{ $effectiveness['to_label'] }}.
                        A percentage appears only when that count has records to divide by.
                    </p>

                    <form method="GET" action="{{ $effectivenessAction }}" class="effectiveness-filter">
                        @foreach($kept as $name => $value)
                            @if(! is_array($value))
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="effectiveness_open" value="1">

                        <label for="effectivenessFrom">From
                            <input
                                type="date"
                                id="effectivenessFrom"
                                name="effectiveness_from"
                                value="{{ old('effectiveness_from', $effectiveness['from']) }}"
                                required
                            >
                        </label>

                        <label for="effectivenessTo">To
                            <input
                                type="date"
                                id="effectivenessTo"
                                name="effectiveness_to"
                                value="{{ old('effectiveness_to', $effectiveness['to']) }}"
                                required
                            >
                        </label>

                        <button type="submit">Apply</button>
                        <a class="effectiveness-month" href="{{ $monthUrl }}">This month</a>
                    </form>
                </div>

                @error('effectiveness_from')
                    <p class="effectiveness-error">{{ $message }}</p>
                @enderror
                @error('effectiveness_to')
                    <p class="effectiveness-error">{{ $message }}</p>
                @enderror

                <div class="effectiveness-grid">
                    @foreach($effectiveness['indicators'] as $indicator)
                        <article class="effectiveness-card">
                            <h3>{{ $indicator['title'] }}</h3>
                            <p class="effectiveness-count {{ $indicator['rate_label'] ? '' : 'is-empty' }}">
                                {{ $indicator['headline'] }}
                            </p>
                            @if($indicator['rate_label'])
                                <p class="effectiveness-rate">{{ $indicator['rate_label'] }}</p>
                            @endif
                            <p class="effectiveness-detail">{{ $indicator['detail'] }}</p>
                            <p class="effectiveness-basis">{{ $indicator['basis'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('systemEffectivenessModal');

        if (!modal || !window.bootstrap) {
            return;
        }

        document.body.appendChild(modal);

        @if($openEffectiveness)
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        @endif
    });
</script>

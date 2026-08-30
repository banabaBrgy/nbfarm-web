@php
    $doc = \App\Support\ReportDocument::parse($content ?? '');
@endphp

@if($doc['empty'])
    <div class="rpt-doc-fallback">{{ $content }}</div>
@else
    <article class="rpt-doc">
        <header class="rpt-doc-card rpt-doc-head">
            <div class="rpt-doc-farm">{{ $doc['farm'] }}</div>
            <h3>{{ $doc['title'] }}</h3>
            @if($doc['date'])
                <div class="rpt-doc-row rpt-doc-date">
                    <span>Report Date</span>
                    <strong>{{ $doc['date'] }}</strong>
                </div>
            @endif
        </header>

        @foreach($doc['sections'] as $section)
            @php
                $slug = Str::slug($section['title'] !== '' ? $section['title'] : 'details');
            @endphp
            <section class="rpt-doc-card rpt-doc-section rpt-doc-section-{{ $slug }}">
                @if($section['title'] !== '')
                    <h4>{{ $section['title'] }}</h4>
                @endif

                <div class="rpt-doc-body">
                    @foreach($section['items'] as $item)
                        @if($item['type'] === 'kv')
                            <div class="rpt-doc-row{{ !empty($item['total']) ? ' is-total' : '' }}{{ !empty($item['alert']) ? ' is-alert' : '' }}">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                        @elseif($item['type'] === 'divider')
                            <div class="rpt-doc-divider"></div>
                        @elseif($item['type'] === 'record')
                            @php $parts = $item['parts'] ?? []; @endphp
                            <div class="rpt-doc-record">
                                <div class="rpt-doc-record-main">
                                    <span class="rpt-doc-record-name">{{ $parts[0] ?? '' }}</span>
                                    <div class="rpt-doc-record-side">
                                        @if(isset($parts[1]))
                                            <span class="rpt-doc-status">{{ $parts[1] }}</span>
                                        @endif
                                        @if(count($parts) === 4)
                                            <span class="rpt-doc-qty">{{ $parts[2] }}</span>
                                            <strong>{{ $parts[3] }}</strong>
                                        @elseif(isset($parts[2]))
                                            <strong>{{ $parts[2] }}</strong>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="rpt-doc-note">{{ $item['text'] }}</p>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($doc['footer'] !== '')
            <footer class="rpt-doc-foot">{{ $doc['footer'] }}</footer>
        @endif
    </article>
@endif

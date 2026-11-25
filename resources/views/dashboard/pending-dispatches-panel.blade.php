@php
    $pendingDispatches = $pendingDispatches ?? collect();
    $pendingDispatchCount = $pendingDispatchCount ?? $pendingDispatches->whereIn('status', ['Pending', 'On Transit'])->count();
    $dispatchIndexRoute = $dispatchIndexRoute
        ?? route(\App\Models\User::routeRole().'.dispatch');
@endphp

<div class="panel-card pending-dispatches-card">
    <div class="pending-dispatches-head">
        <h3 class="pending-dispatches-title">
            Pending Dispatches
            <span class="pending-dispatches-count">({{ $pendingDispatchCount }})</span>
        </h3>
        <a href="{{ $dispatchIndexRoute }}" class="pending-dispatches-view-all">
            View All <span aria-hidden="true">→</span>
        </a>
    </div>

    <div class="pending-dispatches-table-wrap">
        <table class="pending-dispatches-table">
            <thead>
                <tr>
                    <th scope="col">Recipient</th>
                    <th scope="col">Address</th>
                    <th scope="col">Items</th>
                    <th scope="col">Price</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingDispatches as $dispatch)
                    @php
                        $statusKey = strtolower(str_replace(' ', '-', $dispatch->status));
                    @endphp
                    <tr>
                        <td class="pending-dispatches-recipient">{{ $dispatch->recipient }}</td>
                        <td class="pending-dispatches-address">{{ $dispatch->address }}</td>
                        <td>
                            <div class="pending-dispatches-items">
                                @foreach($dispatch->items as $item)
                                    @php $chip = strtolower(str_replace(' ', '-', $item->size)); @endphp
                                    <span class="pending-dispatches-item">
                                        <span class="pending-dispatches-qty">{{ number_format((int) ($item->loaded_quantity ?? 0)) }}/{{ number_format($item->quantity) }} trays</span>
                                        <span class="dsp-chip dsp-chip-{{ $chip }}">{{ $item->size }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="pending-dispatches-price">₱{{ number_format((float) $dispatch->price, 2) }}</td>
                        <td>
                            <span class="pending-dispatches-status pending-dispatches-status--{{ $statusKey }}">
                                <span class="pending-dispatches-status-dot" aria-hidden="true"></span>
                                {{ $dispatch->status }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="pending-dispatches-empty">
                            No dispatches to show right now.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

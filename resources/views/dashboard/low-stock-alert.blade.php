@if($lowStockAlert)
    <div class="alert alert-persistent">
        <span aria-hidden="true">⚠️</span>
        <span><strong>Low stock alert</strong> — {{ $lowStockAlert }} item(s).</span>
    </div>
@endif

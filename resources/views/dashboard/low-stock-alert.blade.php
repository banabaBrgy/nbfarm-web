@if($lowStockAlert)
    <div class="alert alert-persistent">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <span><strong>Low stock alert</strong> — {{ $lowStockAlert }} item(s).</span>
    </div>
@endif

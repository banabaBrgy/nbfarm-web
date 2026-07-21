<button
    type="button"
    class="btn-primary-sm"
    data-bs-toggle="modal"
    data-bs-target="#newTransactionModal">

    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
    </svg>

    New Transaction
</button>

<div class="modal fade" id="newTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Record New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div id="formErrors" class="form-errors" style="display:none;"></div>

                <div class="form-group mb-3">
                    <label for="f-customer" class="form-label">Customer</label>
                    <input type="text" id="f-customer" class="form-control" placeholder="e.g. SM Lipa " />
                </div>

                <div class="form-group mb-3">
    <label for="f-qty" class="form-label">Quantity (trays)</label>
    <input type="number" id="f-qty" class="form-control" placeholder="0" min="1" />
</div>

                <div class="form-group mb-3">
                    <label for="f-price" class="form-label">Price (₱)</label>
                    <input type="number" id="f-price" class="form-control" placeholder="0" min="1" step="0.01" />
                </div>

                <div class="form-group mb-3">
                    <label for="f-date" class="form-label">Date</label>
                    <input type="date" id="f-date" class="form-control" />
                </div>

                <div class="form-group mb-3">
                    <label for="f-status" class="form-label">Payment Status</label>
                    <select id="f-status" class="form-select">
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Partial">Partial</option>
                    </select>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="btnSubmit" type="button">Save Transaction</button>
            </div>

        </div>
    </div>
</div>
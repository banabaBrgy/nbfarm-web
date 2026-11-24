@php
    $reviewState = $reviewState ?? ['drafts' => []];
    $reviewDrafts = $reviewState['drafts'] ?? [];
    $reviewSummary = $reviewState['summary'] ?? ['scans' => 0, 'trays' => 0, 'eggs' => 0];
    $reviewNote = $reviewState['note'] ?? \App\Services\QrScanRecorder::NOTE;
    $harvestDate = $reviewState['harvest_date']
        ?? (isset($date) && $date instanceof \Carbon\Carbon
            ? $date->toDateString()
            : now()->toDateString());
    $addQrCode = trim((string) ($reviewState['qr_code'] ?? ''));
    $rolePrefix = \App\Models\User::routeRole();
    $summaryLabel = number_format($reviewSummary['scans']).' stacks · '
        .number_format($reviewSummary['trays']).' trays · '
        .number_format($reviewSummary['eggs']).' eggs';
    $addUrl = route($rolePrefix.'.scan.drafts.add');
    $commitUrl = route($rolePrefix.'.scan.commit');
    $updateTemplate = str_replace(
        '999999',
        '__ID__',
        route($rolePrefix.'.scan.drafts.update', ['draft' => 999999])
    );
    $deleteTemplate = str_replace(
        '999999',
        '__ID__',
        route($rolePrefix.'.scan.drafts.destroy', ['draft' => 999999])
    );
@endphp

<div
    class="modal fade"
    id="productionReviewModal"
    tabindex="-1"
    aria-labelledby="production-review-title"
    aria-hidden="true"
    data-bs-focus="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable production-review-dialog">
        <div class="modal-content production-review-panel" id="productionReviewPanel">
            <div class="modal-header production-review-toolbar">
                <div>
                    <h2 class="modal-title" id="production-review-title">Review list</h2>
                    <p>One size at a time. Batch and size stay locked. Edit trays and eggs only.</p>
                </div>
                <div class="production-review-header-actions">
                    <span class="production-review-badge">Temporary</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body production-review-body">
                <div class="production-review-note" role="note">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <p>{{ $reviewNote }}</p>
                </div>

                <div class="production-review-scroll">
                    <table class="table production-review-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>QR code</th>
                                <th>Batch</th>
                                <th>Size</th>
                                <th>Trays</th>
                                <th>Eggs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reviewDrafts as $row)
                                <tr>
                                    <td>{{ $row['number'] }}</td>
                                    <td>{{ $row['qr_code'] }}</td>
                                    <td>{{ $row['batch'] }}</td>
                                    <td>{{ $row['size'] }}</td>
                                    <td>{{ number_format($row['trays']) }}</td>
                                    <td>{{ number_format($row['eggs']) }}</td>
                                    <td>
                                        <div class="production-review-row-actions">
                                            <button
                                                type="button"
                                                class="production-review-edit"
                                                data-id="{{ $row['id'] }}"
                                                data-batch="{{ $row['batch'] }}"
                                                data-size="{{ $row['size'] }}"
                                                data-trays="{{ $row['trays'] }}"
                                                data-eggs="{{ $row['eggs'] }}"
                                                data-per-tray="{{ $row['eggs_per_tray'] ?? 30 }}">
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="production-review-delete"
                                                data-id="{{ $row['id'] }}">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer production-review-footer">
                <p class="production-review-totals">
                    {{ number_format($reviewSummary['scans']) }} stacks
                    · {{ number_format($reviewSummary['trays']) }} trays
                    · {{ number_format($reviewSummary['eggs']) }} eggs
                </p>
                <div class="production-review-buttons">
                    <button
                        type="button"
                        class="production-review-add"
                        id="productionAddStackBtn"
                        data-qr="{{ $addQrCode }}"
                        @disabled($addQrCode === '')>
                        <i class="fas fa-plus"></i>
                        Add stack
                    </button>
                    <button type="button" class="production-review-save" id="productionSaveBtn">
                        <i class="fas fa-floppy-disk"></i>
                        Confirm &amp; Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const harvestDate = @json($harvestDate);
    const note = @json($reviewNote);
    let summaryLabel = @json($summaryLabel);
    const addUrl = @json($addUrl);
    const commitUrl = @json($commitUrl);
    const updateTemplate = @json($updateTemplate);
    const deleteTemplate = @json($deleteTemplate);

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function jsonRequest(url, method, body) {
        const options = {
            method,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            }
        };

        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        return fetch(url, options).then((response) => response.json());
    }

    function formatCount(value) {
        return Number(value || 0).toLocaleString('en-US');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function reviewModalEl() {
        return document.getElementById('productionReviewModal');
    }

    function reviewModal() {
        const modal = reviewModalEl();

        if (!modal || !window.bootstrap) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modal);
    }

    function holdReviewModal() {
        const modal = reviewModalEl();

        if (!modal || !modal.classList.contains('show')) {
            return;
        }

        document.body.classList.add('modal-open');
    }

    function hideReviewModal() {
        reviewModal()?.hide();
    }

    function swalOnModal(options) {
        return Swal.fire({
            heightAuto: false,
            ...options,
            didOpen: (el) => {
                holdReviewModal();
                options.didOpen?.(el);
            },
            didClose: () => {
                holdReviewModal();
                options.didClose?.();
            }
        });
    }

    function showError(message) {
        return swalOnModal({
            icon: 'error',
            title: 'Review list',
            text: message || 'Something went wrong.'
        });
    }

    function hasDraftRows() {
        return Boolean(document.querySelector('#productionReviewPanel tbody tr'));
    }

    function updateReviewCount(scans) {
        const count = document.getElementById('scanReviewCount');

        if (!count) {
            return;
        }

        count.textContent = formatCount(scans);

        if (Number(scans) > 0) {
            count.hidden = false;
        } else {
            count.hidden = true;
        }
    }

    function applyListing(res) {
        const drafts = Array.isArray(res.drafts) ? res.drafts : [];
        const summary = res.summary || { scans: 0, trays: 0, eggs: 0 };
        const panel = document.getElementById('productionReviewPanel');
        const tbody = panel?.querySelector('tbody');
        const totals = panel?.querySelector('.production-review-totals');
        const addBtn = document.getElementById('productionAddStackBtn');

        summaryLabel = `${formatCount(summary.scans)} stacks · ${formatCount(summary.trays)} trays · ${formatCount(summary.eggs)} eggs`;

        if (addBtn) {
            const qrCode = res.qr_code || '';
            addBtn.setAttribute('data-qr', qrCode);
            addBtn.disabled = qrCode === '';
        }

        updateReviewCount(summary.scans);

        if (!drafts.length) {
            hideReviewModal();

            if (tbody) {
                tbody.innerHTML = '';
            }

            return;
        }

        if (!tbody) {
            return;
        }

        tbody.innerHTML = drafts.map((row) => {
            const perTray = Math.max(1, Number(row.eggs_per_tray || 30));

            return `<tr>
                <td>${escapeHtml(row.number)}</td>
                <td>${escapeHtml(row.qr_code)}</td>
                <td>${escapeHtml(row.batch)}</td>
                <td>${escapeHtml(row.size)}</td>
                <td>${formatCount(row.trays)}</td>
                <td>${formatCount(row.eggs)}</td>
                <td>
                    <div class="production-review-row-actions">
                        <button
                            type="button"
                            class="production-review-edit"
                            data-id="${escapeHtml(row.id)}"
                            data-batch="${escapeHtml(row.batch)}"
                            data-size="${escapeHtml(row.size)}"
                            data-trays="${escapeHtml(row.trays)}"
                            data-eggs="${escapeHtml(row.eggs)}"
                            data-per-tray="${perTray}">
                            Edit
                        </button>
                        <button
                            type="button"
                            class="production-review-delete"
                            data-id="${escapeHtml(row.id)}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        if (totals) {
            totals.textContent = `${formatCount(summary.scans)} stacks · ${formatCount(summary.trays)} trays · ${formatCount(summary.eggs)} eggs`;
        }

        holdReviewModal();
    }

    document.getElementById('scanReviewBtn')?.addEventListener('click', function () {
        if (!hasDraftRows()) {
            Swal.fire({
                icon: 'info',
                title: 'Review list',
                text: 'No stacks yet. Scan or upload a QR first.',
                confirmButtonText: 'OK'
            });
            return;
        }

        reviewModal()?.show();
    });

    document.getElementById('productionAddStackBtn')?.addEventListener('click', function () {
        const qrCode = this.getAttribute('data-qr');

        if (!qrCode) {
            return;
        }

        jsonRequest(addUrl, 'POST', { qr_code: qrCode })
            .then((res) => {
                if (!res.success) {
                    showError(res.message);
                    return;
                }

                applyListing(res);
            })
            .catch(() => showError('Something went wrong.'));
    });

    document.getElementById('productionReviewPanel')?.addEventListener('click', function (event) {
        const editBtn = event.target.closest('.production-review-edit');
        const deleteBtn = event.target.closest('.production-review-delete');

        if (editBtn) {
            const id = editBtn.getAttribute('data-id');
            const batch = editBtn.getAttribute('data-batch') || '';
            const size = editBtn.getAttribute('data-size') || '';
            const currentTrays = Number(editBtn.getAttribute('data-trays') || 0);
            const currentEggs = Number(editBtn.getAttribute('data-eggs') || 0);
            const perTray = Math.max(1, Number(editBtn.getAttribute('data-per-tray') || 30));

            swalOnModal({
                title: 'Edit stack',
                html: `<p class="production-review-edit-locked" id="reviewEditLocked"></p>
                    <label class="production-review-edit-label" for="reviewEditTrays">Trays</label>
                    <input id="reviewEditTrays" class="swal2-input production-review-edit-input" type="number" min="1" max="999" step="1" value="${currentTrays}">
                    <label class="production-review-edit-label" for="reviewEditEggs">Eggs</label>
                    <input id="reviewEditEggs" class="swal2-input production-review-edit-input" type="number" min="1" step="1" value="${currentEggs}">`,
                showCancelButton: true,
                confirmButtonText: 'Update',
                focusConfirm: false,
                didOpen: () => {
                    const locked = document.getElementById('reviewEditLocked');
                    const traysInput = document.getElementById('reviewEditTrays');
                    const eggsInput = document.getElementById('reviewEditEggs');

                    if (locked) {
                        locked.textContent = `Batch ${batch} · ${size}. Batch and size come from the QR and cannot be changed.`;
                    }

                    traysInput?.addEventListener('input', function () {
                        const trays = Number(this.value);

                        if (!eggsInput || !Number.isInteger(trays) || trays < 1) {
                            return;
                        }

                        eggsInput.value = String(trays * perTray);
                        eggsInput.max = String(trays * perTray);
                    });
                },
                preConfirm: () => {
                    const trays = Number(document.getElementById('reviewEditTrays')?.value);
                    const eggs = Number(document.getElementById('reviewEditEggs')?.value);
                    const maxEggs = Number.isInteger(trays) && trays >= 1 ? trays * perTray : 0;

                    if (!Number.isInteger(trays) || trays < 1 || trays > 999) {
                        Swal.showValidationMessage('Enter 1 to 999 trays.');
                        return false;
                    }

                    if (!Number.isInteger(eggs) || eggs < 1 || eggs > maxEggs) {
                        Swal.showValidationMessage(`Enter 1 to ${maxEggs} eggs for ${trays} trays.`);
                        return false;
                    }

                    return { trays, eggs };
                }
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                return jsonRequest(
                    updateTemplate.replace('__ID__', id),
                    'PATCH',
                    { trays: result.value.trays, eggs: result.value.eggs }
                ).then((res) => {
                    if (!res.success) {
                        showError(res.message);
                        return;
                    }

                    applyListing(res);
                });
            });

            return;
        }

        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');

            swalOnModal({
                icon: 'warning',
                title: 'Remove this stack?',
                text: 'It will be deleted from the review list and will not be saved.',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#b42318'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                return jsonRequest(
                    deleteTemplate.replace('__ID__', id),
                    'DELETE'
                ).then((res) => {
                    if (!res.success) {
                        showError(res.message);
                        return;
                    }

                    applyListing(res);
                });
            });
        }
    });

    document.getElementById('productionSaveBtn')?.addEventListener('click', function () {
        swalOnModal({
            icon: 'warning',
            title: 'Confirm & Save?',
            html: `<p style="margin:0 0 12px">You are about to save <strong>${summaryLabel}</strong> to today’s QR production.</p>
                <p style="margin:0;color:#9a6700">${note}</p>`,
            showCancelButton: true,
            confirmButtonText: 'Confirm & Save',
            cancelButtonText: 'Back to list',
            allowOutsideClick: false
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            return jsonRequest(commitUrl, 'POST', { harvest_date: harvestDate })
                .then((res) => {
                    if (!res.success) {
                        showError(res.message);
                        return;
                    }

                    swalOnModal({
                        icon: 'success',
                        title: 'Saved to QR production',
                        text: res.message || 'The review list is now saved.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        applyListing({ drafts: [], summary: { scans: 0, trays: 0, eggs: 0 } });
                        window.location.reload();
                    });
                })
                .catch(() => showError('Something went wrong.'));
        });
    });

    reviewModalEl()?.addEventListener('hide.bs.modal', function (event) {
        if (document.querySelector('.swal2-container')) {
            event.preventDefault();
        }
    });

    window.applyReviewListing = applyListing;
})();
</script>
@endpush

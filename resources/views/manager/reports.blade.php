@extends('layouts.sidebar')

@section('title', 'Reports')
@section('page-label', 'Reports')

@section('content')
<div class="reports-page">

    <div class="page-header">
        <div class="page-header-copy">
            <h2>Reports</h2>
            <p>Generate, send, and track farm reports for owner review.</p>
        </div>

        <button type="button" class="rpt-btn-generate" data-bs-toggle="modal" data-bs-target="#generateReportModal">
            <i class="fas fa-file-circle-plus"></i>
            Generate Report
        </button>
    </div>

    <div class="rpt-stats">
        <div class="rpt-stat rpt-stat-total">
            <div class="rpt-stat-icon"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="rpt-stat-label">Total Reports</div>
                <div class="rpt-stat-value">{{ $reportStats['total'] }}</div>
            </div>
        </div>

        <div class="rpt-stat rpt-stat-sent">
            <div class="rpt-stat-icon"><i class="fas fa-paper-plane"></i></div>
            <div>
                <div class="rpt-stat-label">Sent</div>
                <div class="rpt-stat-value">{{ $reportStats['sent'] }}</div>
            </div>
        </div>

        <div class="rpt-stat rpt-stat-read">
            <div class="rpt-stat-icon"><i class="fas fa-envelope-open"></i></div>
            <div>
                <div class="rpt-stat-label">Read</div>
                <div class="rpt-stat-value">{{ $reportStats['read'] }}</div>
            </div>
        </div>
    </div>

    <div class="rpt-card">
        <div class="rpt-toolbar">
            <h2>Generated reports</h2>
            <div class="rpt-toolbar-actions">
                <form method="GET" action="{{ route('manager.reports') }}" class="rpt-filter-form">
                    <label class="rpt-filter-field">
                        <span>Type</span>
                        <select name="report_type" data-auto-submit aria-label="Filter reports by type">
                            <option value="">All types</option>
                            <option value="overall" @selected($filters['report_type'] === 'overall')>Overall</option>
                            <option value="production" @selected($filters['report_type'] === 'production')>Production</option>
                            <option value="inventory" @selected($filters['report_type'] === 'inventory')>Inventory</option>
                            <option value="dispatch" @selected($filters['report_type'] === 'dispatch')>Dispatch</option>
                            <option value="sales" @selected($filters['report_type'] === 'sales')>Sales</option>
                        </select>
                    </label>
                    <label class="rpt-filter-field">
                        <span>Status</span>
                        <select name="status" data-auto-submit aria-label="Filter reports by status">
                            <option value="">All status</option>
                            <option value="Draft" @selected($filters['status'] === 'Draft')>Draft</option>
                            <option value="Sent" @selected($filters['status'] === 'Sent')>Sent</option>
                            <option value="Read" @selected($filters['status'] === 'Read')>Read</option>
                        </select>
                    </label>
                    @if($filters['report_type'] || $filters['status'])
                        <a href="{{ route('manager.reports') }}" class="rpt-filter-clear">Clear</a>
                    @endif
                </form>
                <span class="rpt-count">{{ $reports->total() }} {{ Str::plural('record', $reports->total()) }}</span>
            </div>
        </div>

        <div class="rpt-table-wrap">
            <table class="rpt-table has-action-column">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Report No</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Owner Remark</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        @php
                            $type = strtolower($report->report_type);
                            $statusClass = match($report->status) {
                                'Sent' => 'rpt-badge-sent',
                                'Read' => 'rpt-badge-read',
                                default => 'rpt-badge-draft',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="rpt-date">{{ $report->created_at->format('M d, Y') }}</span>
                            </td>
                            <td>
                                <span class="rpt-no">{{ $report->report_no }}</span>
                            </td>
                            <td>
                                <div class="rpt-title">{{ $report->title }}</div>
                            </td>
                            <td>
                                <span class="rpt-chip rpt-chip-{{ $type }}">{{ ucfirst($report->report_type) }}</span>
                            </td>
                            <td>
                                <span class="rpt-badge {{ $statusClass }}">{{ $report->status }}</span>
                            </td>
                            <td>
                                @if($report->owner_comment)
                                    <div class="rpt-remark has-comment" title="{{ $report->owner_comment }}">
                                        {{ Str::limit($report->owner_comment, 40) }}
                                    </div>
                                @else
                                    <div class="rpt-remark">-</div>
                                @endif
                            </td>
                            <td>
                                <div class="rpt-actions">
                                    <button type="button"
                                        class="rpt-icon-btn rpt-icon-view"
                                        title="View"
                                        data-bs-toggle="modal"
                                        data-bs-target="#viewReport{{ $report->id }}">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    @if($report->status == "Draft")
                                        <form action="{{ route('manager.reports.send', $report->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="rpt-icon-btn rpt-icon-send" title="Send">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>

                                        <form action="{{ route('manager.reports.destroy', $report->id) }}" method="POST"
                                            class="report-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rpt-icon-btn rpt-icon-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="rpt-empty">
                                    <i class="fas fa-file-alt"></i>
                                    <p>No Reports Found</p>
                                    <span>Generate a report to send it to the farm owner.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reports->total() > 0)
            <div class="rpt-pagination">
                <div>Showing {{ $reports->firstItem() }}–{{ $reports->lastItem() }} of {{ $reports->total() }}</div>
                <div class="rpt-pagination-controls">
                    @if($reports->onFirstPage())
                        <span class="rpt-page-btn disabled">Prev</span>
                    @else
                        <a href="{{ $reports->previousPageUrl() }}" class="rpt-page-btn">Prev</a>
                    @endif
                    <span>Page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}</span>
                    @if($reports->hasMorePages())
                        <a href="{{ $reports->nextPageUrl() }}" class="rpt-page-btn">Next</a>
                    @else
                        <span class="rpt-page-btn disabled">Next</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- GENERATE REPORT MODAL --}}
    <div class="modal fade report-modal" id="generateReportModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <form method="POST" action="{{ route('manager.reports.store') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Generate Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Report Type</label>
                                <select id="reportType" name="report_type" class="form-select" required>
                                    <option value="">Select Report</option>
                                    <option value="overall">Overall Farm Report</option>
                                    <option value="production">Production Report</option>
                                    <option value="inventory">Inventory Report</option>
                                    <option value="dispatch">Dispatch Report</option>
                                    <option value="sales">Sales Report</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Report Date</label>
                                <input type="date" id="reportDate" class="form-control"
                                    value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Title</label>
                                <input type="text" id="reportTitle" name="title" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="form-label rpt-preview-label">Generated Report</label>
                            <div class="rpt-preview-shell">
                                <div id="generatedPreview">
                                    <div class="rpt-preview-empty">Select a report type to preview the formatted document.</div>
                                </div>
                            </div>
                            <details class="rpt-source-toggle">
                                <summary>Edit source text</summary>
                                <textarea id="generatedContent" name="content" rows="10" class="form-control" required></textarea>
                            </details>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i>
                            Save Draft
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- VIEW REPORT MODALS --}}
    @foreach($reports as $report)
        <div class="modal fade report-modal" id="viewReport{{ $report->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $report->title }}</h5>
                        <div class="rpt-modal-header-actions">
                            <button type="button"
                                class="rpt-btn-print"
                                data-print-report="{{ $report->id }}"
                                aria-label="Print report: {{ $report->title }}"
                                title="Print report">
                                <i class="fas fa-print" aria-hidden="true"></i>
                                <span>Print Report</span>
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body">
                        @php
                            $viewStatusClass = match($report->status) {
                                'Sent' => 'rpt-badge-sent',
                                'Read' => 'rpt-badge-read',
                                default => 'rpt-badge-draft',
                            };
                        @endphp
                        <div class="rpt-meta">
                            <div class="rpt-meta-item">
                                <span>Report No</span>
                                <strong>{{ $report->report_no }}</strong>
                            </div>
                            <div class="rpt-meta-item">
                                <span>Type</span>
                                <strong>{{ ucfirst($report->report_type) }}</strong>
                            </div>
                            <div class="rpt-meta-item">
                                <span>Created</span>
                                <strong>{{ $report->created_at->format('M d, Y - h:i A') }}</strong>
                            </div>
                            <div class="rpt-meta-item">
                                <span>Status</span>
                                <strong>
                                    <span class="rpt-badge {{ $viewStatusClass }}">{{ strtoupper($report->status) }}</span>
                                </strong>
                            </div>
                        </div>

                        <x-report-document :content="$report->content" />

                        @if($report->owner_comment)
                            <div class="rpt-remark-box">
                                <h5>Owner Remark</h5>
                                <p>{{ $report->owner_comment }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

</div>

@push('scripts')
<script>
    document.getElementById('reportType').addEventListener('change', loadReportPreview);
    document.getElementById('reportDate').addEventListener('change', loadReportPreview);

    function loadReportPreview() {
        const type = document.getElementById('reportType').value;
        const date = document.getElementById('reportDate').value;
        const content = document.getElementById('generatedContent');
        const title = document.getElementById('reportTitle');
        const preview = document.getElementById('generatedPreview');

                if (!type || !date) {
                    content.value = '';
                    title.value = '';
                    preview.innerHTML = '<div class="rpt-preview-empty">Select a report type to preview the formatted document.</div>';
                    return;
                }

                const url = @json(url('/manager/reports/preview')) + '/' + encodeURIComponent(type) + '?date=' + encodeURIComponent(date);

                fetch(url)
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Preview failed');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        content.value = data.content || '';
                        title.value = data.title || '';
                        preview.innerHTML = data.html || '';
                    })
                    .catch(function () {
                        content.value = '';
                        title.value = '';
                        preview.innerHTML = '<div class="rpt-preview-empty">Preview could not be generated.</div>';
                    });
    }
</script>

<script>
    const printFileName = (title, reportNumber) => {
        const slug = (value) => String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return `${slug(title) || 'farm-report'}-${slug(reportNumber) || 'report'}`;
    };

    const printReport = (reportId) => {
        const modal = document.getElementById(`viewReport${reportId}`);
        const reportBody = modal?.querySelector('.modal-body');
        const reportTitle = modal?.querySelector('.modal-title')?.textContent?.trim() || 'Farm Report';
        const reportNumber = reportBody?.querySelector('.rpt-meta-item strong')?.textContent?.trim() || `report-${reportId}`;

        if (!reportBody) {
            return;
        }

        const printWindow = window.open('', '_blank', 'width=900,height=1100');
        if (!printWindow) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Print blocked',
                    text: 'Please allow pop-ups for this site and try again.'
                });
            }
            return;
        }

        const escapeHtml = (value) => {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        };
        const fileName = printFileName(reportTitle, reportNumber);
        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
            .map((style) => style.outerHTML)
            .join('');
        const reportMarkup = reportBody.cloneNode(true);
        reportMarkup.querySelectorAll('button, form, textarea, details').forEach((element) => element.remove());
        reportMarkup.classList.remove('modal-body');
        reportMarkup.classList.add('print-report-body');

        printWindow.document.write(`
            <!doctype html>
            <html lang="en">
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>${escapeHtml(fileName)}</title>
                    ${styles}
                    <style>
                        @page {
                            size: A4;
                            margin: 16mm 14mm;
                        }

                        html,
                        body {
                            min-height: 100%;
                        }

                        body {
                            margin: 0;
                            background: #fff;
                            color: #1f2937;
                            font-family: Inter, Arial, sans-serif;
                        }

                        .print-shell {
                            max-width: 820px;
                            margin: 0 auto;
                            padding: 12px 0 24px;
                        }

                        .print-header {
                            margin-bottom: 22px;
                            padding-bottom: 14px;
                            border-bottom: 2px solid #34853f;
                            text-align: center;
                        }

                        .print-brand {
                            color: #34853f;
                            font-size: 12px;
                            font-weight: 800;
                            letter-spacing: .16em;
                            text-transform: uppercase;
                        }

                        .print-header h1 {
                            margin: 7px 0 0;
                            color: #1f2937;
                            font-size: 24px;
                            line-height: 1.2;
                        }

                        .print-header p {
                            margin: 7px 0 0;
                            color: #6b7280;
                            font-size: 12px;
                        }

                        .print-report-body {
                            max-height: none;
                            overflow: visible;
                            padding: 0;
                        }

                        .print-report-body .rpt-meta {
                            grid-template-columns: repeat(4, 1fr);
                            margin-bottom: 18px;
                        }

                        .print-report-body .rpt-doc-card,
                        .print-report-body .rpt-remark-box {
                            box-shadow: none;
                            break-inside: avoid;
                        }

                        .print-report-body .rpt-doc-card {
                            border-color: #d9e3dc;
                        }

                        .print-report-body .rpt-remark-box {
                            margin-top: 18px;
                        }

                        .print-footer {
                            margin-top: 24px;
                            padding-top: 10px;
                            border-top: 1px solid #d9e3dc;
                            color: #6b7280;
                            font-size: 10px;
                            text-align: center;
                        }

                        @media print {
                            .print-shell {
                                max-width: none;
                                padding: 0;
                            }

                            .print-report-body .rpt-doc-card,
                            .print-report-body .rpt-remark-box {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <main class="print-shell">
                        <header class="print-header">
                            <div class="print-brand">NB Poultry Farm</div>
                            <h1>${escapeHtml(reportTitle)}</h1>
                            <p>Farm Management Report</p>
                        </header>
                        ${reportMarkup.outerHTML}
                        <footer class="print-footer">
                            Printed from NB Poultry Farm Management System
                        </footer>
                    </main>
                    <script>
                        (() => {
                            let printStarted = false;
                            const fileName = ${JSON.stringify(fileName)};
                            const finishPrint = () => {
                                if (window.opener && !window.opener.closed) {
                                    window.opener.postMessage({
                                        type: 'report-print-complete',
                                        fileName
                                    }, window.opener.location.origin);
                                }
                                window.close();
                            };
                            const startPrint = () => {
                                if (printStarted) {
                                    return;
                                }

                                printStarted = true;
                                window.focus();
                                window.print();
                            };

                            window.addEventListener('afterprint', () => {
                                window.setTimeout(finishPrint, 250);
                            }, { once: true });
                            window.addEventListener('load', () => {
                                window.setTimeout(startPrint, 250);
                            }, { once: true });
                            window.setTimeout(startPrint, 1000);
                        })();
                    <\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    };

    document.querySelectorAll('[data-print-report]').forEach((button) => {
        button.addEventListener('click', () => printReport(button.dataset.printReport));
    });

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.data?.type !== 'report-print-complete') {
            return;
        }

        const fileName = event.data.fileName || 'farm-report';
        if (window.Swal) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Print dialog completed',
                text: `Suggested filename: ${fileName}.pdf`,
                showConfirmButton: false,
                timer: 4200,
                timerProgressBar: true
            });
        }
    });
</script>

@if(session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session("success") }}',
            timer: 1800,
            showConfirmButton: false
        });
    </script>
@endif

@if(session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session("error") }}'
        });
    </script>
@endif

<script>
    document.querySelectorAll('.report-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();

            Swal.fire({
                icon: 'warning',
                title: 'Delete report?',
                text: 'This action cannot be undone.',
                showCancelButton: true,
                confirmButtonColor: '#d32f2f',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
@endsection

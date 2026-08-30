@extends('layouts.sidebar')

@section('title', 'Reports')
@section('page-label', 'Reports')

@section('content')

    <div class="page-header">

        <h1 class="page-title">

            Reports

        </h1>

        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateReportModal">

            <i class="fas fa-file-circle-plus"></i>

            Generate Report

        </button>

    </div>

    <div class="report-summary">

        <div class="summary-card">

            <i class="fas fa-file-alt"></i>

            <div>

                <span>Total Reports</span>

                <h3>{{ $reports->count() }}</h3>

            </div>

        </div>

        <div class="summary-card">

            <i class="fas fa-paper-plane"></i>

            <div>

                <span>Sent</span>

                <h3>

                    {{ $reports->where('status', 'Sent')->count() }}

                </h3>

            </div>

        </div>

        <div class="summary-card">

            <i class="fas fa-envelope-open"></i>

            <div>

                <span>Read</span>

                <h3>

                    {{ $reports->where('status', 'Read')->count() }}

                </h3>

            </div>

        </div>

    </div>

    <div class="table-container">

        <table class="table">

            <thead>

                <tr>

                    <th>Date</th>

                    <th>Report No</th>

                    <th>Title</th>

                    <th>Type</th>

                    <th>Status</th>

                    <th>Owner Remark</th>

                    <th width="180">

                        Action

                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse($reports as $report)

                    <tr>

                        <td>

                            {{ $report->created_at->format('M d,Y') }}

                        </td>

                        <td>

                            {{ $report->report_no }}

                        </td>

                        <td>

                            {{ $report->title }}

                        </td>

                        <td>

                            {{ ucfirst($report->report_type) }}

                        </td>

                        <td>

                            @if($report->status == "Draft")

                                <span class="badge bg-secondary">

                                    Draft

                                </span>

                            @elseif($report->status == "Sent")

                                <span class="badge bg-warning">

                                    Sent

                                </span>

                            @else

                                <span class="badge bg-success">

                                    Read

                                </span>

                            @endif

                        </td>

                        <td>

                            @if($report->owner_comment)

                                <span class="text-success">

                                    {{ Str::limit($report->owner_comment, 40) }}

                                </span>

                            @else

                                -

                            @endif

                        </td>

                        <td>

                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#viewReport{{ $report->id }}">

                                <i class="fas fa-eye"></i>

                            </button>

                            @if($report->status == "Draft")

                                <form action="{{ route('manager.reports.send', $report->id) }}" method="POST" class="d-inline">

                                    @csrf

                                    <button class="btn btn-success btn-sm">

                                        <i class="fas fa-paper-plane"></i>

                                    </button>

                                </form>

                                <form action="{{ route('manager.reports.destroy', $report->id) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Delete Report?')">

                                    @csrf
                                    @method('DELETE')

                                    <button class="btn btn-danger btn-sm">

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </form>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="7" class="text-center">

                            No Reports Found

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>
    {{-- ===========================================
    GENERATE REPORT MODAL
    =========================================== --}}
    <div class="modal fade" id="generateReportModal" tabindex="-1">

        <div class="modal-dialog modal-xl">

            <form method="POST" action="{{ route('manager.reports.store') }}">

                @csrf

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title">

                            Generate Report

                        </h5>

                        <button class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body">

                        <div class="row g-3">

                            <div class="col-md-4">

                                <label class="form-label">

                                    Report Type

                                </label>

                                <select id="reportType" name="report_type" class="form-select" required>

                                    <option value="">

                                        Select Report

                                    </option>

                                    <option value="overall">

                                        Overall Farm Report

                                    </option>

                                    <option value="production">

                                        Production Report

                                    </option>

                                    <option value="inventory">

                                        Inventory Report

                                    </option>

                                    <option value="dispatch">

                                        Dispatch Report

                                    </option>

                                    <option value="sales">

                                        Sales Report

                                    </option>

                                </select>

                            </div>

                            <div class="col-md-4">

                                <label class="form-label">

                                    Report Date

                                </label>

                                <input type="date" id="reportDate" class="form-control"
                                    value="{{ now()->toDateString() }}" required>

                            </div>

                            <div class="col-md-4">

                                <label class="form-label">

                                    Title

                                </label>

                                <input type="text" id="reportTitle" name="title" class="form-control" required>

                            </div>

                        </div>

                        <div class="mt-4">

                            <label class="form-label">

                                Generated Report

                            </label>

                            <textarea id="generatedContent" name="content" rows="18" class="form-control"
                                required></textarea>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                            Cancel

                        </button>

                        <button type="submit" class="btn btn-success">

                            <i class="fas fa-save"></i>

                            Save Draft

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>
    {{-- ===========================================
    VIEW REPORT MODALS
    =========================================== --}}

    @foreach($reports as $report)

        <div class="modal fade" id="viewReport{{ $report->id }}" tabindex="-1">

            <div class="modal-dialog modal-xl">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title">

                            {{ $report->title }}

                        </h5>

                        <button class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body">

                        <div class="mb-3">

                            <strong>Report No :</strong>

                            {{ $report->report_no }}

                        </div>

                        <div class="mb-3">

                            <strong>Type :</strong>

                            {{ ucfirst($report->report_type) }}

                        </div>

                        <hr>

                        {!! nl2br(e($report->content)) !!}

                        @if($report->owner_comment)

                            <hr>

                            <h5 class="text-success">

                                Owner Remark

                            </h5>

                            <div class="alert alert-success">

                                {{ $report->owner_comment }}

                            </div>

                        @endif

                    </div>

                    <div class="modal-footer">

                        <button class="btn btn-secondary" data-bs-dismiss="modal">

                            Close

                        </button>

                    </div>

                </div>

            </div>

        </div>

    @endforeach


    @push('scripts')

        <script>

            document.getElementById('reportType').addEventListener('change', loadReportPreview);
            document.getElementById('reportDate').addEventListener('change', loadReportPreview);

            function loadReportPreview() {
                const type = document.getElementById('reportType').value;
                const date = document.getElementById('reportDate').value;
                const content = document.getElementById('generatedContent');
                const title = document.getElementById('reportTitle');

                if (!type || !date) {
                    content.value = '';
                    title.value = '';
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
                    })
                    .catch(function () {
                        content.value = '';
                        title.value = '';
                    });
            }

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

    @endpush

@endsection
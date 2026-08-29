@extends('layouts.sidebar')

@section('title', 'Reports')
@section('page-label', 'Reports')

@section('content')

    <div class="container-fluid py-4">

        <div class="page-header">

            <h1 class="page-title">

                Reports

            </h1>

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

                    <span>Unread Reports</span>

                    <h3>{{ $reports->where('status', 'Sent')->count() }}</h3>

                </div>

            </div>

            <div class="summary-card">

                <i class="fas fa-envelope-open"></i>

                <div>

                    <span>Reviewed Reports</span>

                    <h3>{{ $reports->where('status', 'Read')->count() }}</h3>

                </div>

            </div>

        </div>

        <div class="table-container">

            <table class="table align-middle">

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Report No</th>

                        <th>Title</th>

                        <th>Type</th>

                        <th>Status</th>

                        <th>Manager</th>

                        <th width="120">Action</th>

                    </tr>

                </thead>

                <tbody>
                    @forelse($reports as $report)

                        <tr>

                            <td>

                                {{ $report->created_at->format('M d, Y') }}

                            </td>

                            <td>

                                <strong>{{ $report->report_no }}</strong>

                            </td>

                            <td>

                                {{ $report->title }}

                            </td>

                            <td>

                                {{ ucfirst($report->report_type) }}

                            </td>

                            <td>

                                @if($report->status == 'Sent')

                                    <span class="badge bg-warning">

                                        Unread

                                    </span>

                                @elseif($report->status == 'Read')

                                    <span class="badge bg-success">

                                        Read

                                    </span>

                                @else

                                    <span class="badge bg-secondary">

                                        {{ $report->status }}

                                    </span>

                                @endif

                            </td>

                            <td>

                                {{ optional($report->manager)->name ?? 'Manager' }}

                            </td>

                            <td>

                                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#viewReport{{ $report->id }}">

                                    <i class="fas fa-eye"></i>

                                    View

                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="7" class="text-center py-4">

                                No reports available.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>
        </tbody>
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

                            <div class="row mb-3">

                                <div class="col-md-4">

                                    <strong>Report No</strong>

                                    <p>{{ $report->report_no }}</p>

                                </div>

                                <div class="col-md-4">

                                    <strong>Report Type</strong>

                                    <p>{{ ucfirst($report->report_type) }}</p>

                                </div>

                                <div class="col-md-4">

                                    <strong>Date Submitted</strong>

                                    <p>{{ $report->created_at->format('F d, Y h:i A') }}</p>

                                </div>

                            </div>

                            <hr>

                            <h6 class="fw-bold">

                                Manager Report

                            </h6>

                            <div class="border rounded p-3 bg-light mb-4">

                                {!! nl2br(e($report->content)) !!}

                            </div>

                            <h6 class="fw-bold">

                                Owner Remarks

                            </h6>

                            <form action="{{ route('owner.reports.remark', $report->id) }}" method="POST">

                                @csrf

                                <textarea name="owner_remark" rows="5" class="form-control"
                                    placeholder="Write your remarks here...">{{ old('owner_remark', $report->owner_remark) }}</textarea>

                                <div class="text-end mt-3">

                                    <button type="submit" class="btn btn-success">

                                        <i class="fas fa-paper-plane"></i>

                                        Save Remark

                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        @endforeach
        @push('scripts')

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
</div>
@endsection
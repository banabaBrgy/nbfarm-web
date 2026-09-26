@extends('layouts.sidebar')

@section('title', 'Reports')
@section('page-label', 'Reports')

@section('content')

    <div class="reports-page">

        <div class="page-header">
            <div class="page-header-copy">
                <h2>Reports</h2>
                <p>Review farm reports sent by the manager.</p>
            </div>
        </div>

        <div class="rpt-stats">
            <div class="rpt-stat rpt-stat-total">
                <div class="rpt-stat-icon"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="rpt-stat-label">Total Reports</div>
                    <div class="rpt-stat-value">{{ $reports->count() }}</div>
                </div>
            </div>

            <div class="rpt-stat rpt-stat-sent">
                <div class="rpt-stat-icon"><i class="fas fa-paper-plane"></i></div>
                <div>
                    <div class="rpt-stat-label">Unread</div>
                    <div class="rpt-stat-value">{{ $reports->where('status', 'Sent')->count() }}</div>
                </div>
            </div>

            <div class="rpt-stat rpt-stat-read">
                <div class="rpt-stat-icon"><i class="fas fa-envelope-open"></i></div>
                <div>
                    <div class="rpt-stat-label">Reviewed</div>
                    <div class="rpt-stat-value">{{ $reports->where('status', 'Read')->count() }}</div>
                </div>
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-toolbar">
                <h2>Received reports</h2>
                <span class="rpt-count">{{ $reports->count() }} {{ Str::plural('record', $reports->count()) }}</span>
            </div>

            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Report No</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Manager</th>
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
                                    <span class="rpt-badge {{ $statusClass }}">
                                        {{ $report->status == 'Sent' ? 'Unread' : $report->status }}
                                    </span>
                                </td>
                                <td>
                                    {{ optional($report->manager)->name ?? 'Manager' }}
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
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="rpt-empty">
                                        <i class="fas fa-file-alt"></i>
                                        <p>No reports available</p>
                                        <span>Reports sent by the manager will appear here.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{-- ===========================================
        VIEW REPORT MODALS
        =========================================== --}}

        @foreach($reports as $report)

            <div class="modal fade report-modal" id="viewReport{{ $report->id }}" tabindex="-1">

                <div class="modal-dialog modal-xl">

                    <div class="modal-content">

                        <div class="modal-header">

                            <h5 class="modal-title">

                                {{ $report->title }}

                            </h5>

                            <button class="btn-close" data-bs-dismiss="modal"></button>

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

                            <div class="mt-4">

                            <h6 class="fw-bold">

                                Owner Remarks

                            </h6>

                            <form action="{{ route('owner.reports.remark', $report->id) }}" method="POST">

                                @csrf

                                <textarea name="owner_comment" rows="5" class="form-control"
                                    placeholder="Write your remarks here...">{{ old('owner_comment', $report->owner_comment) }}</textarea>

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
@extends('layouts.app')

@section('title', 'Orders')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Orders</h2>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Uid</th>
                        <th>Status</th>
                        <th>Total (minor)</th>
                        <th>Created</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="font-monospace">{{ $order->id }}</a>
                            </td>
                            <td>{{ $order->uid }}</td>
                            <td>{{ $order->status->label() }} ({{ $order->status->value }})</td>
                            <td>{{ $order->total_price }}</td>
                            <td class="text-muted small">{{ \App\Support\MillisTimestampDisplay::format($order->ct) }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded" title="Edit status"
                                        data-bs-toggle="modal" data-bs-target="#mallModalOrderEdit"
                                        data-order-update-url="{{ route('admin.orders.update', $order->id) }}"
                                        data-order-status-value="{{ $order->status->value }}">
                                    @include('admin.partials.icon_pencil')
                                </button>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger" title="Delete"
                                        data-mall-delete-url="{{ route('admin.orders.destroy', $order->id) }}"
                                        data-mall-delete-message="Delete order #{{ $order->id }}?">
                                    @include('admin.partials.icon_trash')
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{ $orders->withQueryString()->links() }}

    <div class="modal fade" id="mallModalOrderEdit" tabindex="-1" aria-labelledby="mallModalOrderEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" id="mall-form-order-update">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="redirect_to" value="list">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="mallModalOrderEditLabel">Edit order status</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="order-status-select">Status</label>
                        <select name="status" id="order-status-select" class="form-select" required>
                            @foreach($statuses as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }} ({{ $st->value }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('mall-form-order-update');
            var modalEl = document.getElementById('mallModalOrderEdit');
            var sel = document.getElementById('order-status-select');
            if (!form || !modalEl || !sel) {
                return;
            }
            modalEl.addEventListener('show.bs.modal', function (e) {
                var btn = e.relatedTarget;
                if (!btn || !form) {
                    return;
                }
                var url = btn.getAttribute('data-order-update-url');
                var st = btn.getAttribute('data-order-status-value');
                if (url) {
                    form.action = url;
                }
                if (st !== null && st !== '') {
                    sel.value = st;
                }
            });
        })();
    </script>
@endpush

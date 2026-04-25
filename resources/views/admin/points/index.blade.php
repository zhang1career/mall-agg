@extends('layouts.app')

@section('title', 'Points')

@section('content')
    <nav class="mall-subnav d-flex flex-wrap gap-2 mb-4" aria-label="Points">
        <a href="{{ route('admin.points.index', ['tab' => 'balances']) }}"
           class="btn btn-sm {{ $tab === 'balances' ? 'btn-primary' : 'btn-outline-secondary' }}">Balances</a>
        <a href="{{ route('admin.points.index', ['tab' => 'flows']) }}"
           class="btn btn-sm {{ $tab === 'flows' ? 'btn-primary' : 'btn-outline-secondary' }}">Flows</a>
    </nav>

    @if($errors->has('delete'))
        <div class="alert alert-danger py-2">{{ $errors->first('delete') }}</div>
    @endif

    @if($tab === 'balances' && $balances)
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">Balances</h2>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mallModalOpenAccount">New</button>
        </div>

        <div class="mall-console-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>UID</th>
                            <th class="text-end">Balance (minor)</th>
                            <th>Updated</th>
                            <th class="text-end text-nowrap">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($balances as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.points.balances.show', $row->id) }}" class="font-monospace">{{ $row->id }}</a>
                                </td>
                                <td>{{ $row->uid }}</td>
                                <td class="text-end font-monospace">{{ number_format((int) $row->balance_minor) }}</td>
                                <td class="text-muted small">{{ \App\Support\MillisTimestampDisplay::format($row->ut) }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded" title="Adjust"
                                            data-bs-toggle="modal" data-bs-target="#mallModalAdjust"
                                            data-balance-uid="{{ $row->uid }}">
                                        @include('admin.partials.icon_pencil')
                                    </button>
                                    <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger" title="Delete"
                                            data-mall-delete-url="{{ route('admin.points.balances.destroy', $row->id) }}"
                                            data-mall-delete-message="Delete balance #{{ $row->id }}? Balance must be zero.">
                                        @include('admin.partials.icon_trash')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No accounts yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {{ $balances->appends(['tab' => 'balances'])->links() }}

    @elseif($tab === 'flows' && $flows)
        <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">Flows</h2>
        </div>

        <div class="mall-console-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>UID</th>
                            <th>OID</th>
                            <th class="text-end">Amount</th>
                            <th>State</th>
                            <th>TCC key</th>
                            <th class="text-end text-nowrap">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($flows as $f)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.points.flows.show', $f->id) }}" class="font-monospace">{{ $f->id }}</a>
                                </td>
                                <td>{{ $f->uid }}</td>
                                <td>{{ $f->oid }}</td>
                                <td class="text-end font-monospace">{{ number_format((int) $f->amount_minor) }}</td>
                                <td>
                                    <span class="badge mall-badge-soft"
                                          data-mall-dict-code="points_hold_state"
                                          data-mall-dict-value="{{ $f->state->value }}">{{ $f->state->value }}</span>
                                </td>
                                <td class="small font-monospace text-break">{{ $f->tcc_idem_key ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded" title="View"
                                            data-bs-toggle="modal" data-bs-target="#mallModalFlowView"
                                            data-flow-id="{{ $f->id }}"
                                            data-flow-uid="{{ $f->uid }}"
                                            data-flow-oid="{{ $f->oid }}"
                                            data-flow-amount="{{ $f->amount_minor }}"
                                            data-flow-state="{{ $f->state->value }}"
                                            data-flow-tcc="{{ $f->tcc_idem_key ?? '' }}"
                                            data-flow-ct="{{ \App\Support\MillisTimestampDisplay::format($f->ct) }}"
                                            data-flow-ut="{{ \App\Support\MillisTimestampDisplay::format($f->ut) }}">
                                        @include('admin.partials.icon_pencil')
                                    </button>
                                    @if($f->state === \App\Enums\PointsHoldState::AdminLedger)
                                        <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger" title="Delete"
                                                data-mall-delete-url="{{ route('admin.points.flows.destroy', $f->id) }}"
                                                data-mall-delete-message="Delete flow #{{ $f->id }}?">
                                            @include('admin.partials.icon_trash')
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No flow rows.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {{ $flows->appends(['tab' => 'flows'])->links() }}
    @endif

    {{-- Open account --}}
    <div class="modal fade" id="mallModalOpenAccount" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.points.accounts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">New account</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->has('account'))
                            <div class="alert alert-danger py-2">{{ $errors->first('account') }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="m-open-uid">User id</label>
                            <input type="number" name="uid" id="m-open-uid" class="form-control" required min="1" value="{{ old('uid') }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="m-open-bal">Initial balance (minor)</label>
                            <input type="number" name="balance_minor" id="m-open-bal" class="form-control" min="0" value="{{ old('balance_minor', 0) }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Adjust --}}
    <div class="modal fade" id="mallModalAdjust" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('admin.points.adjust') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">Adjust balance</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->has('adjust'))
                            <div class="alert alert-danger py-2">{{ $errors->first('adjust') }}</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label" for="m-adj-uid">User id</label>
                            <input type="number" name="uid" id="m-adj-uid" class="form-control" required min="1" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="m-adj-delta">Delta (minor)</label>
                            <input type="number" name="delta_minor" id="m-adj-delta" class="form-control" required value="{{ old('delta_minor') }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="m-adj-oid">Order id (optional)</label>
                            <input type="number" name="oid" id="m-adj-oid" class="form-control" min="0" value="{{ old('oid', 0) }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Flow read-only --}}
    <div class="modal fade" id="mallModalFlowView" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5">Flow <span id="m-flow-title-id"></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body small">
                    <dl class="row mb-0">
                        <dt class="col-4">UID</dt>
                        <dd class="col-8" id="m-flow-uid"></dd>
                        <dt class="col-4">OID</dt>
                        <dd class="col-8" id="m-flow-oid"></dd>
                        <dt class="col-4">Amount</dt>
                        <dd class="col-8 font-monospace" id="m-flow-amount"></dd>
                        <dt class="col-4">State</dt>
                        <dd class="col-8" id="m-flow-state"></dd>
                        <dt class="col-4">TCC</dt>
                        <dd class="col-8 font-monospace text-break" id="m-flow-tcc"></dd>
                        <dt class="col-4">ct / ut</dt>
                        <dd class="col-8" id="m-flow-ctut"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var adjModal = document.getElementById('mallModalAdjust');
            if (adjModal) {
                adjModal.addEventListener('show.bs.modal', function (e) {
                    var btn = e.relatedTarget;
                    var uid = btn && btn.getAttribute('data-balance-uid');
                    var input = document.getElementById('m-adj-uid');
                    if (input && uid) {
                        input.value = uid;
                    }
                });
            }
            var flowModal = document.getElementById('mallModalFlowView');
            if (flowModal) {
                flowModal.addEventListener('show.bs.modal', function (e) {
                    var btn = e.relatedTarget;
                    if (!btn) {
                        return;
                    }
                    var id = document.getElementById('m-flow-title-id');
                    if (id) {
                        id.textContent = '#' + (btn.getAttribute('data-flow-id') || '');
                    }
                    var map = [
                        ['m-flow-uid', 'data-flow-uid'],
                        ['m-flow-oid', 'data-flow-oid'],
                        ['m-flow-amount', 'data-flow-amount'],
                        ['m-flow-tcc', 'data-flow-tcc'],
                    ];
                    map.forEach(function (pair) {
                        var el = document.getElementById(pair[0]);
                        if (el) {
                            el.textContent = btn.getAttribute(pair[1]) || '—';
                        }
                    });
                    var stateVal = btn.getAttribute('data-flow-state') || '';
                    var elState = document.getElementById('m-flow-state');
                    if (elState) {
                        if (window.mallDictEnsure) {
                            window.mallDictEnsure(['points_hold_state'], function () {
                                elState.textContent = window.mallDictLabel
                                    ? window.mallDictLabel('points_hold_state', stateVal)
                                    : stateVal;
                            });
                        } else {
                            elState.textContent = stateVal || '—';
                        }
                    }
                    var ct = btn.getAttribute('data-flow-ct') || '';
                    var ut = btn.getAttribute('data-flow-ut') || '';
                    var elCt = document.getElementById('m-flow-ctut');
                    if (elCt) {
                        elCt.textContent = ct + ' / ' + ut;
                    }
                });
            }
        })();
    </script>
    @if($errors->has('account') || $errors->has('adjust'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var m = new bootstrap.Modal(document.getElementById('{{ $errors->has('account') ? 'mallModalOpenAccount' : 'mallModalAdjust' }}'));
                m.show();
            });
        </script>
    @endif
@endpush

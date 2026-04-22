@extends('layouts.app')

@section('title', 'Points')

@section('content')
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="mall-console-card card h-100 shadow-sm">
                <div class="card-header mall-console-card-header">
                    <h2 class="h6 mb-0">Open account</h2>
                    <p class="small text-muted mb-0 mt-1">Create a row in <code class="mall-inline-code">points_balance</code> (one per user).</p>
                </div>
                <div class="card-body">
                    @if($errors->has('account'))
                        <div class="alert alert-danger py-2">{{ $errors->first('account') }}</div>
                    @endif
                    <form method="post" action="{{ route('admin.points.accounts.store') }}" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label" for="open-uid">User id</label>
                            <input type="number" name="uid" id="open-uid" class="form-control" required min="1"
                                   value="{{ old('uid') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="open-balance">Initial balance (minor)</label>
                            <input type="number" name="balance_minor" id="open-balance" class="form-control" min="0"
                                   value="{{ old('balance_minor', 0) }}">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Create account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="mall-console-card card h-100 shadow-sm">
                <div class="card-header mall-console-card-header">
                    <h2 class="h6 mb-0">Adjust balance</h2>
                    <p class="small text-muted mb-0 mt-1">Updates <code class="mall-inline-code">points_balance</code> and appends one <code class="mall-inline-code">points_flow</code> row (ledger).</p>
                </div>
                <div class="card-body">
                    @if($errors->has('adjust'))
                        <div class="alert alert-danger py-2">{{ $errors->first('adjust') }}</div>
                    @endif
                    <form method="post" action="{{ route('admin.points.adjust') }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label" for="adj-uid">User id</label>
                            <input type="number" name="uid" id="adj-uid" class="form-control" required min="1"
                                   value="{{ old('uid') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="adj-delta">Delta (minor)</label>
                            <input type="number" name="delta_minor" id="adj-delta" class="form-control" required
                                   value="{{ old('delta_minor') }}" placeholder="e.g. 100 or -50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="adj-oid">Order id (optional)</label>
                            <input type="number" name="oid" id="adj-oid" class="form-control" min="0"
                                   value="{{ old('oid', 0) }}">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply adjustment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="mall-console-card card shadow-sm mb-4">
        <div class="card-header mall-console-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h2 class="h6 mb-0">Balances</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>UID</th>
                        <th class="text-end">Balance (minor)</th>
                        <th class="text-nowrap">Updated</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($balances as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->uid }}</td>
                            <td class="text-end font-monospace">{{ number_format((int) $row->balance_minor) }}</td>
                            <td class="text-muted small">{{ $row->ut }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No accounts yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($balances->hasPages())
            <div class="card-footer mall-console-card-footer">
                {{ $balances->links() }}
            </div>
        @endif
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-header mall-console-card-header">
            <h2 class="h6 mb-0">Recent flow (last 25)</h2>
            <p class="small text-muted mb-0 mt-1">Includes TCC holds and manual adjustments (state <span class="font-monospace">60</span> = admin ledger).</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>UID</th>
                        <th>OID</th>
                        <th class="text-end">Amount</th>
                        <th>State</th>
                        <th>TCC key</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentFlows as $f)
                        <tr>
                            <td>{{ $f->id }}</td>
                            <td>{{ $f->uid }}</td>
                            <td>{{ $f->oid }}</td>
                            <td class="text-end font-monospace">{{ number_format((int) $f->amount_minor) }}</td>
                            <td><span class="badge mall-badge-soft">{{ $f->state->value }}</span></td>
                            <td class="small font-monospace text-break">{{ $f->tcc_idem_key ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No flow rows.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

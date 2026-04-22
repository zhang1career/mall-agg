@extends('layouts.app')

@section('title', 'Points · Flow')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Flow #{{ $flow->id }}</h2>
        <a href="{{ route('admin.points.index', ['tab' => 'flows']) }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $flow->id }}</dd>
                <dt class="col-sm-3">UID</dt>
                <dd class="col-sm-9">{{ $flow->uid }}</dd>
                <dt class="col-sm-3">OID</dt>
                <dd class="col-sm-9">{{ $flow->oid }}</dd>
                <dt class="col-sm-3">Amount (minor)</dt>
                <dd class="col-sm-9 font-monospace">{{ number_format((int) $flow->amount_minor) }}</dd>
                <dt class="col-sm-3">State</dt>
                <dd class="col-sm-9">
                    <span class="badge mall-badge-soft"
                          data-mall-dict-code="points_hold_state"
                          data-mall-dict-value="{{ $flow->state->value }}">{{ $flow->state->value }}</span>
                </dd>
                <dt class="col-sm-3">TCC idem key</dt>
                <dd class="col-sm-9 font-monospace text-break">{{ $flow->tcc_idem_key ?? '—' }}</dd>
                <dt class="col-sm-3">ct / ut</dt>
                <dd class="col-sm-9 text-muted small">{{ \App\Support\MillisTimestampDisplay::format($flow->ct) }}
                    / {{ \App\Support\MillisTimestampDisplay::format($flow->ut) }}</dd>
            </dl>
        </div>
    </div>
@endsection

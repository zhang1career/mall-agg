@extends('layouts.app')

@section('title', 'Product')

@section('content')
    @php $pid = (int) ($product['id'] ?? 0); @endphp
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Product #{{ $pid }}</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.products.edit', $pid) }}" class="btn btn-primary btn-sm">Edit</a>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
        </div>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9 font-monospace">{{ $pid }}</dd>
                <dt class="col-sm-3">Title</dt>
                <dd class="col-sm-9">{{ $product['title'] ?? '' }}</dd>
                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9"><pre class="mb-0 small bg-body-secondary p-2 rounded">{{ $product['description'] ?? '' }}</pre></dd>
                <dt class="col-sm-3">Price (minor)</dt>
                <dd class="col-sm-9">{{ $price ?? '—' }}</dd>
                <dt class="col-sm-3">Stock</dt>
                <dd class="col-sm-9">{{ $quantity ?? '—' }}</dd>
            </dl>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="mall-list-toolbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Products</h2>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">New</a>
    </div>

    <div class="mall-console-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 mall-data-table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $row)
                        @php $pid = (int)($row['id'] ?? 0); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.products.show', $pid) }}" class="font-monospace">{{ $pid }}</a>
                            </td>
                            <td>{{ $row['title'] ?? '' }}</td>
                            <td>{{ $priceMap[$pid] ?? '—' }}</td>
                            <td>{{ $qtyMap[$pid] ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.products.edit', $pid) }}" class="mall-icon-btn d-inline-flex p-1 rounded text-decoration-none"
                                   title="Edit" aria-label="Edit">
                                    @include('admin.partials.icon_pencil')
                                </a>
                                <button type="button" class="mall-icon-btn d-inline-flex p-1 rounded text-danger"
                                        title="Delete" aria-label="Delete"
                                        data-mall-delete-url="{{ route('admin.products.destroy', $pid) }}"
                                        data-mall-delete-message="Delete product #{{ $pid }}?">
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

    @php
        $p = $pagination;
        $cur = (int)($p['current_page'] ?? 1);
        $last = (int)($p['last_page'] ?? 1);
    @endphp
    <nav class="mt-3">
        <ul class="pagination">
            @if($cur > 1)
                <li class="page-item"><a class="page-link" href="?page={{ $cur - 1 }}">Previous</a></li>
            @endif
            <li class="page-item disabled"><span class="page-link">Page {{ $cur }} / {{ $last }}</span></li>
            @if($cur < $last)
                <li class="page-item"><a class="page-link" href="?page={{ $cur + 1 }}">Next</a></li>
            @endif
        </ul>
    </nav>
@endsection

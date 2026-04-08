@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">New product</a>
    </div>

    <table class="table table-striped bg-white shadow-sm">
        <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Price</th>
            <th>Stock</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $row)
            @php $pid = (int)($row['id'] ?? 0); @endphp
            <tr>
                <td>{{ $pid }}</td>
                <td>{{ $row['title'] ?? '' }}</td>
                <td>{{ $priceMap[$pid] ?? '—' }}</td>
                <td>{{ $qtyMap[$pid] ?? '—' }}</td>
                <td>
                    <a href="{{ route('admin.products.edit', $pid) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form action="{{ route('admin.products.destroy', $pid) }}" method="post" class="d-inline"
                          onsubmit="return confirm('Delete this product?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @php
        $p = $pagination;
        $cur = (int)($p['current_page'] ?? 1);
        $last = (int)($p['last_page'] ?? 1);
    @endphp
    <nav>
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

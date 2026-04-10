@extends('layouts.app')

@section('title', 'Orders')

@section('content')
    <table class="table table-striped bg-white shadow-sm">
        <thead>
        <tr>
            <th>ID</th>
            <th>Uid</th>
            <th>Status</th>
            <th>Total (minor)</th>
            <th>ct (ms)</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($orders as $order)
            <tr>
                <td>{{ $order->id }}</td>
                <td>{{ $order->uid }}</td>
                <td>{{ $order->status->label() }} ({{ $order->status->value }})</td>
                <td>{{ $order->total_price }}</td>
                <td>{{ $order->ct }}</td>
                <td><a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{ $orders->withQueryString()->links() }}
@endsection

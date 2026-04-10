@extends('layouts.app')

@section('title', 'Order '.$order->id)

@section('content')
    <div class="bg-white shadow-sm p-4 rounded mb-4">
        <p><strong>Uid:</strong> {{ $order->uid }}</p>
        <p><strong>Status:</strong> {{ $order->status->label() }} ({{ $order->status->value }})</p>
        <p><strong>Total (minor):</strong> {{ $order->total_price }}</p>
        <p><strong>ct / ut (ms):</strong> {{ $order->ct }} / {{ $order->ut }}</p>
    </div>

    <h2 class="h5">Lines</h2>
    <table class="table table-sm bg-white shadow-sm">
        <thead>
        <tr>
            <th>Pid</th>
            <th>Qty</th>
            <th>Unit price (minor)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->pid }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->unit_price }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <form method="post" action="{{ route('admin.orders.update', $order->id) }}" class="mt-4 bg-white shadow-sm p-4 rounded">
        @csrf
        @method('PATCH')
        <label class="form-label">Change status</label>
        <select name="status" class="form-select w-auto">
            @foreach($statuses as $st)
                <option value="{{ $st->value }}" @selected($order->status === $st)>{{ $st->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary mt-2">Update status</button>
    </form>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3">Back to orders</a>
@endsection

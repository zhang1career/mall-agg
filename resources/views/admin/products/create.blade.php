@extends('layouts.app')

@section('title', 'New product')

@section('content')
    <form method="post" action="{{ route('admin.products.store') }}" class="bg-white shadow-sm p-4 rounded">
        @csrf
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input name="title" type="text" class="form-control" value="{{ old('title') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
        </div>
        @include('admin.products.partials.media-upload', [
            'thumbnail' => old('thumbnail'),
            'main_media' => old('main_media'),
            'ext_media' => old('ext_media'),
        ])
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Price (minor units, e.g. cents)</label>
                <input name="price" type="number" min="0" class="form-control" value="{{ old('price', 0) }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Initial stock</label>
                <input name="quantity" type="number" min="0" class="form-control" value="{{ old('quantity', 0) }}">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-link">Cancel</a>
    </form>
@endsection

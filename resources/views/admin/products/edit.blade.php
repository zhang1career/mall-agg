@extends('layouts.app')

@section('title', 'Edit product')

@section('content')
    <h1 class="h3 mb-3">Edit product #{{ $product['id'] ?? '' }}</h1>

    <form method="post" action="{{ route('admin.products.update', $product['id']) }}" class="bg-white shadow-sm p-4 rounded">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input name="title" type="text" class="form-control"
                   value="{{ old('title', $product['title'] ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"
                      rows="3">{{ old('description', $product['description'] ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Thumbnail URL</label>
            <input name="thumbnail" type="text" class="form-control"
                   value="{{ old('thumbnail', $product['thumbnail'] ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Main media</label>
            <input name="main_media" type="text" class="form-control"
                   value="{{ old('main_media', $product['main_media'] ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Ext media</label>
            <input name="ext_media" type="text" class="form-control"
                   value="{{ old('ext_media', $product['ext_media'] ?? '') }}">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Price (minor units)</label>
                <input name="price" type="number" min="0" class="form-control"
                       value="{{ old('price', $price ?? 0) }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Stock quantity</label>
                <input name="quantity" type="number" min="0" class="form-control"
                       value="{{ old('quantity', $quantity ?? 0) }}">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-link">Back</a>
    </form>
@endsection

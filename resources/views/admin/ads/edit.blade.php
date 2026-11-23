@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Sửa quảng cáo</h1>
    <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary btn-sm">Quay lại</a>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.ads.update', $ad) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.ads._form')
            <button class="btn btn-primary mt-4" type="submit">Lưu thay đổi</button>
        </form>
    </div>
</div>
@endsection

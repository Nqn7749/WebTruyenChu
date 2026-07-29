@extends('layouts.admin')

@section('content')
<h4>Chào mừng, {{ auth()->user()->name }}!</h4>
<p>Đây là trang quản trị hệ thống Web Đọc Truyện Chữ.</p>
@endsection
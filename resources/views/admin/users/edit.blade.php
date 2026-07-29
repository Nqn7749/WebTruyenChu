@extends('layouts.admin')

@section('content')
<h4>Sửa người dùng: {{ $user->name }}</h4>

<form action="{{ route('admin.users.update', $user) }}" method="POST">
    @csrf @method('PUT')

    <div class="mb-3">
        <label class="form-label">Vai trò</label>
        <select name="role_id" class="form-select">
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" {{ $user->role_id == $role->id ? 'selected' : '' }}>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3 form-check form-switch">
        <input type="hidden" name="status" value="0">
        <input type="checkbox" name="status" value="1" class="form-check-input"
               {{ $user->status ? 'checked' : '' }}>
        <label class="form-check-label">Tài khoản hoạt động</label>
    </div>

    <button class="btn btn-primary">Cập nhật</button>
</form>
@endsection
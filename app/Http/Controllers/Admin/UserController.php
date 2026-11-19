<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('role')->latest()->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['status'] = $request->boolean('status');

        DB::transaction(function () use ($data, $user) {
            $adminRoleId = Role::where('slug', 'admin')->value('id');

            if (! $adminRoleId) {
                throw ValidationException::withMessages([
                    'role_id' => 'Vai trò quản trị chưa được cấu hình.',
                ]);
            }

            // Lock active admin rows so concurrent demotion/deactivation
            // requests cannot both remove the final active administrator.
            $activeAdmins = User::query()
                ->where('role_id', $adminRoleId)
                ->where('status', true)
                ->lockForUpdate()
                ->get(['id']);

            $isChangingOwnAccess = $user->is(auth()->user())
                && (
                    (int) $data['role_id'] !== (int) $user->role_id
                    || ! $data['status']
                );

            if ($isChangingOwnAccess) {
                throw ValidationException::withMessages([
                    'role_id' => 'Bạn không thể tự thay đổi quyền hoặc vô hiệu hóa tài khoản của mình.',
                ]);
            }

            $isRemovingAdminAccess = (int) $user->role_id === (int) $adminRoleId
                && (bool) $user->status
                && (int) $data['role_id'] !== (int) $adminRoleId;

            $isDeactivatingAdmin = (int) $user->role_id === (int) $adminRoleId
                && (bool) $user->status
                && ! $data['status'];

            if (
                ($isRemovingAdminAccess || $isDeactivatingAdmin)
                && $activeAdmins->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'role_id' => 'Không thể hạ quyền hoặc vô hiệu hóa quản trị viên hoạt động cuối cùng.',
                ]);
            }

            // role_id/status không nằm trong $fillable của User nên gán riêng ở đây.
            $user->forceFill($data)->save();
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Cập nhật người dùng thành công.');
    }
}

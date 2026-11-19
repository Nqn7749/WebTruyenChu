<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdminAccountProtectionService
{
    /**
     * Call inside the account-deletion transaction so the locks are held
     * until the deletion commits.
     */
    public function assertCanDelete(User $user): void
    {
        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');

        if (! $adminRoleId || (int) $user->role_id !== (int) $adminRoleId || ! $user->status) {
            return;
        }

        $activeAdmins = User::query()
            ->where('role_id', $adminRoleId)
            ->where('status', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        if ($activeAdmins->count() <= 1) {
            throw ValidationException::withMessages([
                'password' => 'Không thể xóa tài khoản quản trị viên hoạt động cuối cùng.',
            ]);
        }
    }
}

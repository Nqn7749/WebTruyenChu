<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an administrator account interactively';

    public function handle(): int
    {
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );

        $name = $this->ask('Admin name');
        $username = $this->ask('Admin username');
        $email = strtolower((string) $this->ask('Admin email'));
        $password = $this->secret('Admin password');

        $validator = Validator::make(
            compact('name', 'username', 'email', 'password'),
            [
                // Cột users.name là varchar(100): max:255 cho qua rồi MySQL
                // báo 500 (NM-2).
                'name' => ['required', 'string', 'max:100'],
                'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // role_id, status và email_verified_at KHÔNG nằm trong $fillable
        // của User nên User::create() lặng lẽ bỏ qua chúng: lệnh trước đây
        // tạo ra tài khoản thường chưa xác minh email, không phải admin
        // (NM-1). Gán riêng bằng forceFill(), giống RegisteredUserController.
        $user->forceFill([
            'role_id' => $adminRole->id,
            'status' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->info('Administrator account created successfully.');

        return self::SUCCESS;
    }
}

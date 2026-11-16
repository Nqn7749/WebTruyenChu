<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

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
                'name' => ['required', 'string', 'max:255'],
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

        User::create([
            'role_id' => $adminRole->id,
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $this->info('Administrator account created successfully.');

        return self::SUCCESS;
    }
}

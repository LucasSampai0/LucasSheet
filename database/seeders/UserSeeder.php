<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('LUCASSHEET_DEFAULT_USER_EMAIL', 'lucas.bueno@arkus.com.br')],
            [
                'name' => env('LUCASSHEET_DEFAULT_USER_NAME', 'Lucas Bueno'),
                'password' => Hash::make(env('LUCASSHEET_DEFAULT_USER_PASSWORD', '@Rkus142536')),
                'email_verified_at' => now(),
            ],
        );
    }
}

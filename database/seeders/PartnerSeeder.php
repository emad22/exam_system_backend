<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = \App\Models\User::create([
            "username" => "arabacademy",
            'first_name' => 'Arabacademy',
            'last_name' => 'Partner',
            'email' => 'partner@arabacademy.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'partner',
            'is_active' => true,
            'country' => 'Egypt'
        ]);

        \App\Models\Partner::create([
            'user_id' => $user->id,
            'partner_name' => 'Arabacademy',
            'website' => 'https://arabacademy.com',
            'r_date' => now()->toDateString(),
            'note' => 'Seeded data partner for testing'
        ]);
    }
}

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
        \App\Models\Partner::create([
            'partner_name' => 'Arabacademy',
            'fName_contact' => 'Sameh',
            'lName_contact' => 'Ahmed',
            'email' => 'info@arabacademy.com',
            'phone' => '+201116704021',
            'website' => 'https://arabacademy.com',
            'country' => 'Egypt',
            'r_date' => now()->toDateString(),
            'is_active' => 1,
            'note' => 'Seeded data partner for testing'
        ]);
    }
}

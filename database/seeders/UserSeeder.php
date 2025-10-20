<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar usuário administrador
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@sennacar.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Criar usuário de teste
        User::create([
            'name' => 'Usuário Teste',
            'email' => 'teste@sennacar.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Criar alguns usuários aleatórios
        User::factory(10)->create();
    }
}
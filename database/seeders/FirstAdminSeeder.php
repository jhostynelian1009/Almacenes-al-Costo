<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class FirstAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('auth.initial_admin.name'));
        $email = Str::lower(trim((string) config('auth.initial_admin.email')));
        $password = (string) config('auth.initial_admin.password');

        if ($name === '' || $email === '' || $password === '') {
            $this->command?->warn('Administrador inicial omitido: configura INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL e INITIAL_ADMIN_PASSWORD.');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            $this->command?->warn('Administrador inicial omitido: verifica el correo y usa una contraseña de al menos 12 caracteres.');

            return;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            if ($existingUser->role !== UserRole::Admin || ! $existingUser->is_active) {
                throw new RuntimeException(
                    'No se creó el administrador inicial: el correo configurado pertenece a un usuario que no es un administrador activo.'
                );
            }

            $this->command?->info('Administrador inicial activo ya existente; no se realizaron cambios.');

            return;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'is_active' => true,
        ])->save();
    }
}

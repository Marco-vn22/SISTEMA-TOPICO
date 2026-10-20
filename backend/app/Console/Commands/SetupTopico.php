<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('topico:setup')]
#[Description('Crea el coordinador inicial si todavía no existe uno activo')]
class SetupTopico extends Command
{
    public function handle(): int
    {
        if (User::query()->where('role', 'coordinador')->where('active', true)->exists()) {
            $this->info('Ya existe un coordinador activo. No se han modificado las credenciales.');

            return self::SUCCESS;
        }
        if (User::query()->where('email', 'admin@topico.local')->exists()) {
            $this->error('El correo inicial ya está ocupado. Revisa las cuentas existentes antes de continuar.');

            return self::FAILURE;
        }
        $password = Str::password(16, symbols: false);
        User::query()->create(['name' => 'Coordinador de Tópico', 'email' => 'admin@topico.local', 'password' => $password, 'role' => 'coordinador', 'active' => true]);
        $this->info('Coordinador inicial creado.');
        $this->line('Correo: admin@topico.local');
        $this->line('Contraseña temporal: '.$password);
        $this->warn('Cambia la contraseña desde el menú de tu cuenta.');

        return self::SUCCESS;
    }
}

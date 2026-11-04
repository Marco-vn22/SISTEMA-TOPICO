<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('topico:setup {--dni= : DNI de ocho dígitos del coordinador inicial}')]
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
        $dni = (string) $this->option('dni');
        if (! preg_match('/^[0-9]{8}$/', $dni) || User::query()->where('dni', $dni)->exists()) {
            $this->error('Indica un DNI único de ocho dígitos con la opción --dni.');

            return self::FAILURE;
        }
        User::query()->create(['name' => 'Coordinador de Tópico', 'dni' => $dni, 'email' => 'admin@topico.local', 'password' => $dni, 'role' => 'coordinador', 'active' => true, 'uses_initial_password' => true]);
        $this->info('Coordinador inicial creado.');
        $this->line('DNI: '.$dni);
        $this->line('Contraseña inicial: el mismo DNI.');
        $this->info('En el primer ingreso debes cambiar la contraseña antes de utilizar el sistema.');

        return self::SUCCESS;
    }
}

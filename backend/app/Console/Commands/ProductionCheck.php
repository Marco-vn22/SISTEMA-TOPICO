<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('topico:production-check')]
#[Description('Revisa condiciones técnicas de despliegue sin modificar datos ni mostrar credenciales')]
class ProductionCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [
            ['Entorno de producción', app()->environment('production')],
            ['Depuración desactivada', ! config('app.debug')],
            ['Clave de cifrado configurada', filled(config('app.key'))],
            ['URL configurada con HTTPS', str_starts_with((string) config('app.url'), 'https://')],
            ['Configuración optimizada', app()->configurationIsCached()],
            ['Almacenamiento disponible', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'))],
        ];
        try {
            DB::connection()->getPdo();
            $checks[] = ['Conexión a la base de datos', true];
            $migrator = app(Migrator::class);
            $pending = ! $migrator->repositoryExists() || count(array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan())) > 0;
            $checks[] = ['Migraciones aplicadas', ! $pending];
            $checks[] = ['Coordinador activo disponible', User::query()->where('role', 'coordinador')->where('active', true)->exists()];
            $checks[] = ['Cuentas activas con contraseña personal', ! User::query()->where('active', true)->where('uses_initial_password', true)->exists()];
            $connection = DB::connection()->getConfig();
            if (in_array($connection['driver'], ['mysql', 'mariadb', 'pgsql'], true)) {
                $checks[] = ['Usuario de base de datos dedicado', ! in_array($connection['username'] ?? '', ['', 'root', 'postgres'], true)];
            }
        } catch (Throwable) {
            $checks[] = ['Base de datos y estructura accesibles', false];
        }
        $this->table(['Condición', 'Estado'], array_map(fn (array $check): array => [$check[0], $check[1] ? 'OK' : 'PENDIENTE'], $checks));
        $this->warn('Este diagnóstico no certifica seguridad ni funcionamiento. Faltan verificar restauración de copias, servidor HTTPS, permisos del alojamiento y validación con los usuarios.');

        return collect($checks)->every(fn (array $check): bool => $check[1]) ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Leave\WorkflowLock;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateInitialAdmin extends Command
{
    protected $signature = 'cuti:create-admin';

    protected $description = 'Buat administrator sistem awal secara interaktif';

    public function handle(): int
    {
        if (User::where('is_system_admin', true)->exists()) {
            $this->error('Administrator sistem sudah ada.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nama administrator'));
        $nip = trim((string) $this->ask('NIP administrator (18 digit)'));
        $password = (string) $this->secret('Kata sandi (minimal 12 karakter)');
        $confirmation = (string) $this->secret('Ulangi kata sandi');

        if ($name === '' || strlen($name) > 255 || ! preg_match('/^[0-9]{18}$/', $nip)
            || strlen($password) < 12 || $password !== $confirmation || User::where('nip', $nip)->exists()) {
            $this->error('Data tidak valid, NIP sudah digunakan, atau kata sandi tidak cocok.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $nip, $password) {
            WorkflowLock::acquire();
            if (User::where('is_system_admin', true)->exists()) {
                throw new \RuntimeException('Administrator sistem sudah ada.');
            }
            User::create([
                'name' => $name,
                'nip' => $nip,
                'password' => $password,
                'active' => true,
                'is_admin' => true,
                'is_system_admin' => true,
            ]);
        });

        $this->info('Administrator sistem berhasil dibuat.');

        return self::SUCCESS;
    }
}

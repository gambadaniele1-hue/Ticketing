<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanLocalTenants extends Command
{
    /**
     * Il nome del comando da lanciare nel terminale.
     */
    protected $signature = 'tenants:clean';

    /**
     * La descrizione del comando.
     */
    protected $description = 'Pulisce tutti i database fisici e gli utenti MySQL dei tenant (SOLO IN LOCALE)';

    /**
     * Esegue il comando.
     */
    public function handle()
    {
        // 🚨 SCUDO DI SICUREZZA: Evita disastri in produzione!
        if (!app()->isLocal()) {
            $this->error('ERRORE FATALE: Questo comando può essere eseguito solo in ambiente locale (APP_ENV=local)!');
            return;
        }

        $this->warn('Inizio pulizia profonda dei Tenant fantasma...');

        // 1. ELIMINAZIONE UTENTI MYSQL
        $this->info("\nCerco utenti MySQL 'usr_%'...");
        $users = DB::select("SELECT User, Host FROM mysql.user WHERE User LIKE 'usr_%'");

        if (empty($users)) {
            $this->line('Nessun utente trovato.');
        } else {
            foreach ($users as $user) {
                DB::statement("DROP USER '{$user->User}'@'{$user->Host}'");
                $this->line(" - Eliminato utente: <info>{$user->User}@{$user->Host}</info>");
            }
        }

        // 2. ELIMINAZIONE DATABASE FISICI TENANT
        $this->info("\nCerco database fisici 'tenant_%'...");
        $databases = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME LIKE 'tenant_%'");

        if (empty($databases)) {
            $this->line('Nessun database trovato.');
        } else {
            foreach ($databases as $db) {
                DB::statement("DROP DATABASE `{$db->SCHEMA_NAME}`");
                $this->line(" - Eliminato database: <info>{$db->SCHEMA_NAME}</info>");
            }
        }

        $this->newLine();
        $this->info('✨ Pulizia completata con successo! Il tuo server MySQL ora è intonso.');
    }
}
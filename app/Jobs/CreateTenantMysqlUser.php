<?php

namespace App\Jobs;

use App\Models\Global\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTenantMysqlUser
{
    public function __construct(private Tenant $tenant)
    {
    }

    /**
     * La Job Pipeline di Tenancy passa automaticamente l'oggetto Tenant alla funzione handle.
     */
    public function handle(): void
    {
        // 1. Leggiamo le credenziali dal JSON che avremo salvato nel Service
        $dbName = $this->tenant->getInternal('db_name');
        $dbUser = $this->tenant->getInternal('db_username');
        $dbPass = $this->tenant->getInternal('db_password');

        // Se mancano username o password (es. è uno shared plan e non le abbiamo generate), ci fermiamo.
        if (!$dbUser || !$dbPass) {
            return;
        }

        try {
            // 1. IL FIX: Eliminiamo eventuali "fantasmi" di test precedenti 
            // per assicurarci che la password sia sempre quella fresca del JSON
            DB::statement("DROP USER IF EXISTS '{$dbUser}'@'%'");
            DB::statement("DROP USER IF EXISTS '{$dbUser}'@'127.0.0.1'");
            DB::statement("DROP USER IF EXISTS '{$dbUser}'@'localhost'");

            // 2. Creiamo l'utente da zero con la nuova password
            DB::statement("CREATE USER '{$dbUser}'@'%' IDENTIFIED BY '{$dbPass}'");

            // (Opzionale ma super sicuro per l'ambiente locale di Laravel)
            DB::statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}'");
            DB::statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'127.0.0.1' IDENTIFIED BY '{$dbPass}'");

            // 3. Assegniamo i privilegi su tutti gli host
            DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'%'");
            DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'127.0.0.1'");
            DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost'");

            DB::statement("FLUSH PRIVILEGES");

            Log::info("Creato utente MySQL isolato [{$dbUser}] per il tenant: {$this->tenant->id}");

        } catch (\Exception $e) {
            Log::error("Fallita creazione utente MySQL per tenant {$this->tenant->id}: " . $e->getMessage());
            throw $e; // Rilanciamo l'errore così la pipeline si ferma e fa il rollback
        }
    }
}
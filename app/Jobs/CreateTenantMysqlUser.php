<?php

namespace App\Jobs;

use App\Models\Global\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTenantMysqlUser
{
    /**
     * La Job Pipeline di Tenancy passa automaticamente l'oggetto Tenant alla funzione handle.
     */
    public function handle(Tenant $tenant): void
    {
        // 1. Leggiamo le credenziali dal JSON che avremo salvato nel Service
        $dbName = $tenant->getInternal('tenancy_db_name');
        $dbUser = $tenant->getInternal('tenancy_db_username');
        $dbPass = $tenant->getInternal('tenancy_db_password');

        // Se mancano username o password (es. è uno shared plan e non le abbiamo generate), ci fermiamo.
        if (!$dbUser || !$dbPass) {
            return;
        }

        try {
            // 2. Creiamo l'utente e gli diamo i permessi SOLO su quel database
            // (Nota: in produzione al posto di '%' potresti voler usare 'localhost' o l'IP del server web)
            DB::statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'%' IDENTIFIED BY '{$dbPass}'");
            DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'%'");
            DB::statement("FLUSH PRIVILEGES");

            Log::info("Creato utente MySQL isolato [{$dbUser}] per il tenant: {$tenant->id}");

        } catch (\Exception $e) {
            Log::error("Fallita creazione utente MySQL per tenant {$tenant->id}: " . $e->getMessage());
            throw $e; // Rilanciamo l'errore così la pipeline si ferma e fa il rollback
        }
    }
}
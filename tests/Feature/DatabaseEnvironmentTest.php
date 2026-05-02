<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseEnvironmentTest extends TestCase
{
    /**
     * Controlla che Laravel riesca a parlare con il database centrale.
     */
    public function test_central_database_connection_is_working(): void
    {
        try {
            // Proviamo a ottenere l'oggetto PDO (la connessione a basso livello)
            $pdo = DB::connection()->getPdo();
            $this->assertNotNull($pdo, "Impossibile ottenere l'oggetto PDO.");

            // Facciamo una mini query finta per testare che risponda
            $result = DB::select("SELECT 1 AS health_check");
            $this->assertEquals(1, $result[0]->health_check);

        } catch (\Exception $e) {
            $this->fail("Errore di connessione al DB Centrale. Controlla il file .env. Dettaglio: " . $e->getMessage());
        }
    }

    /**
     * Controlla che l'utente MySQL di test abbia il privilegio "CREATE" e "DROP".
     * Questo è FONDAMENTALE per il pacchetto Tenancy! Se non hai questi permessi,
     * il pacchetto non potrà mai creare i tenant dedicati.
     */
    public function test_mysql_user_has_permissions_to_create_and_drop_databases(): void
    {
        $dummyDbName = 'test_dummy_permission_check';

        try {
            // 1. Pulizia preventiva (nel caso un test precedente si sia rotto a metà)
            DB::statement("DROP DATABASE IF EXISTS `{$dummyDbName}`");

            // 2. Testiamo la CREAZIONE (Il muratore può costruire?)
            DB::statement("CREATE DATABASE `{$dummyDbName}`");

            // Verifichiamo che esista davvero
            $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dummyDbName]);
            $this->assertNotEmpty($exists, "Il comando CREATE DATABASE è passato, ma il DB non si trova in INFORMATION_SCHEMA.");

            // 3. Testiamo l'ELIMINAZIONE (Il muratore può demolire?)
            DB::statement("DROP DATABASE `{$dummyDbName}`");

            // Verifichiamo che sia sparito
            $stillExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dummyDbName]);
            $this->assertEmpty($stillExists, "Il comando DROP DATABASE è passato, ma il DB esiste ancora.");

        } catch (\Exception $e) {
            $this->fail("L'utente MySQL (" . env('DB_USERNAME') . ") NON ha i permessi necessari! Deve avere i privilegi CREATE e DROP. Dettaglio: " . $e->getMessage());
        }
    }
}
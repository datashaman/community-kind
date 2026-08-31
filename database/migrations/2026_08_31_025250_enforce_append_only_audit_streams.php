<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_append_only_mutation() RETURNS trigger
                LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'append-only table';
                END;
                $$
            SQL);
        }

        foreach (['tenant_audit_events', 'platform_security_events', 'security_incident_entries'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE OR TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION prevent_append_only_mutation() ");
            } elseif (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$table}_append_only_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
                DB::unprepared("CREATE TRIGGER {$table}_append_only_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            $runtimeRole = config('audit.runtime_database_role');

            if (is_string($runtimeRole) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $runtimeRole) === 1) {
                foreach (['tenant_audit_events', 'platform_security_events', 'security_incident_entries'] as $table) {
                    DB::connection()->getPdo()->exec("REVOKE UPDATE, DELETE, TRUNCATE ON {$table} FROM {$runtimeRole}");
                    DB::connection()->getPdo()->exec("GRANT SELECT, INSERT ON {$table} TO {$runtimeRole}");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $runtimeRole = config('audit.runtime_database_role');

            if (is_string($runtimeRole) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $runtimeRole) === 1) {
                foreach (['tenant_audit_events', 'platform_security_events', 'security_incident_entries'] as $table) {
                    DB::connection()->getPdo()->exec("GRANT UPDATE, DELETE, TRUNCATE ON {$table} TO {$runtimeRole}");
                }
            }
        }

        foreach (['tenant_audit_events', 'platform_security_events', 'security_incident_entries'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
            } elseif (DB::getDriverName() === 'sqlite') {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_append_only_update");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_append_only_delete");
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_append_only_mutation()');
        }
    }
};

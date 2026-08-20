<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Merge the separate "provider" level into "cluster": a Proxmox cluster now
     * carries its own connection configuration directly. The internal provider
     * abstraction remains in code, but is no longer a data/UI level.
     */
    public function up(): void
    {
        Schema::table('infrastructure_clusters', function (Blueprint $table) {
            $table->string('type', 32)->after('name')->default('proxmox');
            $table->string('api_url')->after('type')->nullable();
            $table->string('auth_user')->after('api_url')->nullable();
            $table->text('auth_token')->after('auth_user')->nullable();
            $table->boolean('tls_verify')->after('auth_token')->default(true);
            $table->string('tls_fingerprint')->after('tls_verify')->nullable();
            $table->unsignedInteger('timeout')->after('tls_fingerprint')->default(15);
            $table->string('status', 64)->after('maintenance_mode')->nullable();
            $table->timestamp('last_checked_at')->after('status')->nullable();
        });

        // Drop the provider references; hosts/templates/instances/jobs now
        // reference their cluster directly.
        foreach ([
            'infrastructure_hosts',
            'infrastructure_templates',
            'compute_instances',
            'provisioning_jobs',
            'infrastructure_audit_logs',
        ] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['provider_id']);
                $table->dropColumn('provider_id');
            });
        }

        Schema::table('infrastructure_clusters', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');
        });

        Schema::dropIfExists('infrastructure_providers');
    }

    public function down(): void
    {
        // Not reversible; the provider level is intentionally removed.
    }
};

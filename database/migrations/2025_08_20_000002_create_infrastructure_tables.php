<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('infrastructure_providers', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('type', 32)->default('proxmox');
            $table->string('api_url')->nullable();
            $table->string('auth_user')->nullable();
            $table->text('auth_token')->nullable();
            $table->boolean('tls_verify')->default(true);
            $table->string('tls_fingerprint')->nullable();
            $table->unsignedInteger('timeout')->default(15);
            $table->unsignedInteger('location_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('status', 64)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('infrastructure_clusters', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->unsignedInteger('provider_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('infrastructure_providers')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('infrastructure_hosts', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->unsignedInteger('provider_id');
            $table->unsignedInteger('cluster_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedBigInteger('max_memory')->default(0);
            $table->unsignedBigInteger('max_disk')->default(0);
            $table->unsignedInteger('cpu_cores')->default(0);
            $table->unsignedBigInteger('allocated_memory')->default(0);
            $table->unsignedBigInteger('allocated_disk')->default(0);
            $table->decimal('cpu_utilization', 5, 2)->default(0);
            $table->decimal('memory_utilization', 5, 2)->default(0);
            $table->decimal('disk_utilization', 5, 2)->default(0);
            $table->string('external_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('infrastructure_providers')->onDelete('cascade');
            $table->foreign('cluster_id')->references('id')->on('infrastructure_clusters')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('infrastructure_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->unsignedInteger('provider_id');
            $table->unsignedInteger('cluster_id')->nullable();
            $table->unsignedInteger('template_vmid')->default(0);
            $table->string('storage')->default('local-zfs');
            $table->string('bridge')->default('vmbr0');
            $table->boolean('cloud_init_enabled')->default(true);
            $table->boolean('wings_bootstrap_enabled')->default(true);
            $table->unsignedInteger('default_cpu')->default(2);
            $table->unsignedBigInteger('default_memory')->default(4096);
            $table->unsignedBigInteger('default_disk')->default(40);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('infrastructure_providers')->onDelete('cascade');
            $table->foreign('cluster_id')->references('id')->on('infrastructure_clusters')->onDelete('set null');
        });

        Schema::create('infrastructure_networks', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('bridge')->default('vmbr0');
            $table->unsignedInteger('vlan')->nullable();
            $table->string('gateway')->nullable();
            $table->string('subnet')->nullable();
            $table->string('dns')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('infrastructure_ip_pools', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('network');
            $table->string('subnet')->nullable();
            $table->string('gateway')->nullable();
            $table->string('bridge')->default('vmbr0');
            $table->string('dns')->nullable();
            $table->string('allocation_start')->nullable();
            $table->string('allocation_end')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_ip_pools');
        Schema::dropIfExists('infrastructure_networks');
        Schema::dropIfExists('infrastructure_templates');
        Schema::dropIfExists('infrastructure_hosts');
        Schema::dropIfExists('infrastructure_clusters');
        Schema::dropIfExists('infrastructure_providers');
    }
};

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Host-On specific host settings (managed locally, not synced from Proxmox)
     * and a proper per-instance network model.
     */
    public function up(): void
    {
        Schema::table('infrastructure_hosts', function (Blueprint $table) {
            $table->string('status', 32)->after('external_id')->nullable();
            $table->unsignedInteger('placement_weight')->after('maintenance_mode')->default(100);
            $table->unsignedBigInteger('reserved_memory')->after('allocated_disk')->default(0);
            $table->unsignedBigInteger('reserved_disk')->after('reserved_memory')->default(0);
            $table->json('allowed_product_classes')->after('reserved_disk')->nullable();
            $table->json('tags')->after('allowed_product_classes')->nullable();
            $table->timestamp('last_synced_at')->after('metadata')->nullable();
        });

        Schema::create('compute_instance_networks', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('compute_instance_id');
            $table->string('type', 32)->default('public');
            $table->string('ip', 64)->nullable();
            $table->string('ipv6', 64)->nullable();
            $table->unsignedInteger('vlan')->nullable();
            $table->string('network')->nullable();
            $table->string('gateway', 64)->nullable();
            $table->string('bridge')->nullable();
            $table->unsignedInteger('ip_allocation_id')->nullable();
            $table->timestamps();

            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('cascade');
            $table->foreign('ip_allocation_id')->references('id')->on('infrastructure_ip_allocations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compute_instance_networks');

        Schema::table('infrastructure_hosts', function (Blueprint $table) {
            $table->dropColumn(['status', 'placement_weight', 'reserved_memory', 'reserved_disk', 'allowed_product_classes', 'tags', 'last_synced_at']);
        });
    }
};

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('game_catalog_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('artwork')->nullable();
            $table->unsignedInteger('nest_id')->nullable();
            $table->unsignedInteger('egg_id')->nullable();
            $table->string('default_image')->nullable();
            $table->unsignedBigInteger('min_ram')->default(1024);
            $table->unsignedBigInteger('recommended_ram')->default(4096);
            $table->json('ports')->nullable();
            $table->json('environment')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('nest_id')->references('id')->on('nests')->onDelete('set null');
            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('set null');
        });

        Schema::create('resource_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('game_catalog_id')->nullable();
            $table->unsignedInteger('nest_id')->nullable();
            $table->unsignedInteger('egg_id')->nullable();
            $table->unsignedInteger('cpu')->default(2);
            $table->unsignedBigInteger('memory')->default(4096);
            $table->unsignedBigInteger('disk')->default(40);
            $table->unsignedBigInteger('game_cpu')->default(2);
            $table->unsignedBigInteger('game_memory')->default(2048);
            $table->unsignedBigInteger('game_disk')->default(20);
            $table->unsignedBigInteger('system_reserve')->default(2048);
            $table->json('ports')->nullable();
            $table->unsignedInteger('backups')->default(0);
            $table->string('infrastructure_type', 32)->default('dedicated_vm');
            $table->unsignedInteger('template_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('game_catalog_id')->references('id')->on('game_catalog_entries')->onDelete('set null');
            $table->foreign('nest_id')->references('id')->on('nests')->onDelete('set null');
            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('set null');
            $table->foreign('template_id')->references('id')->on('infrastructure_templates')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('compute_instances', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('provider_id')->nullable();
            $table->unsignedInteger('cluster_id')->nullable();
            $table->unsignedInteger('host_id')->nullable();
            $table->unsignedInteger('template_id')->nullable();
            $table->string('vmid', 32)->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('cpu')->default(2);
            $table->unsignedBigInteger('memory')->default(4096);
            $table->unsignedBigInteger('disk')->default(40);
            $table->string('storage')->nullable();
            $table->string('bridge')->nullable();
            $table->string('management_ip', 64)->nullable();
            $table->string('game_ip', 64)->nullable();
            $table->unsignedInteger('ip_pool_id')->nullable();
            $table->unsignedInteger('wings_node_id')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('provider_id')->references('id')->on('infrastructure_providers')->onDelete('set null');
            $table->foreign('cluster_id')->references('id')->on('infrastructure_clusters')->onDelete('set null');
            $table->foreign('host_id')->references('id')->on('infrastructure_hosts')->onDelete('set null');
            $table->foreign('template_id')->references('id')->on('infrastructure_templates')->onDelete('set null');
            $table->foreign('ip_pool_id')->references('id')->on('infrastructure_ip_pools')->onDelete('set null');
            $table->foreign('wings_node_id')->references('id')->on('nodes')->onDelete('set null');
        });

        Schema::create('game_services', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('external_id', 64)->unique()->nullable();
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->unsignedInteger('compute_instance_id')->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('resource_profile_id')->nullable();
            $table->unsignedInteger('game_catalog_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('set null');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('set null');
            $table->foreign('resource_profile_id')->references('id')->on('resource_profiles')->onDelete('set null');
            $table->foreign('game_catalog_id')->references('id')->on('game_catalog_entries')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });

        Schema::create('provisioning_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('external_id', 64)->unique()->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('game_service_id')->nullable();
            $table->unsignedInteger('resource_profile_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('provider_id')->nullable();
            $table->unsignedInteger('cluster_id')->nullable();
            $table->unsignedInteger('host_id')->nullable();
            $table->unsignedInteger('template_id')->nullable();
            $table->string('vmid', 32)->nullable();
            $table->unsignedInteger('compute_instance_id')->nullable();
            $table->unsignedInteger('wings_node_id')->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('current_step', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('error')->nullable();
            $table->string('rollback_state', 32)->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('game_service_id')->references('id')->on('game_services')->onDelete('set null');
            $table->foreign('resource_profile_id')->references('id')->on('resource_profiles')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('provider_id')->references('id')->on('infrastructure_providers')->onDelete('set null');
            $table->foreign('cluster_id')->references('id')->on('infrastructure_clusters')->onDelete('set null');
            $table->foreign('host_id')->references('id')->on('infrastructure_hosts')->onDelete('set null');
            $table->foreign('template_id')->references('id')->on('infrastructure_templates')->onDelete('set null');
            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('set null');
            $table->foreign('wings_node_id')->references('id')->on('nodes')->onDelete('set null');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('set null');
        });

        Schema::create('provisioning_steps', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('provisioning_job_id');
            $table->string('step', 64);
            $table->string('status', 32)->default('pending');
            $table->text('message')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('provisioning_job_id')->references('id')->on('provisioning_jobs')->onDelete('cascade');
        });

        Schema::create('bootstrap_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('token_hash', 64)->unique();
            $table->unsignedInteger('provisioning_job_id')->nullable();
            $table->unsignedInteger('compute_instance_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            $table->foreign('provisioning_job_id')->references('id')->on('provisioning_jobs')->onDelete('cascade');
            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('set null');
        });

        Schema::create('infrastructure_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('provider_id')->nullable();
            $table->unsignedInteger('cluster_id')->nullable();
            $table->unsignedInteger('host_id')->nullable();
            $table->unsignedInteger('compute_instance_id')->nullable();
            $table->unsignedInteger('node_id')->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->string('operation', 64);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('vmid', 32)->nullable();
            $table->string('result', 32)->default('success');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('provider_id')->references('id')->on('infrastructure_providers')->onDelete('set null');
            $table->foreign('cluster_id')->references('id')->on('infrastructure_clusters')->onDelete('set null');
            $table->foreign('host_id')->references('id')->on('infrastructure_hosts')->onDelete('set null');
            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('set null');
            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('set null');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_audit_logs');
        Schema::dropIfExists('bootstrap_tokens');
        Schema::dropIfExists('provisioning_steps');
        Schema::dropIfExists('provisioning_jobs');
        Schema::dropIfExists('game_services');
        Schema::dropIfExists('compute_instances');
        Schema::dropIfExists('resource_profiles');
        Schema::dropIfExists('game_catalog_entries');
    }
};

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Add Wine/Proton runtime support to the game catalog and introduce the
     * encrypted game license pool abstraction for commercially licensed
     * titles (e.g. Farming Simulator 25).
     */
    public function up(): void
    {
        Schema::table('game_catalog_entries', function (Blueprint $table) {
            $table->string('runtime', 32)->after('slug')->default('linux');
            $table->boolean('requires_license')->after('runtime')->default(false);
            $table->string('license_variable', 64)->after('requires_license')->nullable();
        });

        Schema::create('game_license_pools', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('game_catalog_id')->nullable();
            $table->string('provider', 64)->default('custom');
            $table->string('license_type', 32)->default('dedicated');
            $table->string('license_variable', 64)->default('GAME_LICENSE');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('game_catalog_id')->references('id')->on('game_catalog_entries')->onDelete('set null');
        });

        Schema::create('game_licenses', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('game_license_pool_id');
            $table->text('license_key');
            $table->string('status', 32)->default('available');
            $table->unsignedInteger('game_service_id')->nullable();
            $table->unsignedInteger('compute_instance_id')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('game_license_pool_id')->references('id')->on('game_license_pools')->onDelete('cascade');
            $table->foreign('game_service_id')->references('id')->on('game_services')->onDelete('set null');
            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_licenses');
        Schema::dropIfExists('game_license_pools');

        Schema::table('game_catalog_entries', function (Blueprint $table) {
            $table->dropColumn(['runtime', 'requires_license', 'license_variable']);
        });
    }
};

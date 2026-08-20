<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Track individual IP addresses within an IP pool so we can allocate a
     * dedicated public IP to each customer VM and release it on termination.
     */
    public function up(): void
    {
        Schema::create('infrastructure_ip_allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('ip_pool_id');
            $table->string('address', 64);
            $table->string('status', 32)->default('available');
            $table->unsignedInteger('compute_instance_id')->nullable()->unique();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['ip_pool_id', 'address']);
            $table->foreign('ip_pool_id')->references('id')->on('infrastructure_ip_pools')->onDelete('cascade');
            $table->foreign('compute_instance_id')->references('id')->on('compute_instances')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_ip_allocations');
    }
};

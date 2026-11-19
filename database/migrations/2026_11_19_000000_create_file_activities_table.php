<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_activities', function (Blueprint $table) {
            $table->id();
            $table->string('actor_key', 64);
            $table->string('actor_name');
            $table->string('root_key', 64);
            $table->string('action', 30);
            $table->json('details');
            $table->string('status', 20)->default('processing');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->index(['actor_key', 'root_key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_activities');
    }
};

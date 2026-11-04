<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->double('lat');
            $table->double('lng');
            $table->string('code')->nullable();
            $table->json('metadata');
            $table->json('tags')->nullable();
            $table->json('reports')->nullable();
            $table->timestampTz('occurred_at')->nullable();

            $table->index(['lat', 'lng']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};

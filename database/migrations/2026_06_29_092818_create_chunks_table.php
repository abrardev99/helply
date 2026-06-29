<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::create('chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            // Embeddings are generated in a later phase; allow NULL so a page's text can
            // be stored without one. NOTE: changing this column in place requires a fresh
            // migration (`php artisan migrate:fresh`) in development.
            $table->vector('embedding', 1536)->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX ON chunks USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};

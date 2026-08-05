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
        Schema::table('documents', function (Blueprint $table): void {
            // One document per (agent, URL). The crawl already dedupes through
            // firstOrCreate on canonical URLs, but that is a read-then-write and two
            // concurrent page jobs can interleave. The index makes the invariant the
            // database's responsibility rather than the application's.
            //
            // Postgres treats NULLs as distinct, so PDF documents (no source_url) are
            // unaffected.
            $table->unique(['agent_id', 'source_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique(['agent_id', 'source_url']);
        });
    }
};

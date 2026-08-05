<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chunks', function (Blueprint $table): void {
            // Zero-based order of a chunk within its document, for stable ordering and
            // nicer citations/context assembly during retrieval.
            $table->unsignedInteger('position')->default(0)->after('document_id');
        });
    }
};

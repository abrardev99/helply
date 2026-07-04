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
        Schema::table('chunks', function (Blueprint $table): void {
            // When the chunk's embedding was last generated, for progress and auditing.
            $table->timestamp('embedded_at')->nullable()->after('embedding');
        });
    }
};

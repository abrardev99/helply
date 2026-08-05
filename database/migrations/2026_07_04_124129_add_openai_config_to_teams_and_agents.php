<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Encrypted values are long ciphertext, so store them as text, not string.
        Schema::table('teams', function (Blueprint $table): void {
            $table->text('openai_api_key')->nullable();
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->text('openai_api_key')->nullable();
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->string('chat_model')->default('gpt-5.4');
            $table->text('system_prompt')->nullable();
            $table->float('confidence_threshold')->default(0.75);
        });
    }
};

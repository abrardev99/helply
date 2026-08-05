<?php

use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('source_url')->nullable();
            $table->string('title')->nullable();
            $table->string('status')->default(DocumentStatus::Pending->value)->index();
            $table->timestamps();
        });
    }
};

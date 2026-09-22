<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The row SequenceIssuer locks, so it is never deleted: disabling a type clears is_enabled,
     * and dropping the row would restart numbering at 1 (data-model.md, project_key_type).
     */
    public function up(): void
    {
        Schema::create('project_key_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('key_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('seed_sequence')->default(0);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'key_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_key_type');
    }
};

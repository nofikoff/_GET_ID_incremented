<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('key_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // Exact comparison: a folding collation would treat "café" and "cafe" as one theme.
            $table->string('name_slug')->collation('utf8mb4_bin');
            $table->unsignedInteger('sequence_number');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'key_type_id', 'name_slug']);
            // Also serves the newest-first listing: InnoDB scans it backwards, so a separate DESC index would duplicate it.
            $table->unique(['project_id', 'key_type_id', 'sequence_number']);
        });
    }

    /**
     * Dropping a populated registry would free issued numbers for reissue (constitution, principle II).
     */
    public function down(): void
    {
        if (Schema::hasTable('identifiers') && DB::table('identifiers')->exists()) {
            throw new RuntimeException('Refusing to drop identifiers: the table holds issued numbers.');
        }

        Schema::dropIfExists('identifiers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // Already normalized by ProjectKey, so compared byte for byte; 255 utf8mb4 chars fit the 3072-byte InnoDB key limit.
            $table->string('key')->collation('utf8mb4_bin')->unique();
            $table->string('name');
            $table->string('repo_url', 2048);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

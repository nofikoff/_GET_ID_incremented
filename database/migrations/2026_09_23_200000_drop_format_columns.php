<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The document name is the consumer repository's convention, not the service's (spec 004, ADR-004).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_types', function (Blueprint $table) {
            $table->dropColumn('format_template');
        });

        Schema::table('identifiers', function (Blueprint $table) {
            $table->dropColumn('formatted_id');
        });
    }

    /**
     * Nullable on the way back: the dropped values are gone, and an empty string would pose as a name.
     */
    public function down(): void
    {
        Schema::table('key_types', function (Blueprint $table) {
            $table->string('format_template')->nullable()->after('name');
        });

        Schema::table('identifiers', function (Blueprint $table) {
            $table->string('formatted_id')->nullable()->after('sequence_number');
        });
    }
};

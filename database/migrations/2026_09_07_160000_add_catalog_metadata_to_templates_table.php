<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadados da biblioteca oficial v3.0: o seletor passa a casar por
 * `intent_type` e a marcar o fallback com `is_generic`, em vez de inferir
 * isso só pelo nome do template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('nome');
            $table->text('descricao')->nullable()->after('slug');
            $table->string('intent_type', 40)->nullable()->after('descricao');
            $table->boolean('is_generic')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['slug', 'descricao', 'intent_type', 'is_generic']);
        });
    }
};

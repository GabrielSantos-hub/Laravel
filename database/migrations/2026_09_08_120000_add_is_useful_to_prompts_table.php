<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliação de qualidade do prompt gerado (👍 / 👎).
 *
 * O histórico do GUEASS é a própria tabela `prompts`, então o voto mora aqui:
 * nulo enquanto o usuário não avalia, e um voto por prompt, sobrescrevível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->boolean('is_useful')->nullable()->after('output_text');
            $table->index('is_useful');
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropIndex(['is_useful']);
            $table->dropColumn('is_useful');
        });
    }
};

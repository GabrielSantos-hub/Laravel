<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A arquitetura e a linguagem deixaram de ser obrigatórias na geração: o
 * IntentAnalyzer as deduz do texto do usuário. O histórico precisa aceitar
 * prompts gerados sem essa classificação manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('architecture_id')->nullable()->change();
            $table->unsignedBigInteger('language_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('architecture_id')->nullable(false)->change();
            $table->unsignedBigInteger('language_id')->nullable(false)->change();
        });
    }
};

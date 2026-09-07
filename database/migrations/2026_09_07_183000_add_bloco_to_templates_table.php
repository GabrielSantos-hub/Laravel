<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('bloco', 1)->default('A')->after('intent_type');
        });

        foreach (DB::table('templates')->select('id', 'nome', 'intent_type')->get() as $row) {
            $bloco = 'A';

            if (preg_match('/\(([ABC])\d+\)/u', (string) $row->nome, $matches)) {
                $bloco = $matches[1];
            } elseif ($row->intent_type === 'analysis') {
                $bloco = 'C';
            } elseif (in_array($row->intent_type, ['refactor', 'architecture'], true)
                && str_contains((string) $row->nome, '(B')) {
                $bloco = 'B';
            }

            DB::table('templates')->where('id', $row->id)->update(['bloco' => $bloco]);
        }
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('bloco');
        });
    }
};

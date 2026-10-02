<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico de prompts não deve sumir ao apagar linguagem ou arquitetura.
     * framework_id e template_id já usam nullOnDelete.
     */
    public function up(): void
    {
        $this->recreateForeign('language_id', 'languages');
        $this->recreateForeign('architecture_id', 'architectures');
    }

    public function down(): void
    {
        $this->recreateForeign('language_id', 'languages', cascade: true);
        $this->recreateForeign('architecture_id', 'architectures', cascade: true);
    }

    private function recreateForeign(string $column, string $onTable, bool $cascade = false): void
    {
        Schema::table('prompts', function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });

        Schema::table('prompts', function (Blueprint $table) use ($column, $onTable, $cascade) {
            $reference = $table->foreign($column)->references('id')->on($onTable);

            if ($cascade) {
                $reference->cascadeOnDelete();

                return;
            }

            $reference->nullOnDelete();
        });
    }
};

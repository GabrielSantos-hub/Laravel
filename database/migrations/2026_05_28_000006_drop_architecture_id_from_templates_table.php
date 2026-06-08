<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropArchitectureIdFromTemplatesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('templates') && Schema::hasColumn('templates', 'architecture_id')) {
            Schema::table('templates', function (Blueprint $table) {
       
                try {
                    $table->dropForeign(['architecture_id']);
                } catch (\Throwable $e) {
                
                }

                $table->dropColumn('architecture_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('templates') && ! Schema::hasColumn('templates', 'architecture_id')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->unsignedBigInteger('architecture_id')->nullable()->after('id');
                $table->foreign('architecture_id')
                    ->references('id')
                    ->on('architectures')
                    ->onDelete('cascade');
            });
        }
    }
}

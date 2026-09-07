<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('architecture_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('architecture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['architecture_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('architecture_template');
    }
};

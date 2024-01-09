<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('translatable', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->morphs('translatable');
            $table->string('key', 75);
            $table->string('locale', 5);
            $table->text('text');
            $table->timestamps();
            $table->index(['locale', 'translatable_type', 'translatable_id']);
            $table->unique(['locale', 'key', 'translatable_type', 'translatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translatable');
    }
};

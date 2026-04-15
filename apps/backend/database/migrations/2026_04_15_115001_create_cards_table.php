<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rarity');
            $table->string('type');
            $table->text('text');
            $table->string('image_path')->nullable();
            $table->string('image_mime')->nullable();
            $table->string('scryfall_id')->nullable()->unique();
            $table->string('mana_cost')->nullable();
            $table->decimal('converted_mana_cost', 8, 2)->default(0);
            $table->json('colors')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('type');
            $table->index('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};

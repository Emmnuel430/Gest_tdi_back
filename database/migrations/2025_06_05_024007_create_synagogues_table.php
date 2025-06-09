<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('synagogues', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('localisation');
            $table->string('horaires')->nullable(); // Tu peux aussi en faire un champ texte ou JSON si plus complexe
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synagogues');
    }
};

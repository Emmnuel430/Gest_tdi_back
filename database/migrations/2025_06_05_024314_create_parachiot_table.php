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
        Schema::create('parachiot', function (Blueprint $table) {
            $table->id();
            $table->string('titre');           // Ex: "Bereshit"
            $table->text('resume')->nullable(); // Résumé court
            $table->text('contenu');           // Contenu détaillé ou étude
            $table->date('date_lecture')->nullable(); // Lecture prévue
            $table->string('fichier')->nullable(); // Lien vers PDF ou audio
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parachiot');
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('layouts', function (Blueprint $table) {
            $table->id();

            // Champs pour les affiches
            $table->string('affiche_titre')->nullable();
            $table->string('affiche_image')->nullable(); // chemin de l’image
            $table->string('affiche_lien')->nullable();  // si cliquable

            $table->boolean('actif')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layouts');
    }
};

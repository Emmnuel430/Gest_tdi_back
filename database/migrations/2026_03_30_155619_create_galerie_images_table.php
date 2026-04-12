<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('galerie_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dossier_id')
                ->constrained('galerie_dossiers')
                ->cascadeOnDelete();

            $table->foreignId('media_id')
                ->constrained('media')
                ->cascadeOnDelete();

            $table->string('titre')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('ordre')->default(0);


            $table->timestamps();
            $table->unique(['dossier_id', 'media_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('galerie_images');
    }
};

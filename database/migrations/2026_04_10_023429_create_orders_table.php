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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // 🔐 Paiement
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('XOF');
            $table->string('status')->default('pending'); // pending, paid, failed

            // 👤 Client
            $table->string('nom');
            $table->string('email');
            $table->string('numero')->nullable();

            // 🧠 Type de commande
            $table->string('type'); // cart | prayer | subscription

            // 📦 Données dynamiques
            $table->json('metadata')->nullable();

            // 🏙️ spécifique panier
            $table->string('commune')->nullable();
            $table->integer('total_items')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};

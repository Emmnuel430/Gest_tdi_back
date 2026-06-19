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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // 🔐 Paiement
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('XOF');

            $table->enum('status', ['pending', 'success', 'failed', 'refunded']);

            // 👤 Client
            $table->string('nom')->nullable();
            $table->string('email')->nullable();
            $table->string('numero')->nullable();

            // 🧠 Type global
            $table->string('type');
            // cart | prayer-request | subscription | tsedaka

            // Pour abonnement avec inscrition et mensualités
            $table->string('payment_step')->nullable();
            // registration | monthly | full
            $table->unsignedInteger('payment_index')->nullable();
            // 1,2,3,4,5,6 pour mensualités

            // 🔗 Lien vers modèle (optionnel)
            $table->nullableMorphs('transactionable');
            // order_id / subscription_id / prayer_request_id

            $table->json('metadata')->nullable();
            $table->index(['type', 'status']);

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
        Schema::dropIfExists('transactions');
    }
};

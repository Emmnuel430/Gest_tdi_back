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
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('resource_delivery_status', ['not_applicable', 'pending', 'sent', 'failed'])
                ->default('pending')
                ->after('status');
            $table->string('resource_recipient_email')->nullable()->after('resource_delivery_status');
            $table->timestamp('resource_sent_at')->nullable()->after('resource_recipient_email');
            $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('resource_sent_at');
            $table->text('delivery_error')->nullable()->after('delivery_attempts');

            $table->index(['resource_delivery_status', 'created_at'], 'idx_orders_delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_delivery_status');
            $table->dropColumn([
                'resource_delivery_status',
                'resource_recipient_email',
                'resource_sent_at',
                'delivery_attempts',
                'delivery_error',
            ]);
        });
    }
};

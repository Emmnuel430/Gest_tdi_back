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
        Schema::table('subsections', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('order'); // Date de publication programmée
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subsections', function (Blueprint $table) {
            $table->dropColumn(['publish_at']);
        });
    }
};

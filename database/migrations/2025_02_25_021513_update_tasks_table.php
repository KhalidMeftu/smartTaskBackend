<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('color');
            $table->timestamp('start_date')->nullable(); 
            $table->timestamp('end_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('color')->default('#000000'); 
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};

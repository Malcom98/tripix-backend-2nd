<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLatituteLongitudeCompletedToRouteItem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('route_items',function(Blueprint $table){
            $table->string('latitude');
            $table->string('longitude');
            $table->boolean('completed')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('route_items',function(Blueprint $table){
            $table->dropColumn('latitude');
            $table->dropColumn('longitude');
            $table->dropColumn('completed');
        });
    }
}

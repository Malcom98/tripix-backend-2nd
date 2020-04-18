<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNameAndPhotoReferenceToRouteItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('route_items',function(Blueprint $table){
            $table->string('name')->default("None");
            $table->string('photo_reference')->default("None");
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
            $table->dropColumn('name');
            $table->dropColumn('photo_reference');
        });
    }
}

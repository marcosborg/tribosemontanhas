<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToVehicleExpensesTable extends Migration
{
    public function up()
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('vehicle_item_id')->nullable();
            $table->foreign('vehicle_item_id', 'vehicle_item_fk_10403512')->references('id')->on('vehicle_items');
        });
    }
}

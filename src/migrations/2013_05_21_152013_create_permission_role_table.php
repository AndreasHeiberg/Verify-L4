<?php

use Illuminate\Database\Migrations\Migration;

class CreatePermissionRoleTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('permission_role', function($table)
		{

			$table->integer('permission_id')->unsigned();
			$table->integer('role_id')->unsigned();
			$table->timestamps();

			$table->primary(['permission_id', 'role_id']);
			$table->foreign('permission_id')->references('id')->on('permissions');
			$table->foreign('role_id')->references('id')->on('roles');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('permission_role');
	}

}
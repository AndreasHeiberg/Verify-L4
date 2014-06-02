<?php namespace Andheiberg\Verify\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Config;

class Role extends Eloquent {
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'roles';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = array('name', 'level');

	/**
	 * Users
	 *
	 * @return object
	 */
	public function users()
	{
		return $this->belongsToMany(Config::get('verify::user_model'), 'role_user')->withTimestamps();
	}

	/**
	 * Permissions
	 *
	 * @return object
	 */
	public function permissions()
	{
		return $this->belongsToMany('Andheiberg\Verify\Models\Permission', 'permission_role')->withTimestamps();
	}
}
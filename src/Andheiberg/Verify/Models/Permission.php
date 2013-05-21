<?php namespace Andheiberg\Verify\Models;

class Permission extends BaseModel {
	
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'permissions';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = array('name');

	/**
	 * Roles
	 *
	 * @return object
	 */
	public function roles()
	{
		return $this->belongsToMany('Andheiberg\Verify\Models\Role', 'permission_role')->withTimestamps();
	}
	
}
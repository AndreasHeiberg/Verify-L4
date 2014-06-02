<?php namespace Andheiberg\Verify\Traits;

use Illuminate\Support\Facades\Config;
use Andheiberg\Verify\Models\Role;

trait PermissionTrait {


	/**
	 * Roles
	 *
	 * @return object
	 */
	public function roles()
	{
		return $this->belongsToMany('Andheiberg\Verify\Models\Role', 'role_user')->withTimestamps();
	}

	/**
	 * Is the User a Role
	 *
	 * @param  array|string  $roles A single role or an array of roles
	 * @return boolean
	 */
	public function hasRole($roles)
	{
		$roles = is_array($roles) ?: array($roles);

		$valid = FALSE;
		foreach ($this->roles as $role)
		{
			if (in_array($role->name, $roles))
			{
				$valid = true;
			}
		}

		return $valid;
	}

	/**
	 * Can the User do something
	 *
	 * @param  array|string $permissions Single permission or an array or permissions
	 * @return boolean
	 */
	public function hasPermission($permissions)
	{
		$permissions = is_array($permissions) ?: array($permissions);

		// Are we a super admin?
		foreach ($this->roles as $role)
		{
			if ($role->name === Config::get('verify::super_admin'))
			{
				return true;
			}
		}

		$valid = FALSE;
		foreach ($this->roles as $role)
		{
			foreach ($role->permissions as $permission)
			{
				if (in_array($permission->name, $permissions))
				{
					$valid = true;
					break 2;
				}
			}
		}

		return $valid;
	}

	/**
	 * Is the User a certain Level
	 *
	 * @param  integer $level
	 * @param  string $modifier [description]
	 * @return boolean
	 */
	public function hasLevel($level, $modifier = '>=')
	{
		$max = -1;
		$min = 100;
		$levels = array();

		foreach ($this->roles as $role)
		{
			$max = $role->level > $max
				? $role->level
				: $max;

			$min = $role->level < $min
				? $role->level
				: $min;

			$levels[] = $role->level;
		}

		switch ($modifier)
		{
			case '=':
				return in_array($level, $levels);
				break;

			case '>=':
				return $max >= $level;
				break;

			case '>':
				return $max > $level;
				break;

			case '<=':
				return $min <= $level;
				break;

			case '<':
				return $min < $level;
				break;

			default:
				return false;
				break;
		}
	}

	/**
	 * Remove a role from the user
	 *
	 * @param  array|string $roles Single role or an array or roles
	 * @return boolean
	 */
	public function revokeRole($roles)
	{
		$roles = is_array($roles) ?: array($roles);

		foreach ($roles as $role)
		{
			$this->roles()->whereName($role)->detach();
		}

		return $this;
	}

	/**
	 * Give a role to the user
	 *
	 * @param  array|string $roles Single role or an array or roles
	 * @return boolean
	 */
	public function assignRole($roles)
	{
		$roles = is_array($roles) ?: array($roles);

		foreach ($roles as $role)
		{
			$role = is_numeric($role) ? Role::find($role) : Role::whereName($role)->first();

			if ( ! $role )
			{
				throw new ModelNotFoundException();
			}

			$this->roles()->save($role);
		}
		
		return $this;
	}

}
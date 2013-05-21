<?php namespace Andheiberg\Verify\Models;

class Role extends BaseModel {
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
        return $this->belongsToMany('Toddish\Verify\Models\User', 'role_user')->withTimestamps();
    }

    /**
     * Permissions
     *
     * @return object
     */
    public function permissions()
    {
        return $this->belongsToMany('Toddish\Verify\Models\Permission', 'permission_role')->withTimestamps();
    }
}
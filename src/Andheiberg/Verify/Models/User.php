<?php namespace Andheiberg\Verify\Models;

use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\MessageBag;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Validation\Validator;
use \App;

class User extends Eloquent implements UserInterface {
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'users';

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array(
		'password',
		'password_rest_code',
		'verification_code',
	);

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = array('username', 'password', 'email');

	/**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = array(
		'password_rest_code',
		'verification_code',
	);

	/**
	 * Soft delete
	 *
	 * @var boolean
	 */
	protected $softDelete = true;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var Illuminate\Support\MessageBag
	 */
	public $errors;

	/**
	 * Validation rules.
	 *
	 * @var array
	 */
	protected $rules = [];

	/**
	 * Validator instance
	 * 
	 * @var Illuminate\Validation\Validators
	 */
	protected $validator;

	public function __construct(array $attributes = array(), Validator $validator = null)
	{
		parent::__construct($attributes);

		$this->validator = $validator ?: App::make('validator');
	}

	/**
	 * Fill the model with an array of attributes.
	 *
	 * @param  array  $attributes
	 * @return \Illuminate\Database\Eloquent\Model|static
	 */
	public function fill(array $attributes)
	{
		foreach ($attributes as $key => $value)
		{
			$key = $this->removeTableFromKey($key);
			$value = ! is_array($value) ? $value : implode(', ', $value);

			// The developers may choose to place some attributes in the "fillable"
			// array, which means only those attributes may be set through mass
			// assignment to the model, and all others will just be ignored.
			if ($this->isFillable($key))
			{
				$this->setAttribute($key, $value);
			}
			elseif ($this->totallyGuarded())
			{
				throw new MassAssignmentException($key);
			}
		}

		return $this;
	}

	/**
	 * Listen for save event
	 */
	protected static function boot()
	{
		parent::boot();

		static::saving(function($model)
		{
			return $model->validate();
		});
	}

	/**
	 * Validate the model's attributes.
	 *
	 * @param  array  $rules
	 * @return bool
	 */
	public function validate(array $rules = array())
	{
		$rules = $this->processRules($rules ?: $this->rules);
		$v = $this->validator->make($this->attributes, $rules);

		if ($v->passes())
		{
			return true;
		}

		$this->errors = $v->messages();
		return false;
	}

	/**
	 * Process validation rules.
	 *
	 * @param  array  $rules
	 * @return array  $rules
	 */
	protected function processRules(array $rules)
	{
		$id = $this->getKey();
		array_walk($rules, function(&$item) use ($id)
		{
			$item = stripos($item, ':id:') !== false ? str_ireplace(':id:', $id, $item) : $item;
		});

		return $rules;
	}

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
	 * Salts and saves the password
	 *
	 * @param string $password
	 */
	public function setPasswordAttribute($password)
	{
		$hashed = Hash::make($password);

		$this->attributes['password'] = $hashed;
	}

	/**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
	public function getAuthIdentifier()
	{
		return $this->getKey();
	}

	/**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getAuthPassword()
	{
		return $this->password;
	}

	/**
	 * Find a user by email.
	 *
	 * @param  mixed  $email
	 * @param  array  $columns
	 * @return \Illuminate\Database\Eloquent\Model|Collection|static
	 */
	public static function findByEmail($email, $columns = array('*'))
	{
		$instance = new static;

		if (is_array($email))
		{
			return $instance->newQuery()->whereIn('email', $email)->get($columns);
		}

		return $instance->newQuery()->where('email', $email)->first($columns);
	}

	/**
	 * Register a new user
	 *
	 * @param  array    $credentials All credentials for the user
	 * @param  boolean  $verified    Set to true if you would like to skip verification
	 * @return Andheiberg\Verify\Models\User
	 */
	public static function register($credentials, $verified = false)
	{
		$instance = new static($credentials);
		$instance->verified = $verified;
		$instance->save();

		if ($instance->errors)
		{
			return $instance;
		}

		if ($verified)
		{
			$identifier = Config::get('verify::identifier');

			return self::login([$identifier => $credentials[$identifier], 'password' => $credentials['password']]);
		}
		
		return $instance;
	}

	/**
	 * Login a new user
	 *
	 * @param  array    $credentials The credentials for the user
	 * @return Andheiberg\Verify\Models\User
	 */
	public static function login($credentials)
	{
		$instance = new static;
		try 
		{
			\Auth::attempt($credentials);

			return \Auth::user();
		}
		catch( Exception $e )
		{
			$instance->errors = new MessageBag;

			if ( $e instanceof \Andheiberg\Verify\UserNotFoundException )
			{
				$instance->errors->add('email', 'This email does not exist.');
			}
			if ( $e instanceof \Andheiberg\Verify\UserUnverifiedException )
			{
				$instance->errors->add('email', 'User is unverified');
			}
			if ( $e instanceof \Andheiberg\Verify\UserDisabledException )
			{
				$instance->errors->add('email', 'User is disabled');
			}
			if ( $e instanceof \Andheiberg\Verify\UserDeletedException )
			{
				$instance->errors->add('email', 'User is deleted');
			}
			if ( $e instanceof \Andheiberg\Verify\UserPasswordIncorrectException )
			{
				$instance->errors->add('password', 'User password is incorrect');
			}

			return $instance;
		}
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

	/**
	 * Get an verification code for the given user.
	 *
	 * @return string
	 */
	public function getVerificationCode()
	{
		$this->verification_code = $this->getRandomString();
		$this->save();

		return $this->verification_code;
	}

	/**
	 * Get a password reset code for the given user.
	 *
	 * @return string
	 */
	public function getPasswordResetCode()
	{
		$this->password_rest_code = $this->getRandomString();
		$this->save();

		return $this->password_rest_code;
	}

	/**
	 * Attemps to reset a user's password by matching
	 * the reset code generated with the user's.
	 *
	 * @param  string  $resetCode
	 * @param  string  $newPassword
	 * @return bool
	 */
	public function resetPassword($resetCode, $newPassword)
	{
		if ($this->password_rest_code == $resetCode)
		{
			$this->password = $newPassword;
			$this->password_rest_code = null;
			return $this->save();
		}

		return false;
	}

	/**
	 * Verify the user
	 *
	 * @return object
	 */
	public function verify($verificationCode)
	{
		if ($verificationCode != $this->verification_code)
		{
			return false;
		}
		elseif ($this->verified)
		{
			return $this;
		}

		$this->verification_code = null;
		$this->verified = true;
		$this->save();

		return $this;
	}

	/**
	 * Check if the user is verified.
	 *
	 * @return bool
	 */
	public function isVerified()
	{
		return !! $this->verified;
	}

	/**
	 * Verified scope
	 *
	 * @param  object $query
	 * @return object
	 */
	public function scopeVerified($query)
	{
		return $query->where('verified', '=', 1);
	}

	/**
	 * Unverified scope
	 *
	 * @param  object $query
	 * @return object
	 */
	public function scopeUnverified($query)
	{
		return $query->where('verified', '=', 0);
	}

	/**
	 * Disabled scope
	 *
	 * @param  object $query
	 * @return object
	 */
	public function scopeDisabled($query)
	{
		return $query->where('disabled', '=', 1);
	}

	/**
	 * Enabled scope
	 *
	 * @param  object $query
	 * @return object
	 */
	public function scopeEnabled($query)
	{
		return $query->where('disabled', '=', 0);
	}

	/**
	 * Generate a random string.
	 *
	 * @param  int    $length
	 * @return string
	 */
	public function getRandomString($length = 42)
	{
		// We'll check if the user has OpenSSL installed with PHP. If they do
		// we'll use a better method of getting a random string. Otherwise, we'll
		// fallback to a reasonably reliable method.
		if (function_exists('openssl_random_pseudo_bytes'))
		{
			// We generate twice as many bytes here because we want to ensure we have
			// enough after we base64 encode it to get the length we need because we
			// take out the "/", "+", and "=" characters.
			$bytes = openssl_random_pseudo_bytes($length * 2);

			// We want to stop execution if the key fails because, well, that is bad.
			if ($bytes === false)
			{
				throw new \RuntimeException('Unable to generate random string.');
			}

			return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
		}

		$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
	}

}
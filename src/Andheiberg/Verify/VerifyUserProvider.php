<?php namespace Andheiberg\Verify;

use Illuminate\Hashing\HasherInterface;
use Illuminate\Auth\UserProviderInterface;
use Illuminate\Auth\UserInterface;
use Illuminate\Support\Facades\Config;

class VerifyUserProvider implements UserProviderInterface {
	
	/**
	 * The hasher implementation.
	 *
	 * @var Illuminate\Hashing\HasherInterface
	 */
	protected $hasher;

	/**
	 * The Eloquent user model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Create a new database user provider.
	 *
	 * @param  Illuminate\Hashing\HasherInterface  $hasher
	 * @param  string  $model
	 * @return void
	 */
	public function __construct(HasherInterface $hasher, $model)
	{
		$this->model = $model;
		$this->hasher = $hasher;
	}

	/**
	 * Retrieve a user by their unique identifier.
	 *
	 * @param  mixed  $identifier
	 * @return Illuminate\Auth\UserInterface|null
	 */
	public function retrieveByID($identifier)
	{
		return $this->createModel()->newQuery()->find($identifier);
	}

	/**
	 * Retrieve a user by the given credentials.
	 *
	 * @param  array  $credentials
	 * @return \Illuminate\Auth\UserInterface|null
	 */
	public function retrieveByCredentials(array $credentials)
	{
		// First we will add each credential element to the query as a where clause.
		// Then we can execute the query and, if we found a user, return it in a
		// Eloquent User "model" that will be utilized by the Guard instances.
		$query = $this->createModel()->newQueryWithDeleted();

		foreach ($credentials as $key => $value)
		{
			if ( ! str_contains($key, 'password') ) $query->where($key, $value);
		}

		$user = $query->first();

		if ( ! $user )
		{
			throw new UserNotFoundException('User could not be found');
		}

		return $user;
	}

	/**
	 * Validate a user against the given credentials.
	 *
	 * @param  Illuminate\Auth\UserInterface  $user
	 * @param  array  $credentials
	 * @return bool
	 */
	public function validateCredentials(UserInterface $user, array $credentials)
	{
		$plain = $credentials['password'];
		
		// Is user password is valid?
		if( ! $this->hasher->check($plain, $user->getAuthPassword()) )
		{
			throw new UserPasswordIncorrectException('User password is incorrect');
		}

		// Valid user, but are they verified?
		if ( ! $user->verified)
		{
			throw new UserUnverifiedException('User is unverified');
		}

		// Is the user disabled?
		if ( $user->disabled )
		{
			throw new UserDisabledException('User is disabled');
		}

		// Is the user deleted?
		if ( $user->deleted_at !== NULL )
		{
			throw new UserDeletedException('User is deleted');
		}

		return true;
	}

	/**
	 * Create a new instance of the model.
	 *
	 * @return Illuminate\Database\Eloquent\Model
	 */
	public function createModel()
	{
		$class = '\\'.ltrim($this->model, '\\');

		return new $class;
	}
}

class UserNotFoundException extends \Exception {};
class UserUnverifiedException extends \Exception {};
class UserDisabledException extends \Exception {};
class UserDeletedException extends \Exception {};
class UserPasswordIncorrectException extends \Exception {};
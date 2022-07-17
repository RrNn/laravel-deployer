<?php

namespace App;

use App\Exceptions\HomePathNotFoundException;

class Environment
{
	/**
	 * Get an environment key
	 */
	public static function getKey($key)
	{
		return $_ENV[$key] ?? null;
	}

	/**
	 * Get the repository URL
	 * @return string
	 */
	public static function getRepository()
	{
		return $_ENV['REPOSITORY'] ?? null;
	}

	/**
	 * Get the path to clone the code to
	 * @return string
	 */
	public static function getClonePath()
	{
		$home =
			$_SERVER['HOME'] ??
			($_SERVER['HOMEPATH'] ?? realpath(__DIR__ . DIRECTORY_SEPARATOR . '../..'));

		// ideally this exception should never be thrown
		if (!$home) {
			throw new HomePathNotFoundException(
				'There was an error getting your system home path',
				1
			);
		}

		return $home . '/' . $_ENV['CLONE_PATH'];
	}
}

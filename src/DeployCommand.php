<?php

namespace App;

use App\Log;
use App\Write;
use App\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class DeployCommand extends Command
{
	protected string $clonePath;
	protected string $nginxUser;
	protected string $repositoryUrl;

	protected $write;

	public function __construct()
	{
		parent::__construct();
		$this->higlight = new OutputFormatterStyle('#000', '#00FF00', ['bold']);

		$this->clonePath = Environment::getClonePath();
		$this->nginxUser = Environment::getKey('NGINX_USER');
		$this->repositoryUrl = Environment::getRepository();
	}

	public function configure()
	{
		$this->setName('deploy')
			->setDescription('Deploys an application')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Path to clone the code repo to',
				'Deploying'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->write = new Write($input, $output);

		$this->write->begin();

		$this->preserveAppEnvironment()
			->cloneApp()
			->buildUIAssets()
			->setLaravelFilePathPermissions()
			->composerInstall()
			->recreateAppEnvironment()
			->runMigrations();

		Log::logDeployment($input, $output);

		$this->write->done();

		return Command::SUCCESS;
	}

	/**
	 *	clone the in the ENV or pass an argument to the command repository
	 *	@return $this
	 */
	protected function cloneApp()
	{
		$this->write->clone($this->repositoryUrl, $this->clonePath);

		exec('git clone ' . $this->repositoryUrl . ' ' . $this->clonePath);

		return $this;
	}

	/**
	 *	cd into the cloned app & run `npm install`
	 *	@return $this
	 */
	protected function buildUIAssets()
	{
		$this->write->buildUI($this->repositoryUrl, $this->clonePath);

		exec('cd ' . $this->clonePath . '&& npm install && npm run prod');

		return $this;
	}

	/**
	 *	set the storage & cache permissions to the nginx user
	 *	@return $this
	 */
	protected function setLaravelFilePathPermissions()
	{
		$this->write->setPermissions($this->nginxUser);

		/*
		 *	Change the group ownership of the storage and bootstrap/cache directories to $this->nginxUser
		 *	if the $this->nginxUser group does not exist, this next cmd will error with this non-fatal error
		 *	"chgrp: $this->nginxUser: illegal group name"
		 */
		exec("cd {$this->clonePath} && sudo chgrp -R {$this->nginxUser} storage bootstrap/cache");

		//	Try using just the owner if no group
		exec("cd {$this->clonePath} && sudo chown -R {$this->nginxUser}: storage bootstrap/cache");

		//	recursively grant all permissions, including write and execute, to the group
		exec("cd {$this->clonePath} && sudo chmod -R ug+rwx storage bootstrap/cache");

		return $this;
	}

	/**
	 *	cd into the cloned app & run `composer install`
	 *	@return $this
	 */
	protected function composerInstall()
	{
		$this->write->composerInstall();

		exec(
			"cd {$this->clonePath} && composer update && composer install && composer dump-autoload --optimize"
		);

		return $this;
	}

	/**
	 *	Rus Laravel Database migrations
	 *	@return $this
	 */
	protected function runMigrations()
	{
		$this->write->migrations();

		exec("cd {$this->clonePath} && php artisan migrate", $output, $code);

		$this->write->migrationsConsoleOutput($output, $code);

		return $this;
	}

	protected function preserveAppEnvironment()
	{
		$filepath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '../appenv.txt');

		$laravelExampleEnv = realpath(__DIR__ . DIRECTORY_SEPARATOR . '../laravelDefaultEnv.txt');

		exec("sudo chmod -f 777 {$filepath}", $output, $code);
		//	first default the environment variables the laravel ones
		//	just in case the appenv.txt exixts with previous variables
		copy($laravelExampleEnv, $filepath);

		if (!is_dir($this->clonePath)) {
			return $this;
		}

		exec("cd {$this->clonePath} && cat .env", $currentEnvironmentVariables);

		$resource = fopen($filepath, 'r+');

		foreach ($currentEnvironmentVariables as $environmentVariable) {
			fwrite($resource, $environmentVariable . PHP_EOL);
		}
		//	instead of removing move to another folder with old timestamp
		exec("sudo rm -rf {$this->clonePath}");

		return $this;
	}

	protected function recreateAppEnvironment()
	{
		$env = realpath(__DIR__ . DIRECTORY_SEPARATOR . '../appenv.txt');

		exec("cd {$this->clonePath} && cp {$env} .env");

		return $this;
	}
}

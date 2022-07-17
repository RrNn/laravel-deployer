<?php

namespace App;

use App\Logger;
use App\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class DeployCommand extends Command
{
	protected $higlight; // console output higlight tag
	protected InputInterface $input;
	protected OutputInterface $output;

	protected string $clonePath;
	protected string $nginxUser;
	protected string $repositoryUrl;

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
		// set the $input & $output on the instance
		$this->input = $input;
		$this->output = $output;

		$this->output->writeln('<question>** STARTING DEPLOYMENT PROCESS **</question>');
		$this->preserveAppEnvironment()
			->cloneApp()
			->buildUIAssets()
			->setLaravelFilePathPermissions()
			->composerInstall()
			->recreateAppEnvironment()
			->runMigrations();

		Logger::logDeployment($this->input, $this->output);
		$this->output->writeln('<info>All done. Your app has been deployed!</info>');

		return Command::SUCCESS;
	}

	/**
	 *	clone the in the ENV or pass an argument to the command repository
	 *	@return $this
	 */
	protected function cloneApp()
	{
		$this->output->getFormatter()->setStyle('higlight', $this->higlight);

		$this->output->writeln(
			"<info>Cloning '<higlight>{$this->repositoryUrl}</higlight>' into '<higlight>{$this->clonePath}</higlight>'...</info>"
		);
		exec('git clone ' . $this->repositoryUrl . ' ' . $this->clonePath);
		return $this;
	}

	/**
	 *	cd into the cloned app & run `npm install`
	 *	@return $this
	 */
	protected function buildUIAssets()
	{
		$this->output->writeln(
			'<info>Installing <higlight>npm</higlight> packages & creating a production build...</info>'
		);
		exec('cd ' . $this->clonePath . '&& npm install');
		exec('cd ' . $this->clonePath . '&& npm run prod');
		return $this;
	}

	/**
	 *	set the storage & cache permissions to the nginx user
	 *	@return $this
	 */
	protected function setLaravelFilePathPermissions()
	{
		$this->output->writeln(
			"<info>Setting storage & cache permissions for the <higlight>{$this->nginxUser}</higlight> user...</info>"
		);

		/*
		 *	Change the group ownership of the storage and bootstrap/cache directories to $this->nginxUser
		 *	if the $this->nginxUser group does not exist, this next cmd will error with this non-fatal error
		 *	"chgrp: $this->nginxUser: illegal group name"
		 */
		exec("cd {$this->clonePath} && sudo chgrp -R {$this->nginxUser} storage bootstrap/cache");
		// Try using just the owner if no group
		exec("cd {$this->clonePath} && sudo chown -R {$this->nginxUser}: storage bootstrap/cache");
		// recursively grant all permissions, including write and execute, to the group
		exec("cd {$this->clonePath} && sudo chmod -R ug+rwx storage bootstrap/cache");
		return $this;
	}

	/**
	 *	cd into the cloned app & run `composer install`
	 *	@return $this
	 */
	protected function composerInstall()
	{
		$this->output->writeln('<info>Installing <higlight>composer</higlight> packages...</info>');
		exec("cd {$this->clonePath} && composer update");
		exec("cd {$this->clonePath} && composer install");
		exec("cd {$this->clonePath} && composer dump-autoload --optimize");
		$this->output->writeln('<info>Installing <higlight>composer</higlight> packages...</info>');

		$this->output->writeln('<question> ---- discovered packages ---- </question>');
		exec("cd {$this->clonePath} && php artisan package:discover --ansi", $packages);
		$this->output->writeln($packages);
		$this->output->writeln('<question> ---- discovered packages ---- </question>');

		return $this;
	}

	/**
	 *	Rus Laravel Database migrations
	 *	@return $this
	 */
	protected function runMigrations()
	{
		$this->output->writeln('<info>Running <higlight>php artisan migrate</higlight>...</info>');
		exec("cd {$this->clonePath} && php artisan migrate", $output, $code);
		$this->output->writeln('<info>' . json_encode($output) . '</info>');
		// if code is not 0, there is an exception, back out and reverrt the deploy process
		// move the old code back to the $this->clonePath
		$this->output->writeln('<info> Code: ' . $code . '</info>');
		return $this;
	}

	protected function preserveAppEnvironment()
	{
		$filepath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '../appenv.txt');
		$laravelExampleEnv = realpath(__DIR__ . DIRECTORY_SEPARATOR . '../laravelDefaultEnv.txt');
		exec("sudo chmod -f 777 {$filepath}", $output, $code);
		// first default the environment variables the laravel ones
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
		// instead of removing move to another folder with old timestamp
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

<?php

namespace App;

use App\Environment;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Write
{
	public function __construct(protected InputInterface $input, protected OutputInterface $output)
	{
		$this->input = $input;
		$this->output = $output;

		$this->output
			->getFormatter()
			->setStyle('higlight', new OutputFormatterStyle('#000', '#00FF00', ['bold']));
	}

	public function begin()
	{
		$this->output->writeln('<question>** STARTING DEPLOYMENT PROCESS **</question>');
	}

	public function clone($repositoryUrl, $clonePath)
	{
		$this->output->writeln(
			"<info>Cloning '<higlight>{$repositoryUrl}</higlight>' into '<higlight>{$clonePath}</higlight>'...</info>"
		);
	}

	public function buildUI()
	{
		$this->output->writeln(
			'<info>Installing <higlight>npm</higlight> packages & creating a production build...</info>'
		);
	}
	public function setPermissions($user)
	{
		$this->output->writeln(
			"<info>Setting storage & cache permissions for the <higlight>{$user}</higlight> user...</info>"
		);
	}

	public function composerInstall()
	{
		$this->output->writeln('<info>Installing <higlight>composer</higlight> packages...</info>');
	}

	public function migrations()
	{
		$this->output->writeln('<info>Running <higlight>php artisan migrate</higlight>...</info>');
	}

	public function migrationsConsoleOutput($output, $code)
	{
		$this->output->writeln('<info>' . json_encode($output) . '</info>');
		// if code is not 0, there is an exception, back out and reverrt the deploy process
		// move the old code back to the $this->clonePath
		$this->output->writeln('<info> Code: ' . $code . '</info>');
	}

	public function deploymentTable()
	{
		$this->output->writeln(
			"<info>Creating deployment log in the '<higlight>logs</higlight>' directory with this table...</info>"
		);

		$io = new SymfonyStyle($this->input, $this->output);

		$path = Environment::getClonePath();

		exec("cd {$path} && git log --pretty=oneline -n 1", $commit);

		$io->table(
			['User', 'Time', 'Deployed Commit Details'],
			[[get_current_user(), date('l jS \of F Y h:i:s A'), $commit[0]]]
		);
	}

	public function done()
	{
		$this->output->writeln('<info>All done. Your app has been deployed!</info>');
	}
}

<?php

namespace App;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Logger
{
	public static function logDeployment(InputInterface $input, OutputInterface $output)
	{
		$output->writeln(
			"<info>Creating deployment log in the '<higlight>logs</higlight>' directory with this table...</info>"
		);
		self::showDeploymentTable($input, $output);
	}

	public static function showDeploymentTable($input, $output)
	{
		$io = new SymfonyStyle($input, $output);
		$path = Environment::getClonePath();
		exec("cd {$path} && git log --pretty=oneline -n 1", $commit);

		$io->table(
			['User', 'Time', 'Deployed Commit Details'],
			[[get_current_user(), date('l jS \of F Y h:i:s A'), $commit[0]]]
		);
	}
}

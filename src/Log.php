<?php

namespace App;

use App\Write;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Log
{
	public static function logDeployment(InputInterface $input, OutputInterface $output)
	{
		$write = new Write($input, $output);

		$write->deploymentTable();
	}
}

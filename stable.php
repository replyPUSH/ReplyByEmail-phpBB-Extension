<?php if (php_sapi_name() != "cli") { die('Needs to be run from the command line!'); }
$vers_check = json_decode(file_get_contents(dirname(__FILE__).'/versioncheck.json'), true);
if (isset($vers_check['stable']) && is_array($vers_check['stable']))
{
	sort($vers_check['stable']);
	$current_ver = array_pop($vers_check['stable']);
	if (isset($current_ver['current']))
	{
		exec("git checkout {$current_ver['current']} 2>&1", $output, $code);
		if ($code)
		{
			echo implode("\n", $output)."\n";
		} 
		else
		{
			echo "SUCCESS!\n";
		}
	}
}



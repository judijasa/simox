<?php

declare(strict_types=1);

$arg = $argv[1] ?? null;

[$script, $func_call] = explode(':', $arg, 2);
$func      = substr($func_call, 0, strpos($func_call, '('));
$func_args = rtrim(substr($func_call, strpos($func_call, '(') + 1), ')');

require 'vendor/autoload.php';
require $script;

use Utils\Agent;

$rf = new ReflectionFunction($func);
$attrs = $rf->getAttributes(Agent::class);
if (empty($attrs)) {
    fwrite(STDERR, "Error: '$func' in '$script' is not an #[Agent].\n");
    exit(1);
}
$attrs[0]->newInstance();

printf('%s - Starting %s' . PHP_EOL, date('Y-m-d H:i:s'), $arg);
eval("$func($func_args);");
printf('%s - Finished %s' . PHP_EOL, date('Y-m-d H:i:s'), $arg);


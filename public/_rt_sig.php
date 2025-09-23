<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/vendor/autoload.php';

$rm = new ReflectionMethod(\App\Services\ReviewToken::class, 'create');

echo "isStatic: ".($rm->isStatic()?'yes':'no')."\n";
$i=0;
foreach ($rm->getParameters() as $p) {
  $type = $p->hasType() ? (string)$p->getType() : 'mixed';
  echo sprintf("%d) $%s : %s\n", ++$i, $p->getName(), $type);
}

<?php
echo "argc = " . ($argc ?? 'non défini') . "\n";
echo "argv = ";
var_dump($argv ?? 'non défini');

echo "\n_SERVER['argv'] = ";
var_dump($_SERVER['argv'] ?? 'non défini');

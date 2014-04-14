<?php
require 'vendor/autoload.php';

use Symfony\Component\Console\Application; 
use Command\UpdatePicturesCommand; 

$application = new Application(); 
$application->add(new UpdatePicturesCommand());
$application->run(); 
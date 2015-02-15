#!/usr/bin/php
<?php
/**
 *
 * User: migue
 * Date: 15/02/15
 * Time: 14:13
 */
require_once __DIR__ . '/../vendor/autoload.php';
$app = new \Symfony\Component\Console\Application('MozJpegPhp', '0.1');
$app->add(new \MozJpegPhp\App\OptimizeJpegCommand());
$app->run();

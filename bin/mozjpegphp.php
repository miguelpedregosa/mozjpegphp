#!/usr/bin/php
<?php
/**
 *
 * User: migue
 * Date: 15/02/15
 * Time: 14:13
 */
require_once __DIR__ . '/../vendor/autoload.php';
$app = new \Symfony\Component\Console\Application('MozJpegPhp', '1.0.3');
$app->add(new \MozJpegPhp\App\OptimizeJpegCommand());
$app->add(new \MozJpegPhp\App\ExifInfoCommand());
$app->run();

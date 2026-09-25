<?php
declare(strict_types=1);

require_once __DIR__ . '/Assert.php';
require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/DomInspector.php';
require_once __DIR__ . '/DevServer.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/TestReporter.php';

$projectRoot = dirname(__DIR__, 2);
\CampusFlow\Tests\Harness\Env::load($projectRoot);

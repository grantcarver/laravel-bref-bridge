#!/opt/bin/php
<?php declare(strict_types=1);

use Bref\Runtime\LambdaRuntime;
use Bref\Runtime\PhpFpm;
use STS\AwsEvents\Events\ApiGatewayProxyRequest;
use STS\AwsEvents\Events\Event;

ini_set('display_errors', '1');
error_reporting(E_ALL);

$appRoot = getenv('LAMBDA_TASK_ROOT');

$vendorAutoLoad = $appRoot . '/laravel/vendor/autoload.php';

if (! is_file($vendorAutoLoad)) {
    echo "Composers `$vendorAutoLoad` doesn't exist";
    exit(1);
}

require $vendorAutoLoad;

$lambdaRuntime = LambdaRuntime::fromEnvironmentVariable();

$handler = $appRoot . '/' . getenv('_HANDLER');

if (! is_file($handler)) {
    echo "Handler `$handler` doesn't exist";
    exit(1);
}

$phpFpm = new PhpFpm($handler);

$phpFpm->start();

while (true) {
    $lambdaRuntime->processNextEvent(function ($event) use ($phpFpm): array {
        $event = Event::fromString(json_encode($event));
        if (ApiGatewayProxyRequest::supports($event)) {
            return $phpFpm->proxy($event)->toApiGatewayFormat();
        }
    });

    try {
        $phpFpm->ensureStillRunning();
    } catch (Throwable $e) {
        echo $e->getMessage();
        exit(1);
    }
}

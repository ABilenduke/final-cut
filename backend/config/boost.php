<?php

$appEnv = env('APP_ENV', 'production');
$disabledByDefault = in_array($appEnv, ['local', 'testing'], true);

return [
    'enabled' => env('BOOST_ENABLED', ! $disabledByDefault),

    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', ! $disabledByDefault),
];

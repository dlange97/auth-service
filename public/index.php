<?php

declare(strict_types=1);

use App\Kernel;

// In containerized environments we rely on injected env vars and may not have a local .env file.
if (!is_file(dirname(__DIR__).'/.env')) {
    $_SERVER['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): Kernel {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_stage(3);

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Module not found';

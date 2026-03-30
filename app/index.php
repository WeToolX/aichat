<?php

require_once __DIR__ . '/bootstrap/app.php';

try {
    $request = request();
    Logger::captureRequest($request);

    if (Installer::shouldHandle($request)) {
        Installer::handle($request);
        return;
    }

    Security::inspect($request);

    $router = new Router();
    $router->dispatch($request);
} catch (Throwable $e) {
    ErrorHandler::handleException($e);
}

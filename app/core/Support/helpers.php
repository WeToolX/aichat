<?php

function array_get($array, $key, $default = null)
{
    if (!is_array($array)) {
        return $default;
    }

    return array_key_exists($key, $array) ? $array[$key] : $default;
}

function app($key = null, $default = null)
{
    if (!class_exists('App')) {
        return $default;
    }

    if ($key === null) {
        return App::all();
    }

    return App::get($key, $default);
}

function request()
{
    return App::request();
}

function auth()
{
    return App::auth();
}

function auth_user()
{
    return App::user();
}

function current_user()
{
    return auth_user();
}

function set_current_user($user)
{
    App::setUser($user);
}

function response($success, $data = array(), $message = '', $code = 200)
{
    $httpStatus = is_numeric($code) ? (int) $code : 500;
    if ($httpStatus < 100 || $httpStatus > 599) {
        $httpStatus = 500;
    }

    Response::json(array(
        'success' => $success,
        'code' => $code,
        'data' => $data,
        'message' => $message,
    ), $httpStatus);
}

function writeLog($message)
{
    $file = BASE_PATH . '/log.txt';
    $time = date('Y-m-d H:i:s');
    $content = '[' . $time . '] ' . $message . PHP_EOL;
    file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
}

<?php

function f3()
{
    return \Base::instance();
}

function response()
{
    return new App\Libraries\Response();
}

function env(string $key, $default = null)
{
    return f3()->ENV[$key] ?? $default;
}

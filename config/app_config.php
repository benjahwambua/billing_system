<?php
/**
 * Local application configuration loader.
 * Environment variables take precedence over private local configuration.
 */
$localConfig = __DIR__ . '/local.php';

if (is_file($localConfig)) {
    $values = require $localConfig;
    if (is_array($values)) {
        foreach ($values as $name => $value) {
            if (is_string($name) && $value !== null && getenv($name) === false) {
                putenv($name . '=' . (string)$value);
                $_ENV[$name] = (string)$value;
            }
        }
    }
}

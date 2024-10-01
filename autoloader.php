<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   GPLv3
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(function ($class) {
            $className = str_replace('PrestaShop\\Module\\Doofinder\\', '', $class);
            $file = _PS_MODULE_DIR_ . 'doofinder/' . self::uncapitalize(str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php');
            if (file_exists($file)) {
                require_once $file;

                return true;
            } elseif (file_exists(self::pascalCaseToSnakeCase($file))) {
                $file = self::pascalCaseToSnakeCase($file);
                require_once $file;

                return true;
            }

            return false;
        });
    }

    private static function pascalCaseToSnakeCase($path)
    {
        $parts = explode('/', $path);
        $filename = array_pop($parts);
        // Separate PascalCase words and convert to snake_case without the leading empty element.
        $snakeCase = strtolower(ltrim(implode('_', preg_split('/(?=[A-Z])/', basename($filename, '.php'))), '_'));

        return implode('/', $parts) . '/' . $snakeCase . '.php';
    }

    private static function uncapitalize($text)
    {
        return strtolower($text[0]) . substr($text, 1);
    }
}
Autoloader::register();

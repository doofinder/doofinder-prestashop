<?php
/**
 * Simple autoloader, so we don't need Composer just for this.
 */
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

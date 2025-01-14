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
    /**
     * Registers the autoloader according to PrestaShop standards, which are PSR-12 standards.
     * More info at: https://www.php-fig.org/psr/psr-12/
     *
     * Basically, in order to used this autoloader, the paths have to be camelCased whereas the
     * class names must be PascalCased, not snake_cased. Normally, the module namespaces should
     * have the same structure: Prestashop\Module\Doofinder\My\Custom\PathExample\MyClass where
     * my My\Custom\PathExample is the path from the module root folder, so it would be
     * my/custom/pathExample and inside this folder there should be a class named MyClass.php
     *
     * @return bool
     */
    public static function register()
    {
        return spl_autoload_register(function ($class) {
            $file = sprintf('%1$sdoofinder/%2$s', _PS_MODULE_DIR_, self::pathFromNamespace($class));
            if (file_exists($file)) {
                require_once $file;

                return true;
            }

            return false;
        });
    }

    /**
     * Converts a namespace into a path to load the files.
     *
     * According to PrestaShop's standards, the namespace of the modules
     * should have the following base: Prestashop\Module\NameOfTheModule
     * More info: https://devdocs.prestashop-project.org/8/modules/concepts/composer/
     *
     * @param string $fullNameSpace The class with its full namespace
     *
     * @return string
     */
    private static function pathFromNamespace($fullNameSpace)
    {
        $path = str_replace('PrestaShop\\Module\\Doofinder\\', '', $fullNameSpace);
        $pathParts = explode('\\', $path);
        $className = array_pop($pathParts);
        $pathParts = array_map([__CLASS__, 'uncapitalize'], $pathParts);

        return implode(DIRECTORY_SEPARATOR, $pathParts) . DIRECTORY_SEPARATOR . $className . '.php';
    }

    /**
     * Converts to lowercase only the first letter of a string.
     *
     * @param string $text Text to uncapitalize
     *
     * @return string
     */
    private static function uncapitalize($text)
    {
        // Exceptions based on: https://devdocs.prestashop-project.org/8/modules/creation/module-file-structure/
        $exceptions_for_capitalization = ['Entity', 'Controller'];

        if (in_array($text, $exceptions_for_capitalization, true)) {
            return $text;
        }

        return strtolower(substr($text, 0, 1)) . substr($text, 1);
    }
}
Autoloader::register();

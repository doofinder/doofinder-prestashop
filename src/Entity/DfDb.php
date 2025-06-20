<?php


namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DfDb
{
    /** @var array List of server settings */
    public static $_servers = [];

    /** @var null Flag used to load slave servers only once.
     * See loadSlaveServers() method
     */
    public static $_slave_servers_loaded = null;

    /**
    * Creates a new database instance.
    *
    * This method initializes and returns a new database connection instance, either to the master server
    * or to a slave server based on the parameter. It manages connection pooling and sets up
    * unbuffered queries for PDO connections to handle large datasets efficiently.
    *
    * @param bool $master Whether to connect to the master server (true) or a slave server (false)
    * @return \Db Database instance with active connection
    */
    public static function getNewDbInstance($master = true)
    {
        static $id = 0;

        // This MUST not be declared with the class members because some defines (like _DB_SERVER_) may not exist yet (the constructor can be called directly with params)
        if (!self::$_servers) {
            self::$_servers = [
                ['server' => _DB_SERVER_, 'user' => _DB_USER_, 'password' => _DB_PASSWD_, 'database' => _DB_NAME_], /* MySQL Master server */
            ];
        }

        if (!$master) {
            self::loadSlaveServers();
        }

        $totalServers = count(self::$_servers);
        if ($master || $totalServers == 1) {
            $idServer = 0;
        } else {
            ++$id;
            $idServer = ($totalServers > 2 && ($id % $totalServers) != 0) ? $id % $totalServers : 1;
        }

        $class = \Db::getClass();
        $instance = new $class(
            self::$_servers[$idServer]['server'],
            self::$_servers[$idServer]['user'],
            self::$_servers[$idServer]['password'],
            self::$_servers[$idServer]['database'],
            false
        );
        $link = $instance->connect();
        if ('DbPDO' === $class && method_exists($link, 'setAttribute')) {
            // Set the PDO attribute to not use buffered queries
            // This is needed to avoid memory issues with large datasets

            $link->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }
        return $instance;
    }

    /**
     * Loads configuration settings for slave servers if needed.
     */
    protected static function loadSlaveServers()
    {
        if (self::$_slave_servers_loaded !== null) {
            return;
        }

        // Add here your slave(s) server(s) in this file
        if (file_exists(_PS_ROOT_DIR_ . '/config/db_slave_server.inc.php')) {
            self::$_servers = array_merge(self::$_servers, require (_PS_ROOT_DIR_ . '/config/db_slave_server.inc.php'));
        }

        self::$_slave_servers_loaded = true;
    }

}

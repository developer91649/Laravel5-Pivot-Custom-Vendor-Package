<?php namespace Gitscripts\Maintenance;


use Gitscripts\Maintenance\Databases\MySQLDatabase;
use Gitscripts\Maintenance\Databases\PostgresDatabase;
use Gitscripts\Maintenance\Databases\SqliteDatabase;

class DatabaseBuilder
{
    protected $database;
    protected $console;
    public function __construct()
    {
        $this->console = new Console();
    }
    public function getDatabase(array $realConfig)
    {
        switch ($realConfig['driver'])
        {
            case 'mysql':
                $this->buildMySQL($realConfig);
                break;
            case 'sqlite':
                $this->buildSqlite($realConfig);
                break;
            case 'pgsql':
                $this->buildPostgres($realConfig);
                break;
            default:
                throw new \Exception('Database driver not supported yet');
                break;
        }
        return $this->database;
    }
    protected function buildMySQL(array $config)
    {
        $port = isset($config['port']) ? $config['port'] : 3306;
        $this->database = new MySQLDatabase(
            $this->console,
            $config['database'],
            $config['username'],
            $config['password'],
            $config['host'],
            $port
        );
    }
    protected function buildSqlite(array $config)
    {
        $this->database = new SqliteDatabase(
            $this->console,
            $config['database']
        );
    }
    protected function buildPostgres(array $config)
    {
        $this->database = new PostgresDatabase(
            $this->console,
            $config['database'],
            $config['username'],
            $config['password'],
            $config['host']
        );
    }
}
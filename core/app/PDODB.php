<?php
/**
 * @link http://api.ibos.cn/
 * @copyright Copyright (c) 2018 IBOS Inc
 */

namespace App;

use PDO;


class PDODB
{
    //属性列表
    private $_host;
    private $_port;
    private $_username;
    private $_password;
    private $_charset;
    private $_dbname;

    private $_dsn;
    private $_driver_options;
    private $_pdo;
    //单例的实现
    private static $_instance;//单例对象
    private function __construct($config) {
        //初始化数据库操作
        $this->_initParams($config);//初始化配置参数
        $this->_initDSN();//初始化DSN
        $this->_initDriverOptions();//初始化驱动选项
        $this->_initPDO();//初始化pdo对象
    }
    private function __clone() {

    }
    public static function getInstance($config=[]) {
        if (! static::$_instance instanceof static) {
            static::$_instance = new static($config);
        }
        return static::$_instance;
    }

    //初始化配置参数
    private function _initParams($config) {
        //初始化数据
        $this->_host = isset($config['host']) ? $config['host'] : MYSQL_HOST;
        $this->_port = isset($config['port']) ? $config['port'] : MYSQL_PORT;
        $this->_username = isset($config['username']) ? $config['username'] : MYSQL_DB_USER;
        $this->_password = isset($config['password']) ? $config['password'] : MYSQL_DB_PWD;
        $this->_charset = isset($config['charset']) ? $config['charset'] : 'utf8';
        $this->_dbname = isset($config['dbname']) ? $config['dbname'] : MYSQL_DB_NAME;
    }
    //初始化DSN
    private function _initDSN() {
        $this->_dsn = "mysql:host=$this->_host;port=$this->_port;dbname=$this->_dbname";
    }
    //初始化驱动选项
    private function _initDriverOptions() {
        $this->_driver_options = [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $this->_charset"];
    }
    //初始化pdo对象
    private function _initPDO() {
        $this->_pdo = new PDO($this->_dsn, $this->_username, $this->_password, $this->_driver_options);
    }

    //执行SQL语句
    public function query($sql) {
        if(! $result = $this->_pdo->query($sql))
        {
            $error_info = $this->_pdo->errorInfo();
            echo "<br />执行失败。";
            echo "<br />失败的sql语句为：" . $sql;
            echo "<br />出错信息为：" . $error_info[2];
            echo "<br />错误代号为：" . $error_info[1];
            die;
        }
        return $result;
    }

    //获取全部数据
    public function getAll($sql) {
        //执行
        $result = $this->query($sql);
        //获取数据，关联
        $list = $result->fetchAll(PDO::FETCH_ASSOC);
        //关闭光标（释放）
        $result->closeCursor();
        return $list;
    }

    //获取一行数据
    public function getRow($sql) {
        $result = $this->query($sql);
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $result->closeCursor();
        return $row;
    }

    //获取一个数据
    public function getOne($sql) {
        $result = $this->query($sql);
        $string = $result->fetchColumn();
        $result->closeCursor();
        return $string;
    }

    public function escapeString($data) {
        return $this->_pdo->quote($data);
    }
}
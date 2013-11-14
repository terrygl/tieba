<?php
defined('SS_LIBS_CORE_TOKEN') or die('core_miss');

/**
 * 基础类库
 * @author qianzhong
 */
/**
 * 基础缓存类接口
 *
 */
abstract class ACache
{

    /**
     * 获取缓存的数据
     * @param     string $key
     * @return    mixed
     */
    public function get ($key)
    {
    }

    /**
     * 设置缓存
     * @param     string $key
     * @param     mixed  $value
     * @param     int    $expire_time
     * @return    bool
     */
    public function set ($key, $value, $expire_time = 0)
    {
    }

    /**
     * 清空缓存
     * @return    bool
     */
    public function clear ()
    {
    }

    /**
     * 删除一个缓存
     * @param     string $key
     * @return    bool
     */
    public function delete ($key)
    {
    }
}

/**
 * memcache类
 */
class SMemCache extends ACache
{

    /**
     * memcache类
     *
     * @var Memcache
     */
    protected $mem = "";

    /**
     * 是否使用zip添加数据
     *
     * @var boolean
     */
    public $zip = true;

    /**
     * 数据过期时间秒数,默认30分钟
     *
     * @var int
     */
    public $_expireTime = 1800; //

    /**
     * CONSTRUCT
     *
     */
    function __construct ($s_memcached_servers)
    {
        if (! class_exists('memcache') || empty($s_memcached_servers))
        {
            return false;
        }
        else
        {
            $this->mem = new Memcache();
            $memServerList = explode(" ", $s_memcached_servers);
            $this->addServerGroup($memServerList);
        }
    }

    function __destruct ()
    {
        $this->mem->close();
    }

    /**
     * 添加memcache主机组
     *
     * @param $serverGroup  memcache主机数组
     */
    public function addServerGroup ($serverGroup)
    {
        for ($i = 0; $i < count($serverGroup); $i ++)
        {
            $h = explode(":", $serverGroup[$i]);
            $this->addServer($h[0], $h[1]);
        }
    }

    /**
     * 添加memcache主机
     *
     * @param $host memcache主机IP
     * @param $port memcache主机端口
     */
    public function addServer ($host, $port)
    {
        if (is_object($this->mem))
        {
            return $this->mem->addServer($host, $port, false, 1, 2);
        }
    }

    /**
     * 从memcache得到值
     * @param $key  memcache里使用的key
     * @return array    返回一个数组
     */
    public function get ($key)
    {
        if (! is_object($this->mem))
        {
            return false;
        }
        else
        {
            if ($this->zip)
            {
                return $this->mem->get($key);
            }
            else
            {
                return unserialize($this->mem->get($key));
            }
        }
    }

    /**
     * 向memcache加值
     *
     * @param $key  memcache里使用的key
     * @param $data 写入memcache里的值，一个数组
     */
    public function set ($key, $data, $expire_time = '')
    {
        if (! is_object($this->mem))
        {
            return false;
        }
        if (empty($expire_time))
        {
            $eTime = $this->_expireTime;
        }
        else
            if (is_numeric($expire_time))
            {
                if ($expire_time < 351043200) // 用的是时间戳，如 strtotime("2010-08-08 12:12:21")
                {
                    $eTime = $expire_time;
                }
                else
                {
                    $eTime = $expire_time * 60; //转成秒数
                }
            }
            else
            {
                $eTime = strtotime($expire_time); // 用的是时间格式，如"2010-08-08 12:12:21"
            }
        return $this->mem->set($key, serialize($data), $this->zip, $eTime);
    }

    /**
     * 删除相应key
     *
     * @param $key  要操作的key
     */
    public function delete ($key)
    {
        if (! is_object($this->mem))
        {
            return false;
        }
        return $this->mem->delete($key);
    }

    /**
     * 清空缓存
     *
     * @return 清空状态
     */
    public function clear ()
    {
        if (! is_object($this->mem))
        {
            return false;
        }
        return $this->mem->flush();
    }

    /**
     * 获取版本信息
     */
    public function getVersion ()
    {
        if (! is_object($this->mem))
        {
            return false;
        }
        return $this->mem->getVersion();
    }

    /**
     * getExtendedStats
     *
     * @return 返回结果
     */
    public function getExtendedStats ()
    {
        if (! is_object($this->mem))
        {
            return false;
        }
        return $this->mem->getExtendedStats();
    }
}

/**
 * 普通PHP文件缓存
 */
class SFileCache extends ACache
{

    /* 缓存目录 */
    protected $_cache_dir = './';

    function set ($key, $value, $expire_time = 0)
    {
        if (! $key)
        {
            return false;
        }
        $cache_file = $this->_getCachePath($key);
        $cache_data = "<?php\r\n/**\r\n * @Time:" . date('Y-m-d H:i:s') . "\r\n */";
        $cache_data .= $this->_getExpireCondition(intval($expire_time));
        $cache_data .= "\r\nreturn " . var_export($value, true) . ";\r\n";
        $cache_data .= "\r\n?>";
        return @file_put_contents($cache_file, $cache_data, LOCK_EX);
    }

    public function get ($key)
    {
        $cache_file = $this->_getCachePath($key);
    
        if (! is_file($cache_file))
        {
            return false;
        }
        $data = include ($cache_file);
     
        return $data;
    }

    public function clear ()
    {
        $dir = dir($this->_cache_dir);
        while (false !== ($item = $dir->read()))
        {
            if ($item == '.' || $item == '..' || substr($item, 0, 1) == '.')
            {
                continue;
            }
            $item_path = $this->_cache_dir . '/' . $item;
            if (is_dir($item_path))
            {
                SFile::rmDir($item_path);
            }
            else
            {
                @unlink($item_path);
            }
        }
        return true;
    }

    public function delete ($key)
    {
        $cache_file = $this->_getCachePath($key);
        return @unlink($key);
    }

    public function setCacheDir ($path)
    {
        $this->_cache_dir = $path;
    }

    public function getCacheDir ()
    {
        return $this->_cache_dir;
    }

    protected function _getExpireCondition ($ttl = 0)
    {
        if (! $ttl)
        {
            return '';
        }
        return "\r\n\r\n" . 'if(filemtime(__FILE__) + ' . $ttl . ' < NOW)return false;' . "\r\n";
    }

    protected function _getCachePath ($key)
    {
        $dir = str_pad(abs(crc32($key)) % SS_CACHE_DIR_NUM, 4, '0', STR_PAD_LEFT);
        SFile::mkDir($this->_cache_dir . '/' . $dir);
        return $this->_cache_dir . '/' . $dir . '/' . $this->_getFileName($key);
    }

    protected function _getFileName ($key)
    {
        return md5($key) . '.cache.php';
    }
}

/**
 * cache类,提供统一缓存封装
 *

 *
 */
final class SCache
{

    private static $_fileInstance;

    private static $_memInstance;

    /**
     * 创建缓存key，每个从的都不一样
     * @param string $extra_string
     */
    public static function createKey ($extra_string = '')
    {
        if (! is_string($extra_string))
        {
            $extra_string = strval($extra_string);
        }
        $trace = debug_backtrace();
        $file = $trace[1]['file'];
        $class = $trace[1]['class'];
        $function = $trace[1]['function'];
        return md5("{$file}::{$class}::{$function}::{$extra_string}");
    }

    /**
     * 获取PhpCacheServer的单一实例。
     *
     * @return SFileCache
     */
    static public function getFileInstance ()
    {
        if (null === self::$_fileInstance)
        {
            $cs = new SFileCache();
            $cs->setCacheDir(SS_DATA_PATH . '/caches');
            self::$_fileInstance = $cs;
        }
        return self::$_fileInstance;
    }

    /**
     * 获取MemcacheServer的单一实例。
     *
     * @return SMemCache
     */
    static public function getMemInstance ()
    {
        if (null === self::$_memInstance)
        {
            $cs = new SMemCache(SS_MC_CONF);
            self::$_memInstance = $cs;
        }
        return self::$_memInstance;
    }

    /**
     * 获取MemcacheServer的单一实例。
     *
     * @return MemcacheServer
     */
    static public function getInstance ()
    {
        if (class_exists('memcache') && defined('SS_CACHE_ENGINE') && SS_CACHE_ENGINE == 'MEM')
        {
            return self::getMemInstance();
        }
        else
        {
            return self::getFileInstance();
        }
    }
}

/**
 * 数据模型的接口,因为数据层包含了从数据库、api等位置的数据，这个api主要起强调作用

 */
abstract class SData
{

    /**
     * 获取数据的标志
     * @var int
     */
    const FETCH_ONE = 1;

    const FETCH_AMOUNT = 2;

    const FETCH_PAGER = 3;

    const FETCH_AMOUNT_PAGER = 4;

    const FETCH_ALL = 5;

    /**
     * 插入标志
     * @var int
     */
    const INSERT_TYPE_NORMAL = 0;

    const INSERT_TYPE_REPLACE = 1;

    const INSERT_TYPE_IGNORE = 2;

    /**
     * 本驱动所需的数据库实例
     *
     * @var PDO
     */
    private static $_dao;

    /**
     * 具体的数据名
     *
     * @var string
     */
    protected $_table;

    /**
     * 单一主键
     *
     * @var string
     */
    protected $_pk;

    /**
     * 是否已经添加后缀
     *
     * @var string
     */
    protected $_table_suffix_seted;

    /**
     * 具体的数据字段
     *
     * @var string
     */
    protected $_field_date_type;

    /**
     * 临时查询数据
     *
     * @var PDOStatement
     */
    private $_statement;

    /**
     * 事务启动标志
     *
     * @var boolean
     */
    private $_flagTrStart = false;

    public function __construct ()
    {
        $this->_defineFieldDataType();
    }

    /**
     * 获取表的名称
     *
     * @return string
     */
    public final function getTableName ()
    {
        return $this->_table;
    }

    /**
     * 获取表的单一主键
     *
     * @return string
     */
    public final function getPrimaryKey ()
    {
        return $this->_pk;
    }

    public final function appendTableSuffix ($suffix, $force_append = false, $d = "_")
    {
        if (null != $this->_table)
        {
            if (true != $this->_table_suffix_seted)
            {
                $this->_table = $this->_table . $d . trim($suffix);
                $this->_table_suffix_seted = true;
            }
            else
            {
                if ($force_append)
                {
                    $l = strlen($suffix) + strlen($d);
                    $t = substr($this->_table, 0, - $l);
                    $this->_table = $t . $d . trim($suffix);
                }
            }
        }
    }

    /**
     * 获取字段的类型
     *
     * @return string
     */
    protected function _getFieldDataType ($key)
    {
        $a_type = $this->_field_date_type;
        if (isset($a_type[$key]))
        {
            return $a_type[$key];
        }
        else
        {
            throw new Exception('field_not_defined:' . $key, 0x00001006);
        }
    }

    /**
     * 定义数据的类型
     */
    abstract protected function _defineFieldDataType ();

    /**
     * 只支持单调插入插入
     *
     * @param int $parent_id
     * @param string $name
     * @param string $description
     * @param int $status
     * @param datetime $create_time
     */
    public function insert ($a_data, $insert_type = self::INSERT_TYPE_NORMAL)
    {
        try
        {
            switch ($insert_type)
            {
                case self::INSERT_TYPE_IGNORE:
                    $method = 'INSERT IGNORE';
                    break;
                case self::INSERT_TYPE_REPLACE:
                    $method = 'REPLACE';
                    break;
                case self::INSERT_TYPE_NORMAL:
                default:
                    $method = 'INSERT';
                    break;
            }
            $a_keys = array_keys($a_data);
            $fields = '(' . implode(', ', $a_keys) . ')';
            $values = '(:' . implode(', :', $a_keys) . ')';
            $s_sql = "{$method} INTO {$this->_table} {$fields} VALUES {$values}";
            $stmt = $this->_getDao()
                ->prepare($s_sql);
            //SLog::getInst()->dump($s_sql);
            foreach ($a_data as $key => &$value)
            {
                if (SS_DATA_TYPE_INT == $this->_getFieldDataType($key))
                {
                    $stmt->bindParam(":" . $key, $value, PDO::PARAM_INT);
                }
                else
                {
                    $stmt->bindParam(":" . $key, $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $stmt = null;
            return $this->_getDao()
                ->lastInsertId();
        }
        catch (PDOException $e)
        {
            $msg = "sql: " . $s_sql . " ### " . $e->getMessage();
            throw new SException($msg, 0x00001001);
        }
    }

    public function replace ($a_data)
    {
        return $this->insert($a_data, self::INSERT_TYPE_REPLACE);
    }

    /**
     * 获取最新插入的id
     * int
     */
    public final function getLastInsertId ()
    {
        return $this->_getDao()
            ->lastInsertId();
    }

    /**
     * 获取修改的行数
     * int
     */
    public final function getAffectedRows ()
    {
        return $this->_statement->rowCount();
    }

    /**
     * 只支持单调插入插入
     *
     * @param int $parent_id
     * @param string $name
     * @param string $description
     * @param int $status
     * @param datetime $create_time
     */
    public function update ($s_condition, $a_data, $limit = 1)
    {
        try
        {
            //
            $a_keys = array_keys($a_data);
            $a_sets = array();
            foreach ($a_keys as $_k)
            {
                $a_sets[] = "`{$_k}`=:{$_k}";
            }
            if (empty($a_sets))
            {
                $msg = 'nothing to update';
                throw new SException($msg, 0x00001006);
            }
            $s_sets = implode(',', $a_sets);
            $s_sql = "UPDATE {$this->_table} SET {$s_sets} WHERE {$s_condition}";
            $limit = intval($limit);
            if (0 != $limit)
            {
                $s_sql .= " LIMIT {$limit}";
            }
            $stmt = $this->_getDao()
                ->prepare($s_sql);
            print_r($s_sql);
            foreach ($a_data as $key => &$value)
            {
                $key2 = ":{$key}";
                if (SS_DATA_TYPE_INT == $this->_getFieldDataType($key))
                {
                    $stmt->bindParam($key2, $value, PDO::PARAM_INT);
                }
                else
                {
                    $stmt->bindParam($key2, $value, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            //var_dump($stmt->rowCount ());
            //die();
            return $stmt->rowCount();
        }
        catch (PDOException $e)
        {
            $msg = "sql: " . $s_sql . " ### " . $e->getMessage();
            throw new SException($msg, 0x00001002);
        }
    }

    /**
     * 按照条件查询
     * @param array $s_condition
     * @param int $fetch_type
     * @throws SException
     */
    final public function find ($a_condition, $fetch_type = self::FETCH_ONE)
    {
        try
        {
            $a_base = array(
                'fields' => '*' ,
                'where' => '1' ,
                'group_key' => '' ,
                'order' => '' ,
                'limit' => '1' ,
                'index_key' => ''
            );
            $a_find = array_merge($a_base, $a_condition);
            $i_total = 0;
            $a_data = array();
            $s_condition = $a_find['where'];
            if (empty($s_condition))
            {
                $s_condition = '1';
            }
            //其他的都要查询
            $s_fields = $a_find['fields'];
            //单Key
            $s_group_by = '';
            $s_group_key = $a_find['group_key'];
            if (! empty($s_group_key) && $this->_getFieldDataType($s_group_key))
            {
                $s_group_by = " GROUP BY `{$s_group_key}`";
            }
            //限制
            //不验证
            $s_order_by = '';
            $s_order = $a_find['order'];
            if (! empty($s_order))
            {
                $s_order_by = " ORDER BY {$s_order}";
            }
            //只去一个数据
            $s_sql = "SELECT {$s_fields} FROM {$this->_table} WHERE {$s_condition} {$s_group_by} {$s_order_by}";
  
            if ($fetch_type == self::FETCH_ONE)
            {
                $s_sql .= " LIMIT 1";
                //echo $s_sql;
                return $this->query($s_sql)
                    ->fetchOne();
            }
            //其他的都要查询总数先获取总数
            if ($fetch_type == self::FETCH_AMOUNT || $fetch_type == self::FETCH_AMOUNT_PAGER)
            {
                $s_sql_c = "SELECT count(*) AS i_total  FROM  {$this->_table} WHERE {$s_condition} {$s_group_by}";
                $result = $this->query($s_sql_c)
                    ->fetchOne();
                $i_total = $result['i_total'];
                if ($fetch_type == self::FETCH_AMOUNT)
                {
                    return $i_total;
                }
            }
            if ($fetch_type == self::FETCH_AMOUNT_PAGER && $i_total == 0)
            {
                return array(
                    $i_total ,
                    $a_data
                );
            }
            $s_limit = $a_find['limit'];
            if (! empty($s_limit) && $fetch_type != self::FETCH_ALL)
            {
                $s_sql .= " LIMIT {$s_limit}";
            }
            $a_result = $this->query($s_sql)
                ->fetchAll();
            $s_index_key = $a_find['index_key'];
            if (! empty($s_index_key) && $this->_getFieldDataType($s_index_key))
            {
                foreach ($a_result as $value)
                {
                    $a_data[$value[$s_index_key]] = $value;
                }
            }
            else
            {
                $a_data = $a_result;
            }
            if ($fetch_type == self::FETCH_AMOUNT_PAGER)
            {
                return array(
                    $i_total ,
                    $a_data
                );
            }
            return $a_data;
        }
        catch (PDOException $e)
        {
            $msg = "sql: " . $s_sql . " ### " . $e->getMessage();
            throw new SException($msg, 0x00001004);
        }
    }

    /**
     * 删除数据
     * @param string $s_condition
     * @param int $limit
     * @throws SException
     */
    final public function drop ($s_condition, $limit = 1)
    {
        try
        {
            if (empty($s_condition))
            {
                $msg = 'nothing to update';
                throw new SException($msg, 0x00001008);
            }
            $s_sql = "DELETE FROM {$this->_table} WHERE {$s_condition}";
            $limit = intval($limit);
            if (0 != $limit)
            {
                $s_sql .= " LIMIT {$limit}";
            }
            $stmt = $this->_getDao()
                ->prepare($s_sql);
            $stmt->execute();
            return $stmt->rowCount();
        }
        catch (PDOException $e)
        {
            $msg = "sql: " . $s_sql . " ### " . $e->getMessage();
            throw new SException($msg, 0x00001003);
        }
    }

    /**
     * 一般的查询，需要配合fetch获取
     */
    final public function query ($s_sql)
    {
        try
        {
            $this->_statement = $this->_getDao()
                ->query($s_sql);
            return $this;
        }
        catch (PDOException $e)
        {
            $msg = "sql: " . $s_sql . " ### " . $e->getMessage();
            throw new SException($msg, 0x00001004);
        }
    }

    /**
     * 一般的查询，需要配合fetch获取
     */
    final public function execute ($s_sql)
    {
        try
        {
            $stmt = $this->_getDao()
                ->prepare($s_sql);
            return $stmt->execute();
        }
        catch (PDOException $e)
        {
            $msg = "sql: " . $s_sql . " ### " . $e->getMessage();
            throw new SException($msg, 0x00001004);
        }
    }

    /**
     * 获取数据一个
     *
     * @return array
     */
    final public function fetchOne ()
    {
        try
        {
            $a_result = $this->_statement->fetchAll(PDO::FETCH_ASSOC);
            $this->_statement = null;
            return isset($a_result[0]) ? $a_result[0] : array();
        }
        catch (PDOException $e)
        {
            $msg = $e->getMessage();
            throw new SException($msg, 0x00001004);
        }
    }

    /**
     * 获取数据全部
     *
     * @return array
     */
    final public function fetchAll ()
    {
        try
        {
            $a_result = $this->_statement->fetchAll(PDO::FETCH_ASSOC);
            $this->_statement = null;
            return $a_result;
        }
        catch (PDOException $e)
        {
            $msg = $e->getMessage();
            throw new SException($msg, 0x00001004);
        }
    }

    final public function makeSql_Equal ($field, $value = null, $use_table = false)
    {
        $p = ($use_table) ? $this->_table . "." : '';
        if (is_array($field) && 0 < count($field))
        {
            $a_temp = array();
            foreach ($field as $key => $value)
            {
                $field_type = $this->_getFieldDataType($key);
                if ($field_type == SS_DATA_TYPE_INT)
                {
                    $value = intval($value);
                    $a_temp[] = "(`{$p}{$key}` = {$value} )";
                }
                else
                {
                    //TODO bad
                    $value = self::escape($value);
                    $a_temp[] = "(`{$p}{$key}`  = '{$value}' )";
                }
            }
            return implode(' AND ', $a_temp);
        }
        else
        {
            $field_type = $this->_getFieldDataType($field);
            if ($field_type == SS_DATA_TYPE_INT)
            {
                $value = intval($value);
                return " {$p}`{$field}` = {$value} ";
            }
            else
            {
                //TODO bad
                $value = self::escape($value);
                return " {$p}`{$field}`  = '{$value}' ";
            }
        }
        return '';
    }

    final public function makeSql_Like ($field, $value, $use_table = false)
    {
        if (! empty($value))
        {
            $field_type = $this->_getFieldDataType($field);
            $p = ($use_table) ? $this->_table . "." : '';
            $value = self::escape($value);
            return "{$p}`{$field}` like '%{$value}%' ";
        }
        return '';
    }

    final public function makeSql_Gt ($field, $value, $use_table = false)
    {
        $p = ($use_table) ? $this->_table . "." : '';
        $value = self::escape($value);
        return "{$p}`{$field}` > '{$value}' ";
    }

    final public function makeSql_Gte ($field, $value, $use_table = false)
    {
        $p = ($use_table) ? $this->_table . "." : '';
        $value = self::escape($value);
        return "{$p}`{$field}` >= '{$value}' ";
    }

    final public function makeSql_Lt ($field, $value, $use_table = false)
    {
        $p = ($use_table) ? $this->_table . "." : '';
        $value = self::escape($value);
        return "{$p}`{$field}` < '{$value}' ";
    }

    final public function makeSql_Lte ($field, $value, $use_table = false)
    {
        $p = ($use_table) ? $this->_table . "." : '';
        $value = self::escape($value);
        return "{$p}`{$field}` <= '{$value}' ";
    }

    final public function makeSql_Between ($field, $value1, $value2, $use_table = false)
    {
        $field_type = $this->_getFieldDataType($field);
        $p = ($use_table) ? $this->_table . "." : '';
        $value1 = self::escape($value1);
        $value2 = self::escape($value2);
        return "({$p}`{$field}` BETWEEN '{$value1}' AND '{$value2}') ";
    }

    final public function makeSql_In ($field, $a_values, $use_table = false)
    {
        if (is_array($a_values) && 0 < count($a_values))
        {
            $field_type = $this->_getFieldDataType($field);
            $p = ($use_table) ? $this->_table . "." : '';
            if ($field_type == SS_DATA_TYPE_INT)
            {
                return "{$p}`{$field}` IN (" . implode(",", $a_values) . ")";
            }
            else
            {
                return "{$p}`{$field}` IN ('" . implode("','", $a_values) . "')";
            }
        }
        return '';
    }

    final public function makeSql_Notin ($field, $a_values, $use_table = false)
    {
        if (is_array($a_values) && 0 < count($a_values))
        {
            $field_type = $this->_getFieldDataType($field);
            $p = ($use_table) ? $this->_table . "." : '';
            if ($field_type == SS_DATA_TYPE_INT)
            {
                return "{$p}`{$field}` NOT IN (" . implode(",", $a_values) . ")";
            }
            else
            {
                return "{$p}`{$field}` NOT IN ('" . implode("','", $a_values) . "')";
            }
        }
        return '';
    }

    final public function makeSql_Limit ($page, $page_size)
    {
        $page = intval($page);
        if ($page < 0 || empty($page))
        {
            $page = 1;
        }
        $offset = ($page - 1) * $page_size;
        $length = $page_size;
        return "{$offset},{$length}";
    }

    /**
     * 过滤字符  TODO
     * @param string $value
     */
    final public function escape ($value)
    {
        return mysql_escape_string($value);
    }

    /**
     * 获取更新时设置字段
     *
     * @param  array $data
     * @return string
     */
    protected function _buildSetFields ($data)
    {
        if (! is_array($data))
        {
            return self::escape($data);
        }
        $fields = array();
        foreach ($data as $_k => $_v)
        {
            if (! is_array($_v))
            {
                $_v = self::escape($_v);
                $fields[] = "`{$_k}`='{$_v}'";
            }
        }
        return implode(',', $fields);
    }

    /**
     * 获取插入的数据SQL,仅适合插入一条,且数据是简单的数据，为安全事件，不推荐这样用
     *
     * @param  array $data
     * @return array ['fields', 'values']
     */
    protected function _buildInsertInfo ($data)
    {
        reset($data);
        $fields = array();
        $values = array();
        foreach ($data as $_k => $_v)
        {
            if (! is_array($_v))
            {
                $_v = self::escape($_v);
                $fields[] = "`{$_k}`";
                $values[] = "'{$_v}'";
            }
        }
        $fields = '(' . implode(',', $fields) . ')';
        $values = '(' . implode(',', $values) . ')';
        return compact('fields', 'values');
    }

    /**
     * 获取事务标志
     *
     * @return boolean
     */
    final public function getTrFlag ()
    {
        return $this->_flagTrStart = true;
    }

    /**
     * 启动事务
     *
     * @return boolean
     */
    final public function trStart ()
    {
        if (true == $this->_flagTrStart)
        {
            throw new Exception('Transaction is already started');
        }
        if (true != $this->_getDao()
            ->beginTransaction())
        {
            throw new Exception('Transaction start fail!');
        }
        $this->_flagTrStart = true;
        return true;
    }

    /**
     * 回滚事务
     *
     * @return boolean
     */
    final public function trRollBack ()
    {
        if (false == $this->_flagTrStart)
        {
            throw new Exception('Transaction is not yet started');
        }
        if (true != $this->_getDao()
            ->rollBack())
        {
            throw new Exception('Transaction roll back fail!');
        }
        $this->_flagTrStart = false;
        return true;
    }

    /**
     * 提交事务
     *
     * @return boolean
     */
    final public function trCommit ()
    {
        if (false == $this->_flagTrStart)
        {
            throw new Exception('Transaction is not yet started');
        }
        if (true != $this->_getDao()
            ->commit())
        {
            throw new Exception('Transaction commit fail!');
        }
        $this->_flagTrStart = false;
        return true;
    }

    /**
     * 计算偏移量,针对索引表的处理
     */
    public static function calculateRange ($a_amount, $offset, $size, $sort = 'DESC')
    {
        $sum = 0;
        $a_temp = array();
        $keys = array_keys($a_amount);
        natsort($keys);
        $a_new_array = array();
        foreach ($keys as $k)
        {
            $a_new_array[$k] = $a_amount[$k];
        }
        if ('DESC' == $sort)
        {
            $a_new_array = array_reverse($a_new_array, true);
        }
        $b_first1 = true;
        foreach ($a_new_array as $key => $value)
        {
            $sum = $sum + $value;
            if ($sum < $offset)
            {
                continue;
            }
            else
            {
                if (true == $b_first1)
                {
                    $start = $offset - ($sum - $value);
                    $b_first1 = false;
                    if ($sum < ($offset + $size))
                    {
                        $a_temp[$key] = array(
                            $start ,
                            $sum - $offset
                        );
                        continue;
                    }
                    else
                    {
                        $a_temp[$key] = array(
                            $start ,
                            $size
                        );
                        break;
                    }
                }
                else
                {
                    $start = 0;
                    if ($sum < ($offset + $size))
                    {
                        $a_temp[$key] = array(
                            $start ,
                            $value
                        );
                        continue;
                    }
                    else
                    {
                        $a_temp[$key] = array(
                            $start ,
                            ($offset + $size) - ($sum - $value)
                        );
                        break;
                    }
                }
            }
        }
        return $a_temp;
    }

    /**
     * 释放资源
     */
    public final function free ()
    {
        self::$_dao = null;
    }

    /**
     * 自定义句柄
     */
    public final function setPdo (PDO $pdo)
    {
        self::$_dao = $pdo;
    }

    /**
     * 数据库
     *
     * @return PDO
     */
    private function _getDao ()
    {
        if (null == self::$_dao)
        {
            $pdo = ss_connect_db();
            self::$_dao = $pdo;
        }
        return self::$_dao;
    }

    final public function debug ()
    {
        var_dump($this->_table);
        var_dump($this->_flagTrStart);
    }
}

/**
 * 加密解密

 *
 */
class SEncrypt
{

    /**
     * 加密
     * @param string $text
     * return string
     */
    public static function encrypt ($text)
    {
        return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, SS_LIBS_CORE_SALT_ENCRYPT_KEY, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
    }

    /**
     * 解密
     * @param string $text
     * return string
     */
    public static function decrypt ($text)
    {
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, SS_LIBS_CORE_SALT_ENCRYPT_KEY, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }

    private $_encryption_key = '';

    private $_hash_type = 'sha1';

    private $_mcrypt_exists = false;

    private $_mcrypt_cipher;

    private $_mcrypt_mode;

    /**
     * Constructor
     *
     * Simply determines whether the mcrypt library exists.
     *
     */
    public function __construct ($use_mcrypt = false)
    {
        if ($use_mcrypt)
        {
            $this->_mcrypt_exists = (! function_exists('mcrypt_encrypt')) ? false : true;
        }
    }

    /**
     * Fetch the encryption key
     *
     * Returns it as MD5 in order to have an exact-length 128 bit key.
     * Mcrypt is sensitive to keys that are not the correct length
     *

     * @param   string
     * @return  string
     */
    private function _getKey ($key = '')
    {
        if ($key == '')
        {
            $key = $this->_encryption_key;
            if ($key == '')
            {
                $key = SS_LIBS_CORE_SALT_ENCRYPT_KEY;
            }
        }
        return md5($key);
    }

    /**
     * Set the encryption key
     *

     * @param   string
     * @return  void
     */
    public function setKey ($key = '')
    {
        $this->_encryption_key = $key;
    }

    /**
     * Encode
     *
     * Encodes the message string using bitwise XOR encoding.
     * The key is combined with a random hash, and then it
     * too gets converted using XOR. The whole thing is then run
     * through mcrypt (if supported) using the randomized key.
     * The end result is a double-encrypted message string
     * that is randomized with each call to this private function,
     * even if the supplied message and key are the same.
     *

     * @param   string  the string to encode
     * @param   string  the key
     * @return  string
     */
    public function encode ($string, $key = '')
    {
        $key = $this->_getKey($key);
        if ($this->_mcrypt_exists === true)
        {
            $enc = $this->_mcryptEncode($string, $key);
        }
        else
        {
            $enc = $this->_xorEncode($string, $key);
        }
        return base64_encode($enc);
    }

    /**
     * Decode
     *
     * Reverses the above process
     *

     * @param   string
     * @param   string
     * @return  string
     */
    public function decode ($string, $key = '')
    {
        $key = $this->_getKey($key);
        if (preg_match('/[^a-zA-Z0-9\/\+=]/', $string))
        {
            return false;
        }
        $dec = base64_decode($string);
        if ($this->_mcrypt_exists === true)
        {
            if (($dec = $this->_mcryptDecode($dec, $key)) === false)
            {
                return false;
            }
        }
        else
        {
            $dec = $this->_xorDecode($dec, $key);
        }
        return $dec;
    }

    /**
     * XOR Encode
     *
     * Takes a plain-text string and key as input and generates an
     * encoded bit-string using XOR
     *

     * @param   string
     * @param   string
     * @return  string
     */
    private function _xorEncode ($string, $key)
    {
        $rand = '';
        while (strlen($rand) < 32)
        {
            $rand .= mt_rand(0, mt_getrandmax());
        }
        $rand = $this->_hash($rand);
        $enc = '';
        for ($i = 0; $i < strlen($string); $i ++)
        {
            $enc .= substr($rand, ($i % strlen($rand)), 1) . (substr($rand, ($i % strlen($rand)), 1) ^ substr($string, $i, 1));
        }
        return $this->_xorMerge($enc, $key);
    }

    // --------------------------------------------------------------------
    /**
     * XOR Decode
     *
     * Takes an encoded string and key as input and generates the
     * plain-text original message
     *

     * @param   string
     * @param   string
     * @return  string
     */
    private function _xorDecode ($string, $key)
    {
        $string = $this->_xorMerge($string, $key);
        $dec = '';
        for ($i = 0; $i < strlen($string); $i ++)
        {
            $dec .= (substr($string, $i ++, 1) ^ substr($string, $i, 1));
        }
        return $dec;
    }

    /**
     * XOR key + string Combiner
     *
     * Takes a string and key as input and computes the difference using XOR
     *

     * @param   string
     * @param   string
     * @return  string
     */
    private function _xorMerge ($string, $key)
    {
        $hash = $this->_hash($key);
        $str = '';
        for ($i = 0; $i < strlen($string); $i ++)
        {
            $str .= substr($string, $i, 1) ^ substr($hash, ($i % strlen($hash)), 1);
        }
        return $str;
    }

    /**
     * Encrypt using Mcrypt
     *

     * @param   string
     * @param   string
     * @return  string
     */
    private function _mcryptEncode ($data, $key)
    {
        $init_size = mcrypt_get_iv_size($this->_getCipher(), $this->_getMode());
        $init_vect = mcrypt_create_iv($init_size, MCRYPT_RAND);
        return $this->_addCipherNoise($init_vect . mcrypt_encrypt($this->_getCipher(), $key, $data, $this->_getMode(), $init_vect), $key);
    }

    /**
     * Decrypt using Mcrypt
     *

     * @param   string
     * @param   string
     * @return  string
     */
    private function _mcryptDecode ($data, $key)
    {
        $data = $this->_removeCipherNoise($data, $key);
        $init_size = mcrypt_get_iv_size($this->_getCipher(), $this->_getMode());
        if ($init_size > strlen($data))
        {
            return false;
        }
        $init_vect = substr($data, 0, $init_size);
        $data = substr($data, $init_size);
        return rtrim(mcrypt_decrypt($this->_getCipher(), $key, $data, $this->_getMode(), $init_vect), "\0");
    }

    /**
     * Adds permuted noise to the IV + encrypted data to protect
     * against Man-in-the-middle attacks on CBC mode ciphers
     * http://www.ciphersbyritter.com/GLOSSARY.HTM#IV
     *
     * private function description
     *

     * @param   string
     * @param   string
     * @return  string
     */
    private function _addCipherNoise ($data, $key)
    {
        $keyhash = $this->_hash($key);
        $keylen = strlen($keyhash);
        $str = '';
        for ($i = 0, $j = 0, $len = strlen($data); $i < $len; ++ $i, ++ $j)
        {
            if ($j >= $keylen)
            {
                $j = 0;
            }
            $str .= chr((ord($data[$i]) + ord($keyhash[$j])) % 256);
        }
        return $str;
    }

    /**
     * Removes permuted noise from the IV + encrypted data, reversing
     *
     * private function description
     *

     * @param   type
     * @return  type
     */
    private function _removeCipherNoise ($data, $key)
    {
        $keyhash = $this->_hash($key);
        $keylen = strlen($keyhash);
        $str = '';
        for ($i = 0, $j = 0, $len = strlen($data); $i < $len; ++ $i, ++ $j)
        {
            if ($j >= $keylen)
            {
                $j = 0;
            }
            $temp = ord($data[$i]) - ord($keyhash[$j]);
            if ($temp < 0)
            {
                $temp = $temp + 256;
            }
            $str .= chr($temp);
        }
        return $str;
    }

    /**
     * Set the Mcrypt Cipher
     *

     * @param   constant
     * @return  string
     */
    public function setCipher ($cipher)
    {
        $this->_mcrypt_cipher = $cipher;
    }

    /**
     * Set the Mcrypt Mode
     *

     * @param   constant
     * @return  string
     */
    public function setMode ($mode)
    {
        $this->_mcrypt_mode = $mode;
    }

    /**
     * Get Mcrypt cipher Value
     *

     * @return  string
     */
    private function _getCipher ()
    {
        if ($this->_mcrypt_cipher == '')
        {
            $this->_mcrypt_cipher = MCRYPT_RIJNDAEL_256;
        }
        return $this->_mcrypt_cipher;
    }

    /**
     * Get Mcrypt Mode Value
     *

     * @return  string
     */
    private function _getMode ()
    {
        if ($this->_mcrypt_mode == '')
        {
            $this->_mcrypt_mode = MCRYPT_MODE_CBC;
        }
        return $this->_mcrypt_mode;
    }

    /**
     * Set the Hash type
     *

     * @param   string
     * @return  string
     */
    public function setHash ($type = 'sha1')
    {
        $this->_hash_type = ($type != 'sha1' and $type != 'md5') ? 'sha1' : $type;
    }

    /**
     * Hash encode a string
     *

     * @param   string
     * @return  string
     */
    private function _hash ($str)
    {
        return ($this->_hash_type == 'sha1') ? $this->_sha1($str) : md5($str);
    }

    /**
     * Generate an SHA1 Hash
     *
     * @param   string
     * @return  string
     */
    private function _sha1 ($str)
    {
        if (! function_exists('sha1'))
        {
            return bin2hex(mhash(MHASH_SHA1, $str));
        }
        else
        {
            return sha1($str);
        }
    }
}
defined('SS_LIBS_CORE_TOKEN') or die('NO CORE!');

/**
 * 定义基本的调试和异常处理

 *
 */
final class SException extends Exception
{

    protected static $counter = - 1;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @param  string $path
     * @return void
     */
    public function __construct ($message, $code)
    {
        $code2 = @dechex($code);
        $desc = @$GLOBALS['SS_EXCEPTION'][$code];
        $msg = "Code:0x{$code2} ";
        if (false != DEBUG_MODE)
        {
            //打印调用调试信息
            $trace = debug_backtrace();
            $file = $trace[1]['file'];
            $line = $trace[1]['line'];
            $class = $trace[1]['class'];
            $function = $trace[1]['function'];
            $args = $trace[1]['args'];
            if (! empty($args))
            {
                $args = serialize($args);
            }
            $i = self::$counter;
            $msg .= "#$i File:$file Line:$line Mark:$class::$function\n\t Args:($args)\n";
            self::$counter ++;
        }
        $msg .= "Desc:{$desc}\n";
        $msg .= "More:\n{$message}";
        parent::__construct($msg, $code);
    }
}

/**
 * 文件相关

 *
 */
class SFile
{
    public static function write ($path, $data)
    {
        if (is_array($data))
        {
            $data = serialize($data);
        }
        if (! $_fp = @fopen($path, SS_FOPEN_WRITE_CREATE))
        {
            return false;
        }
        fwrite($_fp, $data);
        fclose($_fp);
        $_fp = null;
        unset($_fp);
        return true;
    }

    public static function append ($path, $data)
    {
        if (is_array($data))
        {
            $data = serialize($data);
        }
        if (! $fp = @fopen($path, SS_FOPEN_WRITE_CREATE))
        {
            return false;
        }
        flock($fp, LOCK_EX);
        fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod($path, SS_FILE_WRITE_MODE);
        $fp = null;
        unset($fp);
        return true;
    }

    /**
     * 删除目录,不支持目录中带 ..
     *
     * @param string $dir
     * @return boolen
     */
    public static function rmDir ($dir)
    {
        $dir = str_replace(array(
            '..' ,
            "\n" ,
            "\r"
        ), array(
            '' ,
            '' ,
            ''
        ), $dir);
        $ret_val = false;
        if (is_dir($dir))
        {
            $d = @dir($dir);
            if ($d)
            {
                while (false !== ($entry = $d->read()))
                {
                    if ($entry != '.' && $entry != '..')
                    {
                        $entry = $dir . '/' . $entry;
                        if (is_dir($entry))
                        {
                            self::rmDir($entry);
                        }
                        else
                        {
                            @unlink($entry);
                        }
                    }
                }
                $d->close();
                $ret_val = rmdir($dir);
            }
        }
        else
        {
            $ret_val = unlink($dir);
        }
        return $ret_val;
    }

    /**
     *
     * 创建目录,不支持目录中带 ..
     * @param string $dir
     */
    public static function mkDir ($dir)
    {
        $dir = str_replace(array(
            '..' ,
            "\n" ,
            "\r"
        ), array(
            '' ,
            '' ,
            ''
        ), $dir);
        $buf = explode("/", $dir);
        $level = count($buf);
        $dir = $buf[0];
        for ($i = 1; $i < $level; $i ++)
        {
            $dir = $dir . "/" . $buf[$i];
            if (! is_dir($dir))
            {
                mkdir($dir, 0777);
            }
        }
        return $dir;
    }
}

/**
 * 日志相关
 * Enter description here ...

 *
 */
class SLog
{

    /**
     * 存储SLog的单一实例。
     *
     * @var    SLog
     */
    protected static $mInstance;

    /**
     * 自定义目录
     * @var string
     */
    protected $_userLogRoot = null;

    /**
     * 系统目录
     * @var string
     */
    private static $_logRoot = SS_LOGS_PATH;

    protected static $_levels = array(
        'ERROR' => '1' ,
        'DEBUG' => '2' ,
        'INFO' => '3' ,
        'ALL' => '4'
    );

    protected function __construct ()
    {
        $this->_userLogRoot = self::$_logRoot;
    }

    public function setLogRoot ($path)
    {
        if (is_dir($path))
        {
            $this->_userLogRoot = $path;
        }
    }

    /**
     * 测试变量
     * @param mixed $var
     */
    public static function dump ($var)
    {
        static $_init = true;
        $s_log_path = self::$_logRoot . "/dump_once.log";
        $r = var_export($var, true);
        $r = "\ntime:" . microtime(true) . "\n:{$r}";
        if (true == $_init)
        {
            SFile::write($s_log_path, $r);
            $_init = false;
        }
        else
        {
            SFile::append($s_log_path, $r);
        }
    }

    /**
     * Write Log File
     *
     * @param   string $desc    the error desc
     * @param   string $msg   the error message
     * @param   string $level   the error level
     * @return  bool
     */
    public function log ($desc, $msg, $level = 'error')
    {
        $_date_fmt = 'Y-m-d H:i:s';
        $level = strtoupper($level);
        $a_level = self::$_levels;
        if (! isset($a_level[$level]))
        {
            return false;
        }
        $trace = debug_backtrace();
        $file = isset($trace[1]['file']) ? $trace[1]['file'] : 'file';
        $line = isset($trace[1]['line']) ? $trace[1]['line'] : 'line';
        $class = isset($trace[1]['class']) ? $trace[1]['class'] : 'main_class';
        $function = isset($trace[1]['function']) ? $trace[1]['function'] : 'main_function';
        $p = "[{$file}]::{$line}::{$class}::{$function}";
        if (! is_string($desc))
        {
            $desc = strval($desc);
        }
        if (! is_string($msg))
        {
            $msg = strval($msg);
        }
        $path = $this->_userLogRoot . '/log-' . date('Y-m-d');
        $message = date($_date_fmt) . ' ' . $level . ' ' . $p . ' (' . $desc . ') -> ' . $msg . "\n";
        return SFile::append($path, $message);
    }

    /**
     * 获取SLog的单一实例。
     *
     * @return SLog
     */
    static public function getInstance ()
    {
        if (null === self::$mInstance)
        {
            self::$mInstance = new self();
        }
        return self::$mInstance;
    }

    static public function getInst ()
    {
        return self::getInstance();
    }
}

/**
 * 这是一个标准单体的模型
 * Enter description here ...

 */
abstract class SModel
{

    /**
     * 实体的唯一标志
     * @var int|string
     */
    protected $_id = null;

    /**
     * 实体的信息
     * @var array
     */
    protected $_info = null;

    /**
     * 错误信息
     * @var array
     */
    protected $_error = null;

    /**
     * 最后一个错误信息
     * @var string
     */
    protected $_lastError = null;

    /**
     * 缓存时间
     * @var int
     */
    protected $_cache_life_time = 0;

    /**
     * 验证规则
     * @var array
     */
    protected $_vconf = null;

    /**
     * 物理数据驱动
     * @var SData
     */
    protected $_data = null;

    /**
     * 获取标识
     * @return int|string
     */
    public function getId ()
    {
        return $this->_id;
    }

    /**
     * 获取实体的信息
     * @return array
     */
    public function getInfo ()
    {
        return $this->_info;
    }

    /**
     * 重置实体
     */
    public function reset ()
    {
        $this->_id = null;
        $this->_info = null;
        $this->_resetError();
    }

    /**
     * 获取错误信息
     * @return array
     */
    public function getError ()
    {
        return $this->_error;
    }

    /**
     * 获取错误信息
     * @return array
     */
    public function getLastError ()
    {
        if (null == $this->_lastError)
        {
            if (null == $this->_error)
            {
                return '';
            }
            return end($this->_error);
        }
        return $this->_lastError;
    }

    /**
     * 设置错误信息
     * @return array
     */
    protected function _setError ($key, $value)
    {
        $key = strval($key);
        $this->_error[$key] = $value;
        $this->_lastError = $value;
    }

    /**
     * 设置错误信息
     * @return array
     */
    protected function _resetError ()
    {
        $this->_error = null;
        $this->_lastError = null;
    }

    /**
     * 允许直接访问实体数组里面的值，info[$property]
     *
     * @param $property
     * @return maxie
     */
    public function __get ($property)
    {
        if (empty($property) || ! isset($this->_info[$property]))
        {
            throw new Exception("Entity property:{$property} is not exist");
        }
        else
        {
            return $this->_info[$property];
        }
    }

    /**
     * 验证
     * @return array
     */
    public function valid ($a_data)
    {
        $a_conf = $this->_vconf;
        if (empty($a_conf) || empty($a_data))
        {
            return false;
        }
        $this->_resetError();
        $o_v = new SValidator($a_conf);
        $r = $o_v->check($a_data);
        if (! $r)
        {
            $this->_error = $o_v->getErrorInfo();
            return false;
        }

        return $o_v->getQualifiedData();
    }

    /**
     * 普通创建，id为自增
     */
    public function create ($a_data)
    {
        try
        {       	
        	$a_data['create_time_i'] = NOW;
            $a_data['update_time_i'] = NOW;
            $a_data = $this->valid($a_data);
            $a_phy_data = $this->_preparePhyData($a_data);
            $a_data_to_insert = $a_phy_data['_data'];
            unset($a_data_to_insert[$this->_data->getPrimaryKey()]);
            $this->_data->trStart();
            $id = $this->_data->insert($a_data_to_insert);
            $this->_data->trCommit();
            self::_onCreate();
            return $id;
        }
        catch (Exception $e)
        {
            if (false != $this->_data->getTrFlag())
            {
                $this->_data->trRollBack();
            }
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_CREATE);
        }
    }

    /**
     * 伪接口:创建，多表结构
     */
    public function createMore ($a_data)
    {
        try
        {
            $a_data = $this->valid($a_data);
            $this->_data->trStart();
            $a_phy_data = self::_preparePhyData($a_data);
            foreach ($a_phy_data as $key => $a_data_to_insert)
            {
                $this->$key->insert($a_data_to_insert);
            }
            $this->_data->trCommit();
            //
            self::_onCreate();
            return true;
        }
        catch (Exception $e)
        {
            if (false != $this->_data->getTrFlag())
            {
                $this->_data->trRollBack();
            }
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_CREATE);
        }
    }

    /**
     * 普通更新
     */
    public function update ($a_info_to_update)
    {
        try
        {
            $now = NOW;
            $a_info_to_update['update_time_i'] = $now;
            $a_info_to_update = $this->valid($a_info_to_update);

            $a_phy_data = $this->_preparePhyData($a_info_to_update);
            $a_data_to_update = $a_phy_data['_data'];
            unset($a_data_to_update[$this->_data->getPrimaryKey()]);
            $this->_data->trStart();
            $id = $this->getId();
            $a_where = array(
                $this->_data->getPrimaryKey() => $id
            );
            $s_condition = $this->_data->makeSql_Equal($a_where);

            $this->_data->update($s_condition, $a_data_to_update);
            $this->_data->trCommit();
            return true;
        }
        catch (Exception $e)
        {
            if (false != $this->_data->getTrFlag())
            {
                $this->_data->trRollBack();
            }
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_UPDATE);
        }
    }

    /**
     * 伪接口:更新多表结构
     */
    public function updateMore ($a_info_to_update)
    {
        try
        {
            $a_info_to_update = $this->valid($a_info_to_update);
            $this->_data->trStart();
            $id = $this->getId();
            $a_phy_data = self::_preparePhyData($a_info_to_update);
            foreach ($a_phy_data as $pdata => $a_data_can_update)
            {
                $s_condition = $this->$pdata->makeSql_Equal($this->$pdata->getPrimaryKey(), $id);
                $this->$pdata->update($s_condition, $a_data_can_update);
            }
            $this->_data->trCommit();
            return true;
        }
        catch (Exception $e)
        {
            if (false != $this->_data->getTrFlag())
            {
                $this->_data->trRollBack();
            }
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_UPDATE);
        }
    }

    protected function _onCreate ()
    {
        return;
        throw new Exception('Method [on_create] must be overrides in child');
    }

    protected function _onUpdate ()
    {
        return;
        throw new Exception('Method [on_update] must be overrides in child');
    }

    protected function _onDrop ()
    {
        return;
        throw new Exception('Method [on_drop] must be overrides in child');
    }

    protected static function _makeCacheKey ()
    {
        return;
        throw new Exception('Method [_make_cache_key] must be overrides in child');
    }

    protected static function _resetCache ()
    {
        return;
        throw new Exception('Method [_reset_cache] must be overrides in child');
    }

    /**
     * 注册一个数据实例
     * @param string $name
     * @param SData $data_entity
     * @return boolean
     */
    public function registerData ($name, SData $data_entity)
    {
        if (! property_exists($this, $name))
        {
            throw new Exception("Property {$name} is not defined,so it can not be regedit as Data instance");
            return false;
        }
        if (null != $this->$name)
        {
            throw new Exception("Property {$name} is already defined");
            return false;
        }
        $this->$name = $data_entity;
        return $this;
    }

    /**
     * 物理表到逻辑表达映射关系
     * @var array
     */
    protected $_mapPhy2Logic = null;

    /**
     * 物理表到逻辑表达映射关系
     * @var array
     */
    protected $_mapPhy2LogicPool = null;

    /**
     * 逻辑表到达物理表映射关系
     * @var array
     */
    protected $_mapLogic2Phy = null;

    /**
     * 逻辑表到达物理表映射关系
     * @var array
     */
    protected $_mapLogic2PhyPool = null;

    protected function _buildDataMap ()
    {
        if (! isset($this->_vconf['rule']))
        {
            return false;
        }
        $a_fields = $this->_vconf['rule'];
        foreach ($a_fields as $key => $value)
        {
            if (! isset($value['phy_data']))
            {
                $a_phy_data = array(
                    '_data'
                );
            }
            else
            {
                $a_phy_data = explode(',', $value['phy_data']);
            }
            foreach ($a_phy_data as $phy_data)
            {
                $phy_key = 'f_' . $key;
                if (! property_exists($this, $phy_data) || ! property_exists($this->$phy_data, $phy_key))
                {
                    continue;
                }
                $this->_mapLogic2Phy[$key] = $this->$phy_data->$phy_key;
                $this->_mapLogic2PhyPool[$key][] = $phy_data;
                $this->_mapPhy2Logic[$this->$phy_data->$phy_key] = $key;
                $this->_mapPhy2LogicPool[$phy_data][$this->$phy_data->$phy_key] = $key;
            }
        }
         // var_dump($this->_mapLogic2Phy,$this->_mapLogic2PhyPool,$this->_mapPhy2Logic,$this->_mapPhy2LogicPool);
    }

    /**
     * 分解数据
     */
    protected function _extract ($a_data)
    {
        if (! is_array($a_data) || empty($a_data))
        {
            return false;
        }
        $a_result = array();
        foreach ($a_data as $key => $value)
        {
            $a_result[$this->_mapPhy2Logic[$key]] = $value;
        }
        return $a_result;
    }

    protected $_pk = null;

    /**
     * 分解数据
     */
    protected function _preparePhyData ($a_data)
    {
        if (! is_array($a_data) || empty($a_data))
        {
            return false;
        }
        $a_result = array();
        foreach ($a_data as $key => $value)
        {
            if (! isset($this->_mapLogic2Phy[$key]) || ! isset($this->_mapLogic2PhyPool[$key]))
            {
                continue;
            }
            $a_phys = $this->_mapLogic2PhyPool[$key];
            foreach ($a_phys as $phy)
            {
                $a_result[$phy][$this->_mapLogic2Phy[$key]] = $value;
            }
        }
        return $a_result;
    }

    /**
     * 从数据库加载
     * @param int $id
     * @return self
     */
    public function attach ($id)
    {
        try
        {
            $a_where = array(
                $this->_data->getPrimaryKey() => $id
            );
            $s_condition = $this->_data->makeSql_Equal($a_where);
            $a_condition['where'] = $this->_data->makeSql_Equal($a_where);
            $a_info = $this->_data->find($a_condition, SData::FETCH_ONE);
            $a_info = $this->_extract($a_info);
            return $this->init($a_info);
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_ATTACH);
        }
    }

    /**
     * 伪接口:删除
     */
    public function drop ()
    {
        try
        {
            $id = $this->getId();
            $a_where = array(
                $this->_data->getPrimaryKey() => $id
            );
            $s_condition = $this->_data->makeSql_Equal($a_where);
            return $this->_data->drop($s_condition);
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_DROP);
        }
    }

    /**
     * 填充模型实例
     * @param array $a_data
     * @return self
     */
    public function init ($a_data)
    {
    	//echo "init***";
        //var_dump(empty($this->_pk) , empty($a_data) , isset($a_data[$this->_pk]));
       // var_dump($a_data);
        if (empty($this->_pk) || empty($a_data) || ! isset($a_data[$this->_pk]))
        {
            $msg = 'no_mpk';
            throw new SException($msg, SS_EX_INIT);
        }
        else
        {
            $this->_id = $a_data[$this->_pk];
            $this->_info = $a_data;
            return $this;
        }
    }

    /**
     * 获取公开的信息
     * @return array
     */
    public function getPublicInfo ()
    {
        if (! isset($this->_vconf['rule']))
        {
            return false;
        }
        $a_result = array();
        $a_fields = $this->_vconf['rule'];
        foreach ($a_fields as $key => $value)
        {
            if (isset($value['is_private']) && true == $value['is_private'])
            {
                continue;
            }
            $a_result[$key] = $this->_info[$key];
        }
        return $a_result;
    }

    /**
     * 获取全部列表
     * @return array
     */
    public function getListAll ()
    {
        try
        {
            $a_condition = array();
            $a_condition['fields'] = '*';
            $a_condition['where'] = 1;
            $a_condition['index_key'] = $this->_data->getPrimaryKey();
            $a_condition['order'] = $this->_data->f_create_time_i . ' DESC';
            $a_list = $this->_data->find($a_condition, SData::FETCH_ALL);
            $a_result = array();
            foreach ($a_list as $id => $a_value)
            {
                $this->reset();
                $a_info = $this->_extract($a_value);
                $this->init($a_info);
                $a_result[$id] = $this->getInfo();
            }
            return $a_result;
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            throw new SException($msg, SS_EX_LIST);
        }
    }
}

/**
 * 分页帮助
 *
 */
class SPager
{

    /**
     * 将数组分页，返回分页页数等内容
     *
     * @param  int $total            要分页的总数
     * @param  int   $size           每页大小
     * @param  int   $page           当前页数
     * @return array                 分页信息。
     */
    public static function buildPagerType_A ($total, $size = 10, $page = 1)
    {
        $total = abs(0 + $total);
        $size = abs(0 + $size);
        $page = abs(0 + $page);
        if ($size < 1)
        {
            $size = 10;
        }
        if ($total < 1)
        {
            $total = 1;
        }
        $page_count = ceil($total / $size);
        if ($page < 1 || $page > $page_count)
        {
            $page = 1;
        }
        $low = 1 + $size * ($page - 1);
        $hign = min($total, $size * $page);
        $pager = array();
        $pager['TOTAL'] = $total;
        $pager['SIZE'] = $size;
        $pager['LOW'] = $low;
        $pager['HIGH'] = $hign;
        $pager['CURRENT'] = $page;
        $pager['MAX'] = $page_count;
        $pager['PREVIOUS'] = max($page - 1, 1);
        $pager['NEXT'] = min($page + 1, $page_count);
        return $pager;
    }

    /**
     * 将数组分页，返回分页页数等内容,中间包含部分页数
     *
     * @param  int $total            要分页的总数
     * @param  int   $size           每页大小
     * @param  int   $page           当前页数
     * @return array                 分页信息。
     */
    public static function buildPagerType_B ($total, $size = 10, $page = 1, $sibling_count = 2)
    {
        $total = abs(0 + $total);
        $size = abs(0 + $size);
        $page = abs(0 + $page);
        if ($size < 1)
        {
            $size = 10;
        }
        if ($total < 1)
        {
            $total = 1;
        }
        $page_count = ceil($total / $size);
        if ($page < 1 || $page > $page_count)
        {
            $page = 1;
        }
        $low = 1 + $size * ($page - 1);
        $hign = 0 + min($total, $size * $page);
        $pager = array();
        $pager['TOTAL'] = $total;
        $pager['SIZE'] = $size;
        $pager['LOW'] = $low;
        $pager['HIGH'] = $hign;
        $pager['CURRENT'] = $page;
        $pager['MAX'] = $page_count;
        $pager['PREVIOUS'] = max($page - 1, 1);
        $pager['NEXT'] = min($page + 1, $page_count);
        $button_count = $sibling_count * 2 + 2;
        if ($page_count <= $button_count + 1)
        {
            $left_begin = 2;
            $left_end = $pager['PREVIOUS'];
            $right_begin = $pager['NEXT'];
            $right_end = $page_count - 1;
        }
        else
        {
            $left_begin = max($page - $sibling_count, 2);
            $left_end = $pager['PREVIOUS'];
            $right_begin = $pager['NEXT'];
            $right_end = min($page + $sibling_count, $page_count - 1);
            if ($page < $sibling_count + 2)
            {
                $right_end = $button_count;
            }
            elseif ($page > $page_count - $sibling_count - 2)
            {
                $left_begin = $page_count - $button_count + 1;
            }
        }
        $pager['ALL_LIST'] = range(1, $page_count);
        $pager['LEFT_DOT'] = $left_begin > 2 ? '...' : '';
        $pager['LEFT_LIST'] = $left_begin <= $left_end ? range($left_begin, $left_end) : array();
        $pager['RIGHT_DOT'] = $right_end < $page_count - 1 ? '...' : '';
        $pager['RIGHT_LIST'] = $right_begin <= $right_end ? range($right_begin, $right_end) : array();
        return $pager;
    }

    /**
     * 将数组分页，返回分页后的部分结果
     * @param  array $list           要分页的数据
     * @param  int   $size           每页大小
     * @param  int   $page           当前页数
     * @return array
     *
     */
    public static function getPagerData (array $list, $size = 24, $page = 1)
    {
        $item_count = count($list);
        $page_count = 0;
        $size = $size ? abs($size) : 24;
        if ($item_count)
        {
            $page_count = ceil($item_count / $size);
        }
        if (! is_int($page) || $page < 1 || $page > $page_count)
        {
            $page = 1;
        }
        if ($list)
        {
            try
            {
                $result = array_slice($list, ($page - 1) * $size, $size, false);
            }
            catch (Exception $e)
            {
                $result = array();
            }
        }
        else
        {
            $result = array();
        }
        return $result;
    }
}

/**
 * 定义安全控制

 *
 */
final class SSecure
{

    /**
     * 用 strip_tags() 来过滤 PHPSESSID 以避免 XSS 相关的攻击
     *
     * @return boolean
     */
    public static function protectXSS ()
    {
        $ip = ss_get_client_ip();
        if (! isset($_REQUEST['PHPSESSID']) || ! isset($_SESSION['USER_AGENT']))
        {
            /* 如果用户session ID是伪造,则重新分配session ID */
            return self::_resetSession();
        }
        $sessionid = strip_tags($_REQUEST['PHPSESSID']);
        if (strlen($sessionid) == strlen($_REQUEST['PHPSESSID']) && $_SESSION['USER_AGENT'] == MD5($ip . $_SERVER['HTTP_USER_AGENT']))
        {
            $_SESSION['PHPSESSID'] = $sessionid;
            return true;
        }
        else
        {
            //PHPSESSID XSS 攻击
            self::_resetSession();
            return false;
        }
    }

    /**
     * 保护ip相同情况
     *
     */
    public static function protectSameIP ()
    {
        $ip = ss_get_client_ip();
        if (! isset($_SESSION['REMOTE_IP']))
        {
            $_SESSION['REMOTE_IP'] = $ip;
        }
        elseif ($ip != $_SESSION['REMOTE_IP'])
        {
            self::_resetSession();
        }
    }

    /**
     * 清理session,产生新的sessionID
     *
     */
    private static function _resetSession ()
    {
        $ip = ss_get_client_ip();
        $old_session_id = session_id();
        session_regenerate_id();
        $new_session_id = session_id();
        $_SESSION['PHPSESSID'] = $new_session_id;
        $_SESSION['REMOTE_IP'] = $ip;
        $s_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'cgi-bin';
        $_SESSION['USER_AGENT'] = MD5($ip . $s_agent);
        return;
    }

    /**
     * 清理session
     *
     */
    public static function clearSession ()
    {
        @session_destroy();
        setcookie(session_name(), '', NOW - 3600);
        $_SESSION = array();
    }

    /**
     * 用客户IP与随机数组和通过MD5产生一个随机串，用于防治刷新一类的操作
     *
     * @return string 随机串
     */
    static public function getRandomKey ()
    {
        $rnd = rand();
        $keystr = md5($_SESSION['REMOTE_IP'] . $rnd);
        return $keystr;
    }
}

/**
 * SSmarty
 *
 */
class SSmarty
{

    /**
     * 存储SSmarty的单一实例。
     *
     * @var    SSmarty
     */
    protected static $mInstance;

    /**
     * Smarty初始化
     * @return Smarty
     */
    static public function init (Smarty $smarty)
    {
        $smarty->left_delimiter = "{_";
        $smarty->right_delimiter = "_}";
        //$smarty->template_dir = SS_TMPL_PATH;
        $smarty->compile_dir = SS_DATA_PATH . "/smarty/tpl_c/";
        $smarty->config_dir = SS_DATA_PATH . "/smarty/configs/";
        $smarty->cache_dir = SS_DATA_PATH . "/smarty/cache/";
        if (! is_dir($smarty->compile_dir))
        {
            if (false === mkdir($smarty->compile_dir, 0755, true))
            {
                die('smarty_compile_dir');
                exit(0);
            }
        }
        if (! is_dir($smarty->config_dir))
        {
            if (false === mkdir($smarty->config_dir, 0755, true))
            {
                die('smarty_config_dir_error');
                exit(0);
            }
        }
        if (! is_dir($smarty->cache_dir))
        {
            if (false === mkdir($smarty->cache_dir, 0755, true))
            {
                die('smarty_cache_dir_error');
                exit(0);
            }
        }
        if (true == DEBUG_MODE)
        {
            $smarty->force_compile = true; //强行编译
            $smarty->caching = 0; // cache关闭
            $smarty->compile_check = true; // 模板文件更动时是否自动编译template_c下文件
        }
        else
        {
            $smarty->compile_check = true; // 模板文件更动时是否自动编译template_c下文件
            $smarty->caching = 2; //cache关闭
            $smarty->cache_lifetime = 300; //默认缓存时间5分钟
        }
        return $smarty;
    }

    /**
     * 获取SSmarty的单一实例。
     *
     * @return Smarty
     */
    static public function getInstance ()
    {
        if (null === self::$mInstance)
        {
            if (function_exists('config_smarty'))
            {
                $smarty = config_smarty();
            }
            else
            {
                import('helpers.smarty');
                $smarty = new Smarty();
            }
            self::$mInstance = self::init($smarty);
        }
        return self::$mInstance;
    }
}

/**
 * 网络操作类
 */
class SSocket
{

    /**
     * httpget请求
     *
     * @param string $url 访问地址
     * @param int $timeout 超时
     * @return string $pageMemo
     */
    public static function httpGet ($url, $timeout = 30, $host = '')
    {
        $time_begin = time();
        if ($host)
        {
            $header[] = 'Host: ' . $host;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        if (! empty($header))
        {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        //curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727; .NET CLR 1.1.4322)");
        $pageMemo = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (curl_errno($ch))
        {
            self::sockWriteTimeLog($time_begin, "curl_get_err:\t$url\t" . curl_error($ch));
            //curl_close ($ch);
            return false;
        }
        if ($info['http_code'] >= 400)
        {
            self::sockWriteTimeLog($time_begin, "curl_get_err: {$url} http_code: " . $info['http_code']);
            //curl_close ($ch);
            return false;
        }
        //curl_close ($ch);
        self::sockWriteTimeLog($time_begin, "curl_get_succ:\t$url");
        return $pageMemo;
    }

    /**
     * httpsslget请求
     *
     * @param string $url 访问地址
     * @param int $timeout 超时
     * @return $pageMemo
     */
    public static function httpSSLGet ($url, $timeout = 30)
    {
        $time_begin = time();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727; .NET CLR 1.1.4322)");
        $pageMemo = curl_exec($ch);
        $info = curl_getinfo($ch);
        if (curl_errno($ch))
        {
            self::sockWriteTimeLog($time_begin, "curl_get_err:\t$url\t" . curl_error($ch));
            curl_close($ch);
            return false;
        }
        if ($info['http_code'] >= 400)
        {
            self::sockWriteTimeLog($time_begin, "curl_get_err: {$url} http_code: " . $info['http_code']);
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        self::sockWriteTimeLog($time_begin, "curl_get_succ:\t$url");
        return $pageMemo;
    }

    /**
     * get 方式的 socket 连接
     *
     * @param string $url 访问地址
     * @param int $timeout 超时时间
     * @param string &$reason
     * @param string &$status
     * @return string $content for succ, bool false for fail
     */
    public static function sockGet ($url, $timeout, &$reason, &$status)
    {
        $time_begin = time();
        $info = parse_url($url);
        if (! $info["port"])
        {
            $info["port"] = "80";
        }
        /// socket 连接: 3 秒超时,连接上以后的交互才使用 $timeout
        $fp = fsockopen($info["host"], $info["port"], $errno, $errstr, 3);
        if (! $fp)
        {
            $reason = "connect";
            self::sockWriteTimeLog($time_begin, "get_connect_err:\t$url");
            return false;
        }
        if (strlen($info["query"]))
        {
            $head = "GET " . $info['path'] . "?" . $info["query"] . " HTTP/1.1\r\n";
        }
        else
        {
            $head = "GET " . $info['path'] . " HTTP/1.0\r\n";
        }
        $head .= "Host: " . $info['host'] . "\r\n";
        $head .= "\r\n";
        $write = fwrite($fp, $head);
        if ($write != strlen($head))
        {
            $reason = "write";
            $head = str_replace("\r\n", " ", $head);
            self::sockWriteTimeLog($time_begin, "get_write_err:\t$url\t$head");
            return false;
        }
        stream_set_timeout($fp, $timeout);
        $result = "";
        $i = 0;
        $line = '';
        while (! feof($fp))
        {
            unset($line);
            $line = fread($fp, 4096);
            if ($i == 0)
            {
                list ($protocol, $status, $statuswork) = explode(" ", $line, 3);
                if ($status >= 300)
                {
                    fclose($fp);
                    $reason = "status";
                    self::sockWriteTimeLog($time_begin, "get_status_err:\t$url\t$line");
                    return false;
                }
                $i = 1;
            }
            $result .= $line;
            $metadata = stream_get_meta_data($fp);
            if ($metadata["timed_out"])
            {
                fclose($fp);
                $reason = "timeout";
                self::sockWriteTimeLog($time_begin, "get_timeout:\t$url\t$result");
                return false;
            }
        }
        fclose($fp);
        $result = explode("\r\n\r\n", $result, 2);
        $result = $result[1];
        self::sockWriteTimeLog($time_begin, "get_succ:\t$url\t$result");
        return $result;
    }

    /**
     * post 方式的 socket 连接
     *
     * @param string $base_url 访问地址
     * @param string $query
     * @param int $timeout
     * @param string &$fail_reason
     * @param string &$http_status
     * @return string $content for succ, bool false for fail
     */
    public static function sockPost ($url, $query, $timeout, &$reason, &$status, $cookies = array())
    {
        if (is_array($query))
        {
            $q = '';
            foreach ($query as $p => $v)
            {
                $q .= "&{$p}=" . rawurlencode($v);
            }
            $query = $q;
        }
        
        $time_begin = time();
        $info = parse_url($url);
        $info["port"] = isset($info["port"]) ? $info["port"] : '';
        if (! $info["port"])
        {
            $info["port"] = "80";
        }
        $fp = fsockopen($info["host"], $info["port"], $errno, $errstr, 13);
        if (! $fp)
        {
            echo "aa !fp";
            $reason = "connect";
            self::sockWriteTimeLog($time_begin, "post_connect_err:\t$url");
            return false;
        }
        $info["query"] = isset($info["query"]) ? $info["query"] : '';
        if (strlen($info["query"]))
        {
            $head = "POST " . $info['path'] . "?" . $info["query"] . " HTTP/1.0\r\n";
        }
        else
        {
            $head = "POST " . $info['path'] . " HTTP/1.0\r\n";
        }
        $head .= "Host: " . $info['host'] . "\r\n";
        $head .= "Referer: http://" . $info['host'] . $info['path'] . "\r\n";
        $head .= "Content-type: application/x-www-form-urlencoded\r\n";
        $head .= "Content-Length: " . strlen(trim($query)) . "\r\n";
        $head .= "\r\n";
        $head .= trim($query);
        $write = fputs($fp, $head);
        if ($write != strlen($head))
        {
            $reason = "write";
            $head = str_replace("\r\n", " ", $head);
            self::sockWriteTimeLog($time_begin, "post_write_err:\t$url\t$head");
            return false;
        }
        stream_set_timeout($fp, $timeout);
        $result = "";
        $i = 0;
        while (! feof($fp))
        {
            $line = fread($fp, 4096);
            if ($i == 0)
            {
                list ($protocol, $status, $statuswork) = explode(" ", $line, 3);
                if ($status >= 300)
                {
                    fclose($fp);
                    $reason = "status";
                    self::sockWriteTimeLog($time_begin, "post_status_err:\t$url\t$line");
                    return false;
                }
                $i = 1;
            }
            $result .= $line;
            $metadata = stream_get_meta_data($fp);
            if ($metadata["timed_out"])
            {
                fclose($fp);
                $reason = "timeout";
                self::sockWriteTimeLog($time_begin, "post_timeout:\t$url\t$result");
                return false;
            }
        }
        fclose($fp);
        $result = explode("\r\n\r\n", $result, 2);
        $result = $result[1];
       
        self::sockWriteTimeLog($time_begin, "post_succ:\t$url\t$result");
       
        return $result;
    }

    /**
     * 打时间log
     * @param $time_begin 时间开始
     * @param $msg 日志内容
     */
    public static function sockWriteTimeLog ($time_begin, $msg)
    {
        $time_end = time();
        $time_use = $time_end - $time_begin;
        //SLog::vardump($time_use, 'time use:');
        return;
    }
}

/**
 * 无限分级类

 */
class STree
{

    private $_orgList;

    private $_allowList;

    private $_groupedList;

    public function __construct ($a_org_list = array())
    {
        $this->_orgList = $a_org_list;
        $this->_allowList = false;
        $this->_groupedList = false;
    }

    /**
     * 获取全部列表
     */
    public function getOrgList ()
    {
        return $this->_orgList;
    }

    /**
     * 允许列表
     */
    public function getAllowedList ($a_allow_ids)
    {
        if (! $this->_allowList)
        {
            $a_allow = array();
            //获取母树
            $mother_tree = $this->_getGroupedIds();
            //组权限
            if (! empty($a_allow_ids))
            {
                foreach ($a_allow_ids as $request_id)
                {
                    array_push($a_allow, $request_id);
                    // loop
                    $a_allow_c = $this->_findChild($request_id, $mother_tree);
                    $a_allow_f = $this->_findFather($request_id, $mother_tree);
                    $a_allow = array_merge($a_allow, $a_allow_c, $a_allow_f);
                }
            }
            $a_allow_ids_full = array_unique($a_allow);
            $a_temp = array();
            foreach ($a_allow_ids_full as $v)
            {
                if (isset($this->_orgList[$v]))
                {
                    $a_temp[$v] = $this->_orgList[$v];
                }
            }
            $this->_allowList = $a_temp;
        }
        return $this->_allowList;
    }

    /**
     * 获取父列表
     */
    public function getFatherList ($a_child_ids)
    {
        $a_father = array();
        //获取母树
        $mother_tree = $this->_getGroupedIds();
        //组权限
        if (! empty($a_child_ids))
        {
            foreach ($a_child_ids as $request_id)
            {
                // loop
                $a_f = $this->_findFather($request_id, $mother_tree);
                $a_father[$request_id] = array_unique($a_f);
            }
        }
        return $a_father;
    }

    /**
     * 获取子列表
     */
    public function getChildList ($a_father_ids)
    {
        $a_father = array();
        //获取母树
        $mother_tree = $this->_getGroupedIds();
        //到向寻找
        rsort($a_father_ids);
        //组权限
        if (! empty($a_father_ids))
        {
            foreach ($a_father_ids as $request_id)
            {
                // loop
                $a_f = $this->_findChild($request_id, $mother_tree);
                $a_father[$request_id] = array_unique($a_f);
            }
        }
        return $a_father;
    }

    //
    /*从数据库中获取所有请求的关系列表
    格式Array(fatherid => Array(childid1 => childname1 ,,,),,,)
    输出格式如：
    Array(
            [36] => Array
            (
                [39] => 列表用户
                [38] => 修改用户
                [37] => 新建用户
            )
            [0] => Array
            (
                [26] => 管理系统
            )
        )
    */
    public function getGroupedList ()
    {
        if (! $this->_groupedList)
        {
            $sorted_group = array();
            $a_request = $this->getOrgList();
            //记录为空，返回空数组
            if (! $a_request)
            {
                return $sorted_group;
            }
            //排序数组
            foreach ($a_request as $key => $value)
            {
                $father = $value['pid'];
                $name = $value['name'];
                $sorted_group[$father][$key] = $name;
            }
            $this->_groupedList = $sorted_group;
        }
        return $this->_groupedList;
    }

    /*
     * 将sortGroup() 的数组已另一种形式来显示
    格式Array(fatherid1 => Array(child_id1,,,),,,)
    输出格式
    Array(
        [36] => Array
        (
            [0] => 39
            [1] => 38
            [2] => 37
        )

        [0] => Array
        (
            [0] => 26
        )
    )
    */
    protected function _getGroupedIds ($a_allow = array())
    {
        $a_sorted_group = array();
        $a_grouped = $this->getGroupedList();
        $c_allow = count($a_allow);
        if (count($a_grouped) > 0)
        {
            if ($c_allow == 0)
            {
                foreach ($a_grouped as $father => $value)
                {
                    foreach ($value as $k => $v)
                    {
                        $a_sorted_group[$father][] = $k;
                    }
                }
            }
            else
            {
                foreach ($a_grouped as $father => $value)
                {
                    if (in_array($father, $a_allow))
                    {
                        foreach ($value as $k => $v)
                        {
                            if (in_array($k, $a_allow))
                            {
                                $a_sorted_group[$father][] = $k;
                            }
                        }
                    }
                }
            }
        }
        return $a_sorted_group;
    }

    /**
     * 格式化request关系数组
     *
     * @param array Output from gacl_api->sorted_groups($group_type)
     * @param array Output type desired, either 'TEXT', 'HTML', or 'ARRAY'
     * @param int Root of tree to produce
     * @param int Current level of depth
     * @param array Pass the current formatted groups object for appending via recursion.
     * @return array Array of formatted text, ordered by group id, formatted according to $type
     */
    public function formatGroups ($sorted_groups, $type = 'TEXT', $root_id = -1, $level = 0, $formatted_groups = NULL)
    {
        if (! is_array($sorted_groups))
        {
            return FALSE;
        }
        if (! is_array($formatted_groups))
        {
            $formatted_groups = array();
        }
        if (isset($sorted_groups[$root_id]))
        {
            $keys = array_keys($sorted_groups[$root_id]);
            $last_id = end($keys);
            unset($keys);
            foreach ($sorted_groups[$root_id] as $id => $name)
            {
                switch (strtoupper($type))
                {
                    case 'TEXT':
                        //Formatting optimized for TEXT (combo box) output.
                        //$spacing = ($level == 0) ? '&nbsp;' : '&nbsp;&nbsp;&nbsp;&nbsp;';
                        $spacing = self::_getSpace($level);
                        if ($id == $last_id)
                        {
                            $spacing = $spacing . '\'---- ';
                        }
                        else
                        {
                            $spacing = $spacing . '|---- ';
                        }
                        $next = $level + 1;
                        $text = $spacing . $name;
                        break;
                    case 'HTML':
                        //Formatting optimized for HTML (tables) output.
                        // $spacing = '&nbsp;&nbsp;&nbsp;&nbsp;';
                        $spacing = self::_getSpace($level);
                        if ($id == $last_id)
                        {
                            $spacing = $spacing . '\'---- ';
                        }
                        else
                        {
                            $spacing = $spacing . '|---- ';
                        }
                        $next = $level + 1;
                        $text = $spacing . " " . $name;
                        break;
                    case 'ARRAY':
                        $next = $level;
                        $text = $name;
                        break;
                    default:
                        return FALSE;
                }
                $formatted_groups[$id] = $text . ":" . $level;
                // SLog::getInst()->dump($var)
                /*
                 * Recurse if we can.
                 */
                if (($id != - 1) && (isset($sorted_groups[$id])))
                {
                    //Recursing! Level: $level
                    $formatted_groups = $this->formatGroups($sorted_groups, $type, $id, $next, $formatted_groups);
                }
                else
                {
                    //Found last branch!
                }
            }
        }
        return $formatted_groups;
    }

    protected static function _getSpace ($level)
    {
        $space = '&nbsp;';
        while ($level > 0)
        {
            $space .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            $level --;
        }
        return $space;
    }

    /**
     * 获取列表用以管理
     */
    public function getListForManage ()
    {
        $format_groups = $this->formatGroups($this->getGroupedList(), 'HTML');
        $a_result = array();
        $a_org_list = $this->getOrgList();
        foreach ($format_groups as $id => $name)
        {
            $a_info = $a_org_list[$id];
            $a_info['display_name'] = $name;
            $a_result[] = $a_info;
        }
        return $a_result;
    }

    /**
     * 获取列表以选择
     */
    public function getListForSelector ()
    {
        $a_list = $this->getGroupedList();
        $format_groups = $this->formatGroups($a_list);
        $a_result = array();
        $a_org_list = $this->getOrgList();
        foreach ($format_groups as $id => $name)
        {
            $a_info = $a_org_list[$id];
            $a_info['display_name'] = $name;
            $a_result[] = $a_info;
        }
        return $a_result;
    }

    //构造输出的树行数组
    //para $node_id 当前的构造结点
    //需要2个成员变量的支持_allowedData,_sortData
    protected function _parseNode ($node_id = 0, $a_mather_list, $a_grouped_id)
    {
        $a_return = array();
        //如果隐藏怎么，不显示
        if (isset($a_mather_list[$node_id]) && is_array($a_mather_list[$node_id]))
        {
            $a_return = $a_mather_list[$node_id];
            $a_child_list = array();
            if (isset($a_grouped_id[$node_id]) && is_array($a_grouped_id[$node_id]) && count($a_grouped_id[$node_id]) > 0)
            {
                foreach ($a_grouped_id[$node_id] as $child)
                {
                    if ($child)
                    {
                        $a_child_list[] = $this->_parseNode($child, $a_mather_list, $a_grouped_id);
                    }
                }
                if (count($a_child_list) > 0)
                {
                    usort($a_child_list, create_function('$a,$b', 'return $a["order"]-$b["order"];'));
                }
            }
            $a_return['child'] = $a_child_list;
        }
        return $a_return;
    }

    /**
     * 构造目录树
     * @param array $a_allow_ids
     */
    public function parseMenuTree ($a_allow_ids)
    {
        $a_data_allow = $this->getAllowedList($a_allow_ids);
        $a_data_grouped = $this->_getGroupedIds(array_keys($a_data_allow));
        //构造输出数组
        return $this->_parseNode(current(current($a_data_grouped)), $a_data_allow, $a_data_grouped);
    }

    protected function _findChild ($request_id, $mother_tree)
    {
        $a_child = array();
        if (isset($mother_tree[$request_id]) && is_array($mother_tree[$request_id]))
        {
            foreach ($mother_tree[$request_id] as $child_id)
            {
                if ($child_id > - 1)
                {
                    $a_child_2 = $this->_findChild($child_id, $mother_tree);
                    array_push($a_child, $child_id);
                    $a_child = array_merge($a_child, $a_child_2);
                }
            }
        }
        return $a_child;
    }

    protected function _findFather ($request_id, $mother_tree)
    {
        $a_father = array();
        if (isset($this->_orgList[$request_id]) && isset($mother_tree[$this->_orgList[$request_id]['pid']]))
        {
            $pid = 0 + $this->_orgList[$request_id]['pid'];
            $a_father_2 = array();
            if ($pid != - 1)
            {
                $a_father_2 = $this->_findFather($pid, $mother_tree);
            }
            array_push($a_father, $pid);
            $a_father = array_merge($a_father, $a_father_2);
        }
        return $a_father;
    }
}

/**
 * 图片上傳模型
 */
class SUpload
{

    const SIZE_LARGE = 790;

    const SIZE_MIDELE = 500;

    const SIZE_THUMBNAIL = 200;

    const SIZE_SMALL = 100;

    /**
     * 本地根目录
     * @var string
     */
    private $_uploadRoot;

    function __construct ()
    {
        $this->_uploadRoot = SS_DATA_PATH . "/uploads";
    }

    /**
     * 生成图片存储目录
     * @param string $pid
     * @return string
     */
    protected function _makeDir ($pid)
    {
        $dd = self::_getRelativeDirName($pid);
        $dir = $this->_uploadRoot . '/' . $dd;
        if (! is_dir($dir))
        {
            mkdir($dir, 0777);
        }
        return $dir;
    }

    /**
     * 获取相对目录
     * @param string $pid
     * @return string
     */
    protected function _getRelativeDirName ($pid)
    {
        return substr($pid, - 1);
    }

    /**
     * 上传图片
     * @param string $file
     * @param string $user_id
     */
    public function upload ($file_field, $user_id = 0)
    {
        if (! isset($_FILES[$file_field]))
        {
            return false;
        }
        $file = $_FILES[$file_field];
        if (! is_uploaded_file($file['tmp_name']))
        {
            return false;
        }
        $pid = md5($user_id . NOW . rand(10000, 99999));
        $dir_path = self::_makeDir($pid);
        $target = $dir_path . "/" . $pid;
        if (false == move_uploaded_file($file['tmp_name'], $target))
        {
            return false;
        }
        $target_large = $dir_path . "/" . $pid . "_" . self::SIZE_LARGE . ".jpg";
        $target_middle = $dir_path . "/" . $pid . "_" . self::SIZE_MIDELE . ".jpg";
        $target_thumbnail = $dir_path . "/" . $pid . "_" . self::SIZE_THUMBNAIL . ".jpg";
        $target_small = $dir_path . "/" . $pid . "_" . self::SIZE_SMALL . ".jpg";
        self::_scaleImage($target, $target_large, self::SIZE_LARGE, self::SIZE_LARGE);
        self::_scaleImage($target, $target_middle, self::SIZE_MIDELE, self::SIZE_MIDELE);
        self::_scaleImage($target, $target_thumbnail, self::SIZE_THUMBNAIL, self::SIZE_THUMBNAIL);
        self::_scaleImage($target, $target_small, self::SIZE_SMALL, self::SIZE_SMALL);
        //
        $a_info['pid'] = $pid;
        $a_info['name'] = $file['name'];
        $a_info['type'] = $file['type'];
        $a_info['size'] = $file['size'];
        $a_info['url_large'] = self::getUrl($pid, self::SIZE_LARGE);
        $a_info['url_middle'] = self::getUrl($pid, self::SIZE_MIDELE);
        $a_info['url_thumbnail'] = self::getUrl($pid, self::SIZE_THUMBNAIL);
        $a_info['url_small'] = self::getUrl($pid, self::SIZE_SMALL);
        return $a_info;
    }

    /**
     * 上传图片,保持原样
     * @param string $file
     * @param string $user_id
     */
    public function uploadOrg ($file_field, $user_id = 0)
    {
        if (! isset($_FILES[$file_field]))
        {
            return false;
        }
        $file = $_FILES[$file_field];
        if (! is_uploaded_file($file['tmp_name']))
        {
            return false;
        }
        $pid = md5($user_id . NOW . rand(10000, 99999));
        $dir_path = self::_makeDir($pid);
        $target = $dir_path . "/" . $pid . ".jpg";
        if (false == move_uploaded_file($file['tmp_name'], $target))
        {
            return false;
        }
        //
        $a_info['pid'] = $pid;
        $a_info['name'] = $file['name'];
        $a_info['type'] = $file['type'];
        $a_info['size'] = $file['size'];
        $a_info['url'] = self::getUrl($pid);
        return $a_info;
    }

    protected function _getFileName ($pid, $size_type = '')
    {
        switch ($size_type)
        {
            case self::SIZE_LARGE:
                $name = $pid . '_' . self::SIZE_LARGE;
                break;
            case self::SIZE_MIDELE:
                $name = $pid . '_' . self::SIZE_MIDELE;
                break;
            case self::SIZE_THUMBNAIL:
                $name = $pid . '_' . self::SIZE_THUMBNAIL;
                break;
            case self::SIZE_SMALL:
                $name = $pid . '_' . self::SIZE_SMALL;
                break;
            default:
                $name = $pid;
                break;
        }
        return $name;
    }

    public function getUrl ($pid, $size_type = '')
    {
        $file_name = $this->_getFileName($pid, $size_type);
        $dir_path = self::_getRelativeDirName($pid);
        return "/uploads/" . $dir_path . "/" . $file_name . ".jpg";
    }

    /**
     * Open image file
     *
     * This function will try to open image file
     *
     * @param string $file
     * @return resource
     */
    protected function _openImage ($file)
    {
        if (! extension_loaded('gd'))
        {
            return false;
        } // if
        $info = & getimagesize($file);
        if ($info)
        {
            switch ($info[2])
            {
                case IMAGETYPE_JPEG:
                    return array(
                        'type' => IMAGETYPE_JPEG ,
                        'resource' => imagecreatefromjpeg($file)
                    ); // array
                case IMAGETYPE_GIF:
                    return array(
                        'type' => IMAGETYPE_GIF ,
                        'resource' => imagecreatefromgif($file)
                    ); // array
                case IMAGETYPE_PNG:
                    return array(
                        'type' => IMAGETYPE_PNG ,
                        'resource' => imagecreatefrompng($file)
                    ); // array
            } // switch
        } // if
        return null;
    } // open_image

    /**
     * Resize input image
     *
     * @param string $input_file
     * @param string $dest_file
     * @param integer $max_width
     * @param integer $max_height
     * @return boolean
     */
    protected function _scaleImage ($input_file, $dest_file, $max_width, $max_height, $quality = 80)
    {
        $open_image = self::_openImage($input_file);
        if (is_array($open_image))
        {
            $image_type = $open_image['type'];
            $image = $open_image['resource'];
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min($max_width / $width, $max_height / $height);
            if ($scale < 1)
            {
                $new_width = floor($scale * $width);
                $new_height = floor($scale * $height);
                $tmp_img = imagecreatetruecolor($new_width, $new_height);
                $white_color = imagecolorallocate($tmp_img, 255, 255, 255);
                imagefill($tmp_img, 0, 0, $white_color);
                imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagedestroy($image);
                $image = $tmp_img;
            }
            if ($scale > 1)
            {
                $tmp_img = imagecreatetruecolor($max_width, $max_height);
                $white_color = imagecolorallocate($tmp_img, 255, 255, 255);
                imagefill($tmp_img, 0, 0, $white_color);
                imagecopy($tmp_img, $image, round(($max_width - $width) / 2), round(($max_height - $height) / 2), 0, 0, $width, $height);
                imagedestroy($image);
                $image = $tmp_img;
            } // if
            return imagejpeg($image, $dest_file, $quality);
        } // ifs
        return false;
    } // scale_image
}

/**
 * 常用的验证功能

 * @example
 * <pre>
 * $a_conf = array(
 * 'rule' => array(
 * 'a' => array(
 * 'required' => true ,
 * 'filter' => 'md5,fff::uuu'
 * ) ,
 * 'b' => array(
 * 'required' => true ,
 * 'filter' => 'intval' ,
 * 'min' => 10
 * ) ,
 * 'c' => array(
 * 'required' => true ,
 * 'reg' => '/^[0-9\+(\s]{3,}[0-9\-)\s]{2,}[0-9]$/'
 * )
 * ) ,
 * 'message' => array(
 * 'a' => array(
 * 'required' => '需要a'
 * ) ,
 * 'b' => array(
 * 'required' => '需要b' ,
 * 'min' => 'zuishao 10'
 * ) ,
 * 'c' => array(
 * 'default' => '需要c' ,
 * 'reg' => '电话号码至少6位'
 * )
 * )
 * );
 * $t = array(
 * 'a' => "yyyy" ,
 * 'b' => '01' ,
 * 'c' => '01'
 * );
 * $v = new SValidator($a_conf);
 * $r = $v->test($t);//遇到错误马上停止
 * $r = $v->check($t);//详细检查
 * var_dump($r);
 * var_dump($v->getErrorInfo());
 * var_dump($v->getQualifiedData());
 * </pre>
 *
 */
class SValidator
{

    /**
     * 规则
     * @var array
     */
    protected $_rule;

    /**
     * 错误信息
     * @var array
     */
    protected $_message;

    /**
     * 错误的原因
     * @var array
     */
    protected $_errorInfo;

    /**
     * 验证后的数据
     * @var array
     */
    protected $_qualifiedData;

    public function __construct ($a_conf)
    {
        if (isset($a_conf['rule']))
        {
            $this->loadRule($a_conf['rule']);
        }
        if (isset($a_conf['message']))
        {
            $this->loadMessage($a_conf['message']);
        }
    }

    /**
     * 加载规则
     * @param string $a_rule
     */
    public function loadRule ($a_rule)
    {
        if (! is_array($a_rule))
        {
            return false;
        }
        $this->_rule = $a_rule;
        return $this;
    }

    /**
     * 理论上_message和rule同构
     * @param array $a_message
     */
    public function loadMessage ($a_message)
    {
        if (! is_array($a_message))
        {
            return false;
        }
        $this->_message = $a_message;
        return $this;
    }

    /**
     * 记录错误
     * @param string $key
     * @param string $meg
     */
    protected function _error ($key, $msg)
    {
        $this->_errorInfo[$key] = $msg;
    }

    /**
     * 获取错误信息
     */
    public function getErrorInfo ()
    {
        return $this->_errorInfo;
    }

    /**
     * 获取可用数据
     */
    public function getQualifiedData ()
    {
        return $this->_qualifiedData;
    }

    /**
     * 遇到错误立即返回，不记录原因
     */
    public function test ($a_data)
    {
        return $this->_run($a_data, true);
    }

    /**
     * 全面检查
     */
    public function check ($a_data)
    {
        return $this->_run($a_data, false);
    }

    /**
     * 验证
     *
     * @param  array $a_data
     * @return mixed
     */
    protected function _run ($a_data, $test_only = false)
    {
        $r = true;
        //检测数据是否为空
        if (empty($this->_rule) || empty($a_data) || ! is_array($a_data))
        {
            return false;
        }
        $max = $filter = $reg = $default = $valid = '';
        reset($a_data); //过滤重复
        $a_message = &$this->_message;
        foreach ($this->_rule as $_k => $_v)
        {
            if (is_array($_v))
            {
                $required = (isset($_v['required']) && $_v['required']) ? true : false;
                $type = isset($this->_rule[$_k]['type']) ? $this->_rule[$_k]['type'] : 'string';
                $min = isset($this->_rule[$_k]['min']) ? $this->_rule[$_k]['min'] : 0;
                $max = isset($this->_rule[$_k]['max']) ? $this->_rule[$_k]['max'] : 0;
                $filter = isset($this->_rule[$_k]['filter']) ? $this->_rule[$_k]['filter'] : '';
                $valid = isset($this->_rule[$_k]['valid']) ? $this->_rule[$_k]['valid'] : '';
                $reg = isset($this->_rule[$_k]['reg']) ? $this->_rule[$_k]['reg'] : '';
                $has_default = isset($this->_rule[$_k]['default']) ? true : false;
                $default = isset($this->_rule[$_k]['default']) ? $this->_rule[$_k]['default'] : '';
            }
            else
            {
                preg_match_all('/([a-z]+)(\((\d+),(\d+)\))?/', $_v, $result);
                $type = $result[1];
                $min = $result[3];
                $max = $result[4];
            }
            if (! isset($a_data[$_k]))
            {
                //不存在的数据无需处理
                //continue;
                /* 默认值 */
                if ($required)
                {
                    if ($has_default)
                    {
                        $a_data[$_k] = function_exists($default) ? $default() : $default;
                        continue;
                    }
                    $this->_error($_k, isset($a_message[$_k]['required']) ? $a_message[$_k]['required'] : "required_field1");
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                }
                continue;
            }
            $value = $a_data[$_k];
            if ($required && $value === '')
            {
                $this->_error($_k, isset($a_message[$_k]['required']) ? $a_message[$_k]['required'] : "required_field2");
                if ($test_only)
                {
                    return false;
                }
                $r = false;
                continue;
            }
            /* 到此，说明该字段不是必填项,可以为空 */
            /* 默认值 */
            if (! $value && $default)
            {
                $a_data[$_k] = function_exists($default) ? $default() : $default;
                continue;
            }
            /* 若还是空值，则没必要往下验证长度 */
            if (! $value)
            {
                continue;
            }
            /* 先过滤，再验证 */
            if ($filter)
            {
                $funs = explode(',', $filter);
                foreach ($funs as $fun)
                {
                    function_exists($fun) && $value = $fun($value);
                }
                $a_data[$_k] = $value;
            }
            /* 大小|长度限制 */
            if ($type == 'string')
            {
                $strlen = strlen($value);
                if ($min != 0 && $strlen < $min)
                {
                    $this->_error($_k, isset($a_message[$_k]['min']) ? $a_message[$_k]['min'] : "length_lt_min");
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                    continue;
                }
                if ($max != 0 && $strlen > $max)
                {
                    $this->_error($_k, isset($a_message[$_k]['max']) ? $a_message[$_k]['max'] : "length_gt_max");
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                    continue;
                }
            }
            else
            {
                if ($min != 0 && $value < $min)
                {
                    $this->_error($_k, isset($a_message[$_k]['min']) ? $a_message[$_k]['min'] : "value_lt_min");
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                    continue;
                }
                if ($max != 0 && $value > $max)
                {
                    $this->_error($_k, isset($a_message[$_k]['max']) ? $a_message[$_k]['max'] : "value_gt_max");
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                    continue;
                }
            }
            /* 正则 */
            if ($reg)
            {
                if (! preg_match($reg, $value))
                {
                    $this->_error($_k, isset($a_message[$_k]['reg']) ? $a_message[$_k]['reg'] : "check_match_error");
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                    continue;
                }
            }
            /* 自定义验证方法，只支持函数 */
            if ($valid && function_exists($valid))
            {
                $result = $valid($value);
                if ($result !== true)
                {
                    $this->_error($_k, isset($a_message[$_k]['valid']) ? $a_message[$_k]['valid'] : $result);
                    if ($test_only)
                    {
                        return false;
                    }
                    $r = false;
                    continue;
                }
            }
        }
        $this->_qualifiedData = $a_data;
        return $r;
    }
}

/**
 * 字符串处理类
 */
class SString
{

    public static function jsOutputFormat ($str)
    {
        $str = trim($str);
        $str = str_replace('\\s\\s', '\\s', $str);
        $str = str_replace(chr(10), '', $str);
        $str = str_replace(chr(13), '', $str);
        $str = str_replace('   ', '', $str);
        $str = str_replace('\\', '\\\\', $str);
        $str = str_replace('"', '\\"', $str);
        $str = str_replace('\\\'', '\\\\\'', $str);
        $str = str_replace("'", "\'", $str);
        return $str;
    }

    /**
     * json 编码
     * @param array $data
     */
    public static function jsonEncode ($a_data)
    {
        if (SS_DEFAULT_CHARACTER_SET == 'GBK')
        {
            $a_data = SConverter::gbk2utf8($a_data);
        }
        //否则是utf8
        return self::_json_encode($a_data);
    }

    /**
     * json 解码
     * @param string $data
     */
    static function jsonDecode ($json_string)
    {
        $a_data = json_decode($json_string, true);
        if (SS_DEFAULT_CHARACTER_SET == 'GBK')
        {
            $a_data = SConverter::utf82gbk($a_data);
        }
        //否则是utf8
        return $a_data;
    }

    /**
     * 过滤不需要的tag
     * @example
     * $str ='<img stc="javascript:alert(1);" /> aaa <font>bbb</font>';
     * var_dump(strip_tags_attributes($str,array('<img>','<font>')));
     *
     */
    static function stripTagsAttributes ($source, $a_allowed_tags = array(), $a_disabled_attributes = array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavaible', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragdrop', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterupdate', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmoveout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload'))
    {
        if (empty($a_disabled_attributes))
        {
            return strip_tags($source, implode('', $a_allowed_tags));
        }
        return preg_replace('/<(.*?)>/ie', "'<' . preg_replace(array('/javascript:[^\"\']*/i', '/(" . implode('|', $a_disabled_attributes) . ")[ \\t\\n]*=[ \\t\\n]*[\"\'][^\"\']*[\"\']/i', '/\s+/'), array('', '', ' '), stripslashes('\\1')) . '>'", strip_tags($source, implode('', $a_allowed_tags)));
    }

    protected static function _json_encode_string ($in_str)
    {
        $in_str = self::jsOutputFormat($in_str);
        mb_internal_encoding("UTF-8");
        $convmap = array(
            0x80 ,
            0xFFFF ,
            0 ,
            0xFFFF
        );
        $str = "";
        for ($i = mb_strlen($in_str) - 1; $i >= 0; $i --)
        {
            $mb_char = mb_substr($in_str, $i, 1);
            $match = array();
            if (mb_ereg("&#(\\d+);", mb_encode_numericentity($mb_char, $convmap, "UTF-8"), $match))
            {
                $str = sprintf("\\u%04x", $match[1]) . $str;
            }
            else
            {
                $str = $mb_char . $str;
            }
        }
        return $str;
    }

    protected static function _json_encode ($arr)
    {
        $json_str = "";
        if (is_array($arr))
        {
            $pure_array = true;
            $array_length = count($arr);
            if (0 == $array_length)
            {
                $json_str = "{}";
            }
            else
            {
                for ($i = 0; $i < $array_length; $i ++)
                {
                    if (! isset($arr[$i]))
                    {
                        $pure_array = false;
                        break;
                    }
                }
                if ($pure_array)
                {
                    $json_str = "[";
                    $temp = array();
                    for ($i = 0; $i < $array_length; $i ++)
                    {
                        $temp[] = sprintf("%s", self::_json_encode($arr[$i]));
                    }
                    $json_str .= implode(",", $temp);
                    $json_str .= "]";
                }
                else
                {
                    $json_str = "{";
                    $temp = array();
                    foreach ($arr as $key => $value)
                    {
                        $temp[] = sprintf("\"%s\":%s", $key, self::_json_encode($value));
                    }
                    $json_str .= implode(",", $temp);
                    $json_str .= "}";
                }
            }
        }
        else
        {
            if (is_string($arr))
            {
                $json_str = "\"" . self::_json_encode_string($arr) . "\"";
            }
            else
                if (is_numeric($arr))
                {
                    $json_str = $arr;
                }
                else
                {
                    $json_str = "\"" . self::_json_encode_string($arr) . "\"";
                }
        }
        return $json_str;
    }

    /**
     * 建立一个通用的链接显示方法
     *
     * @param  $content     需要过滤的代码
     * @return string
     */
    public static function textOnly ($content)
    {
        $a_allowed_tags = array();
        return self::stripTagsAttributes($content, $a_allowed_tags);
    }

    /**
     * 过滤空白字符串
     *
     * @param   string
     * @return  string
     */
    public static function removeInvisibleCharacters ($str)
    {
        // every control character except newline (dec 10), carriage return (dec 13), and horizontal tab (dec 09),
        $non_displayables = array(
            '/%0[0-8bcef]/' ,  // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/' ,  // url encoded 16-31
            '/[\x00-\x08]/' ,  // 00-08
            '/\x0b/' ,
            '/\x0c/' ,  // 11, 12
            '/[\x0e-\x1f]/' // 14-31
        );
        do
        {
            $cleaned = $str;
            $str = preg_replace($non_displayables, '', $str);
        }
        while ($cleaned != $str);
        return $str;
    }

    /**
     * 获取一段文字的首字母或者拼音的第一个
     *
     * @param string $py_key
     */
    public static function getFirstLetter ($py_key)
    {
        $match = null;
        if (preg_match("/^[a-zA-Z0-9]{1}(.*)$/", $py_key, $match))
        {
            return substr($py_key, 0, 1);
        }
        $pinyin = 65536 + self::_pinyin($py_key);
        if (45217 <= $pinyin && $pinyin <= 45252)
        {
            $zimu = "A";
            return $zimu;
        }
        if (45253 <= $pinyin && $pinyin <= 45760)
        {
            $zimu = "B";
            return $zimu;
        }
        if (45761 <= $pinyin && $pinyin <= 46317)
        {
            $zimu = "C";
            return $zimu;
        }
        if (46318 <= $pinyin && $pinyin <= 46825)
        {
            $zimu = "D";
            return $zimu;
        }
        if (46826 <= $pinyin && $pinyin <= 47009)
        {
            $zimu = "E";
            return $zimu;
        }
        if (47010 <= $pinyin && $pinyin <= 47296)
        {
            $zimu = "F";
            return $zimu;
        }
        if (47297 <= $pinyin && $pinyin <= 47613)
        {
            $zimu = "G";
            return $zimu;
        }
        if (47614 <= $pinyin && $pinyin <= 48118)
        {
            $zimu = "H";
            return $zimu;
        }
        if (48119 <= $pinyin && $pinyin <= 49061)
        {
            $zimu = "J";
            return $zimu;
        }
        if (49062 <= $pinyin && $pinyin <= 49323)
        {
            $zimu = "K";
            return $zimu;
        }
        if (49324 <= $pinyin && $pinyin <= 49895)
        {
            $zimu = "L";
            return $zimu;
        }
        if (49896 <= $pinyin && $pinyin <= 50370)
        {
            $zimu = "M";
            return $zimu;
        }
        if (50371 <= $pinyin && $pinyin <= 50613)
        {
            $zimu = "N";
            return $zimu;
        }
        if (50614 <= $pinyin && $pinyin <= 50621)
        {
            $zimu = "O";
            return $zimu;
        }
        if (50622 <= $pinyin && $pinyin <= 50905)
        {
            $zimu = "P";
            return $zimu;
        }
        if (50906 <= $pinyin && $pinyin <= 51386)
        {
            $zimu = "Q";
            return $zimu;
        }
        if (51387 <= $pinyin && $pinyin <= 51445)
        {
            $zimu = "R";
            return $zimu;
        }
        if (51446 <= $pinyin && $pinyin <= 52217)
        {
            $zimu = "S";
            return $zimu;
        }
        if (52218 <= $pinyin && $pinyin <= 52697)
        {
            $zimu = "T";
            return $zimu;
        }
        if (52698 <= $pinyin && $pinyin <= 52979)
        {
            $zimu = "W";
            return $zimu;
        }
        if (52980 <= $pinyin && $pinyin <= 53640)
        {
            $zimu = "X";
            return $zimu;
        }
        if (53689 <= $pinyin && $pinyin <= 54480)
        {
            $zimu = "Y";
            return $zimu;
        }
        if (54481 <= $pinyin && $pinyin <= 62289)
        {
            $zimu = "Z";
            return $zimu;
        }
        return 0;
        //
        $zimu = $py_key;
        return $zimu;
    }

    protected function _pinyin ($pysa)
    {
        $pyi = "";
        $i = 0;
        for (; $i < strlen($pysa); $i ++)
        {
            $_obfuscate_8w = ord(substr($pysa, $i, 1));
            if (160 < $_obfuscate_8w)
            {
                $_obfuscate_Bw = ord(substr($pysa, $i ++, 1));
                $_obfuscate_8w = $_obfuscate_8w * 256 + $_obfuscate_Bw - 65536;
            }
            $pyi .= $_obfuscate_8w;
        }
        return $pyi;
    }
}

/**
 * 常用的转换器集合
 */
class SConverter
{

    /**
     * 转换gbk数据为utf-8
     *
     * @param maxie $data
     */
    static function gbk2utf8 ($data)
    {
        if (is_array($data))
        {
            return array_map(array(
                'SConverter' ,
                "gbk2utf8"
            ), $data);
        }
        return @iconv('gbk', 'utf-8', $data);
    }

    /**
     * 转换utf-8数据为gbk
     *
     * @param maxie $data
     */
    static function utf82gbk ($data)
    {
        if (is_array($data))
        {
            return array_map(array(
                'SConverter' ,
                "utf82gbk"
            ), $data);
        }
        return @iconv('utf-8', 'gbk', $data);
    }
}

class SUri
{

    static public function parseUrl ($url)
    {
        $arr = parse_url($url);
        if (isset($arr['query']))
        {
            $arr['query_params'] = self::parseQuery($arr['query']);
        }
        return $arr;
    }

    static function parseQuery ($var)
    {
        /**
         * Use this function to parse out the query array element from
         * the output of parse_url().
         */
        $var = html_entity_decode($var);
        $var = explode('&', $var);
        $arr = array();
        foreach ($var as $val)
        {
            $x = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $var);
        return $arr;
    }

    static function buildQuery ($a_param)
    {
        /**
         * XXX 这里需要过滤全局变量
         */
        return http_build_query($a_param);
    }

    /**
     * 建立一个通用的链接显示方法
     *
     * @uses ://echo LinksHelper::buildUri('aaa','bbb',array('p1'=>'v1','p2'=>'v2'));
     *
     * @param string $controller    控制器的名称
     * @param string $action        action的名称
     * @param string|array $otherParams    其他参数
     * @return string
     */
    public static function buildUri ($controller = "", $action = "", $other_params = "")
    {
        if (! is_string($controller))
        {
            $controller = strval($controller);
        }
        if (! is_string($action))
        {
            $action = strval($action);
        }
        if (empty($other_params))
        {
            $s_other_params = '';
        }
        elseif (is_array($other_params))
        {
            $s_other_params = '?' . http_build_query($other_params);
        }
        else
        {
            $s_other_params = '?' . $other_params;
        }
        $s_uri = "http://" . SS_DOMAIN . "/";
        if ("" != $controller)
        {
            $s_uri .= $controller . '/';
            if ("" != $action)
            {
                $s_uri .= $action . '';
            }
        }
        $s_uri .= $s_other_params;
        return $s_uri;
    }
}


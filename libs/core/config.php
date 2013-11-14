<?php
defined('SS_TOKEN') or die('NO_T');
/**
 * 检测常量
 */
defined('SS_ROOT') or die("libs_miss");
defined('SS_TOKEN') or die("token_miss");
defined('SS_ROLE') or die("role_miss");
/**
 * 定义当前路径
 */
define('SS_LIBS_CORE_ROOT', dirname(__FILE__));
/**
 * 这里存放全部的核心功能的配置
 *
 * @var string
 */
define('SS_LIBS_CORE_TOKEN', 'libs_core');
/**
 * 默认编码
 *
 * @var string
 */
define('SS_DEFAULT_CHARACTER_SET', 'UTF-8');
define('SS_DEFAULT_CHARACTER_SET_DB', 'UTF8');
/**
 * 获取服务器角色信息,为程序定义在三种环境下，由服务器的SG_ROLE产生
 *
 * SS_WEB_DEVELOP    开发环境
 * SS_WEB_TEST       测试环境
 * SS_WEB_PRODUCT    生产环境
 */
switch (SS_ROLE)
{
    case "SS_ROLE_DEVELOP":
        ini_set("error_reporting", E_ALL);
        define('DEBUG_MODE', true);
        break;
    case "SS_ROLE_TESTER":
        define('DEBUG_MODE', false);
        break;
    case "SS_ROLE_PRODUCT":
    	ini_set("error_reporting", 0);
        define('DEBUG_MODE', false);
        break;
    default:
        die("server_role_error");
        exit(0);
        break;
}
define('SS_LIBS_CORE_SALT_ENCRYPT_KEY', 'sgsnssecretkeyfortest');
define('IS_POST', isset($_SERVER['REQUEST_METHOD']) ? (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') : 0);
define('PHP_SELF', htmlentities(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']));
/**
 * 数据类型
 */
define('SS_DATA_TYPE_INT', 1);
define('SS_DATA_TYPE_STRING', 2);
/**
 * 时区设置
 */
date_default_timezone_set('PRC');
define('NOW', time());
/**
 * 安全链接
 */
if (! empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']))
{
    define('SS_URI_PERFIX', 'https://');
}
else
{
    define('SS_URI_PERFIX', 'http://');
}
/**
 * 域名
 */
if (! empty($_SERVER['HTTP_HOST']))
{
    define('SS_DOMAIN', $_SERVER["HTTP_HOST"]);
    define('SS_HOME_PAGE', SS_URI_PERFIX . SS_DOMAIN);
}
/**
 * 缓存目录数量，根据预期缓存文件数调整，开根号即可
 * @var int
 */
define('SS_CACHE_DIR_NUM', 256); //
/**
 * 文件权限
 */
define('SS_FILE_READ_MODE', 0644);
define('SS_FILE_WRITE_MODE', 0666);
define('SS_DIR_READ_MODE', 0755);
define('SS_DIR_WRITE_MODE', 0777);
/**
 * 文件读写模式
 */
define('SS_FOPEN_READ', 'rb');
define('SS_FOPEN_READ_WRITE', 'r+b');
define('SS_FOPEN_WRITE_CREATE_DESTRUCTIVE', 'wb');
define('SS_FOPEN_READ_WRITE_CREATE_DESTRUCTIVE', 'w+b');
define('SS_FOPEN_WRITE_CREATE', 'ab');
define('SS_FOPEN_READ_WRITE_CREATE', 'a+b');
define('SS_FOPEN_WRITE_CREATE_STRICT', 'xb');
define('SS_FOPEN_READ_WRITE_CREATE_STRICT', 'x+b');
/**
 * 简化的对外输出的错误代码
 */
define('S00000', '操作成功');
define('E00000', '系统繁忙');
define('E00001', '参数错误');
define('E00002', '非法操作');
define('E00003', '无权限');
define('E00004', '抱歉，请先登录');
define('E00005', '验证码有错误');

/**
 * 异常定义
 */
function ss_add_exception ($code, $msg)
{
    $GLOBALS['SS_EXCEPTION'][$code] = $msg;
}
/**
 * 系统部分的异常,以0x0000 开头
 */
ss_add_exception(0x00000000, '系统初始化');
/**
 * 通用的db异常
 */
ss_add_exception(0x00001000, '替换失败');
ss_add_exception(0x00001001, '插入失败');
ss_add_exception(0x00001002, '更新失败');
ss_add_exception(0x00001003, '删除失败');
ss_add_exception(0x00001004, '查询失败');
ss_add_exception(0x00001005, '更新的字段有误');
ss_add_exception(0x00001006, '字段为空');
ss_add_exception(0x00001007, '更新条件不存在');
ss_add_exception(0x00001008, '删除条件不存在');
/**
 * 框架的异常
 */
ss_add_exception(0x00002001, 'the_keyname_is_undefined_in_request');
ss_add_exception(0x00002002, 'the_controller_file_is_not_found');
ss_add_exception(0x00002003, 'the_controller_is_not_found');
ss_add_exception(0x00002004, 'the_action_file_is_not_found');
ss_add_exception(0x00002005, 'the_action_is_not_found');
ss_add_exception(0x00002006, 'the_task_file_is_not_found');
ss_add_exception(0x00002007, 'the_task_is_not_found');
ss_add_exception(0x00002008, 'the_task_is_invalid');
ss_add_exception(0x00002009, 'force_reroute');
ss_add_exception(0x00002010, 'the_keyname_is_undefined_in_helper');
ss_add_exception(0x00002011, 'the_keyname_is_defined_in_helper');
ss_add_exception(0x00002012, 'the_viewdata_must_be_an_array');
ss_add_exception(0x00002013, 'the_helper_file_is_not_found');
ss_add_exception(0x00002014, 'the_helper_is_not_found');
ss_add_exception(0x00002015, 'the_helper_is_invalid');
ss_add_exception(0x00002016, 'the_action_has_exception');
/**
 * 模型的异常
 */
define('SS_EX_CREATE', 0x00003000);
ss_add_exception(SS_EX_CREATE, 'ex_create');
define('SS_EX_UPDATE', 0x00003001);
ss_add_exception(SS_EX_UPDATE, 'ex_update');
define('SS_EX_INIT', 0x00003002);
ss_add_exception(SS_EX_INIT, 'ex_init');
define('SS_EX_ATTACH', 0x00003003);
ss_add_exception(SS_EX_ATTACH, 'ex_attach');
define('SS_EX_COUNT', 0x00003004);
ss_add_exception(SS_EX_COUNT, 'ex_count');
define('SS_EX_LIST', 0x00003005);
ss_add_exception(SS_EX_LIST, 'ex_list');
define('SS_EX_DROP', 0x00003006);
ss_add_exception(SS_EX_DROP, 'ex_drop');
define('SS_EX_MOVE', 0x00003007);
ss_add_exception(SS_EX_MOVE, 'ex_move');
define('SS_EX_GET', 0x00003008);
ss_add_exception(SS_EX_GET, 'ex_get');
define('SS_EX_SEARCH', 0x00003009);
ss_add_exception(SS_EX_SEARCH, 'ex_search');
define('SS_EX_PARAM', 0x00003010);
ss_add_exception(SS_EX_PARAM, 'ex_param');
/**
 * 获取全局值,key为大写的那种
 * @param string $key
 * @return maxie
 */
function ss_get_global ($key)
{
    $result = $GLOBALS;
    if (! empty($key))
    {
        $a_address = explode('.', $key);
        foreach ($a_address as $value)
        {
            $result = $result[strtoupper($value)];
        }
    }
    return $result;
}
/**
 * 全局类搜索路径：二维数组(命名空间(类的分类)=>该类路径集合)[Model->]
 * 命名空间:port,model,driver 等,其他少数的类自行加载
 *
 * @var array
 */
$GLOBALS['SS_CLASS_PATHS'] = array();
$GLOBALS['SS_CLASS_PATHS']['Data'] = array(); //数据模型
$GLOBALS['SS_CLASS_PATHS']['Model'] = array(); //实体模型

/**
 * 增加全局搜索路径
 */
function ss_add_package ($path)
{
    /**
     * 加载模块功能公共配置
     */
    $f_src = $path . '/config.php';
    if (file_exists($f_src))
    {
        require_once $f_src;
        $GLOBALS['SS_CLASS_PATHS']['Data'][] = $path . '/data';
        $GLOBALS['SS_CLASS_PATHS']['Model'][] = $path . '/model';
        $GLOBALS['SS_CLASS_PATHS']['Helper'][] = $path . '/helper';
    }
    else
    {
        $a_p = explode(DIRECTORY_SEPARATOR, $f_src);
        $s_p = $a_p[max(count($a_p) - 1, 0)];
        echo "package {$s_p} not found!";
        die();
    }
}

/**
 * 自动加载函数的实现
 *
 * @param string $classname
 * @return void
 */
function autoload ($classname)
{
    $f_src = SS_LIBS_CORE_ROOT . "/{$classname}.class.php";
    if (file_exists($f_src))
    {
        require_once $f_src;
    }
    else
    {
        $a_paths = ss_get_global('ss_class_paths');
        foreach ($a_paths as $s_class_type => $a_class_type_paths)
        {
            if ($s_class_type === substr($classname, 0 - strlen($s_class_type)))
            {
                foreach ($a_class_type_paths as $s_class_type_path)
                {
                    $f_src = $s_class_type_path . "/{$classname}.class.php";
                    if (file_exists($f_src))
                    {
                        require_once $f_src;
                        break;
                    }
                }
                break;
            }
        }
    }
}
/**
 * 设置自动加载
 */
spl_autoload_register('autoload');

/**
 * 获取数据句柄
 * @param boolean $is_persistent
 * @return PDO
 */
function ss_connect_db ($is_persistent = false)
{
    if (function_exists('config_db'))
    {
        $pdo = config_db($is_persistent);
    }
    else
    {
        $a_conf = ss_get_global('ss_db_conf');
        if (empty($a_conf))
        {
            return false;
        }
        $con = array();
        if ($is_persistent === true)
        {
            $con[PDO::ATTR_PERSISTENT] = true;
        }
        $pdo = new Pdo("mysql:host=" . $a_conf['host'] . ";port=" . $a_conf['port'] . ";dbname=" . $a_conf['dbname'], $a_conf['user'], $a_conf['passwd'], $con);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec('SET NAMES ' . SS_DEFAULT_CHARACTER_SET_DB);
    return $pdo;
}

/**
 * 导入一个包
 *
 * @return    void
 */
function import ()
{
    $c = func_get_args();
    if (empty($c))
    {
        return;
    }
    foreach ($c as $item)
    {
        $item = str_replace('.', '/', $item);
        require_once SS_LIBS_PATH . '/' . $item . "/load.php";
    }
}
/**
 * 加载核心功能公共配置
 */
require_once SS_LIBS_CORE_ROOT . '/B.class.php';
require_once SS_LIBS_CORE_ROOT . '/C.class.php';
require_once SS_LIBS_CORE_ROOT . '/F.function.php';

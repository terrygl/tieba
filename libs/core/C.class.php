<?php
defined('SS_LIBS_CORE_TOKEN') or die('core_miss');

/**
 * 定义应用前端控制器。
 * 负责完成环境信息的收集，工作流的初始化，并将控制权转交由路由器做初始化派发工作。
 *
 * @author dengqianzhong
 */
class MyApp
{

    /**
     * 起始任务。
     */
    const TASK_TYPE_BEGINNING = 1;

    /**
     * 收尾任务。
     */
    const TASK_TYPE_ENDING = 2;

    /**
     * 当前运行时的路由解析器。
     *
     * @var MyRouter
     */
    protected $_router;

    /**
     * 保持环境信息收集控件的实例。
     *
     * @var MyRequest
     */
    protected $_request;

    /**
     * 保持信息回应控件的实例。
     *
     * @var MyResponse
     */
    protected $_response;

    /**
     * 存储起始任务列表。
     *
     * @var array
     */
    protected $_beginningTasks;

    /**
     * 存储收尾任务列表。
     *
     * @var array
     */
    protected $_endingTasks;

    /**
     * 根目录文件的存放路径 。
     *
     * @var string
     */
    protected $_documentRoot;

    /**
     * 存储用于决定公共任务的存放路径 。
     *
     * @var string
     */
    protected $_taskRoot;

    /**
     * 存储用于选用应用帮助器的存放路径 。
     *
     * @var string
     */
    protected $_helperRoot;

    /**
     * 存储用于决定主控制器的存放路径 。
     *
     * @var string
     */
    protected $_appRoot;

    /**
     * 存储唯一实例。
     *
     * @var    MyApp
     */
    protected static $_instance;

    /**
     * 允许重新路由多少次
     * @var int
     */
    protected $_dispatchMax;

    /**
     * 是否开始分发重新
     * @var bool
     */
    protected $_dispatchFlag;

    /**
     * 帮助器
     * @var bool
     */
    protected $_helpers;

    /**
     * 调试用
     * @var bool
     */
    protected $_markers;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @return void
     */
    protected function __construct ()
    {
        $this->_router = new MyRouter();
        $this->_request = new MyRequest();
        $this->_response = new MyResponse();
        $this->_beginningTasks = array();
        $this->_endingTasks = array();
        $this->_dispatchMax = 3;
        $this->_dispatchFlag = false;
    }

    /**
     * 设置根路径。
     *
     * @param  string    $name
     * @return MyApp
     */
    public function setDocumentRoot ($name)
    {
        if (! is_string($name))
        {
            $name = strval($name);
        }
        $this->_documentRoot = $name;
        return $this;
    }

    /**
     * 设controller的存放路径。
     *
     * @param  string    $name
     * @return MyApp
     */
    public function setTaskRoot ($name)
    {
        if (! is_string($name))
        {
            $name = strval($name);
        }
        $this->_taskRoot = $name;
        return $this;
    }

    /**
     * 设helper的存放路径。
     *
     * @param  string        $name
     * @return MyApp
     */
    public function setHelperRoot ($name)
    {
        if (! is_string($name))
        {
            $name = strval($name);
        }
        $this->_helperRoot = $name;
        return $this;
    }

    /**
     * 设app的存放路径。
     *
     * @param  string    $name
     * @return MyApp
     */
    public function setAppRoot ($name)
    {
        if (! is_string($name))
        {
            $name = strval($name);
        }
        $this->_appRoot = $name;
        return $this;
    }

    /**
     * 执行起始任务列表。
     *
     * @return MyApp
     */
    protected function _invokeBeginningTasks ()
    {
        for ($ii = 0, $jj = count($this->_beginningTasks); $ii < $jj; $ii ++)
        {
            $this->_beginningTasks[$ii]->execute(MyApp::TASK_TYPE_BEGINNING);
        }
        return $this;
    }

    /**
     * 执行后置任务列表。
     *
     * @return MyApp
     */
    protected function _invokeEndingTasks ()
    {
        for ($ii = 0, $jj = count($this->_endingTasks); $ii < $jj; $ii ++)
        {
            $this->_endingTasks[$ii]->execute(MyApp::TASK_TYPE_ENDING);
        }
        return $this;
    }

    /**
     * 将指定的任务实例化。
     *
     * @param  string            $name
     * @return MyTask
     */
    protected function _instantTask ($name)
    {
        $taskRoot = $this->_taskRoot;
        if (empty($taskRoot))
        {
            //默认就在根目录
            $taskRoot = $this->_router->getDocumentRoot();
        }
        $taskPath = $taskRoot . DIRECTORY_SEPARATOR . $name . '.class.php';
        if (is_file($taskPath) && is_readable($taskPath))
        {
            require_once $taskPath;
            $taskClass = $name;
            if (! class_exists($taskClass))
            {
                throw new SException($taskClass, 0x00002007);
            }
            if (! is_subclass_of($taskClass, 'MyTask'))
            {
                throw new SException($taskClass, 0x00002008);
            }
            return new $taskClass($this);
        }
        else
        {
            throw new SException($taskPath, 0x00002006);
        }
    }

    /**
     * 注册起始任务。
     *
     * 注意：目前暂时不考虑同任务重复添加导致错误发生的可能。
     *
     * @param  string       $name
     * @return MyApp
     */
    public function registerBeginningTask ($name)
    {
        $this->_beginningTasks[] = $this->_instantTask($name);
        return $this;
    }

    /**
     * 注册收尾任务。
     *
     * 注意：目前暂时不考虑同任务重复添加导致错误发生的可能。
     *
     * @param  string       $name
     * @return MyApp
     */
    public function registerEndingTask ($name)
    {
        $this->_endingTasks[] = $this->_instantTask($name);
        return $this;
    }

    /**
     * 注册帮助器。
     *
     * @param  string  $name
     * @return MyApp
     */
    public function registerHelper ($name, $value)
    {
        $s_id = md5($name, true);
        $this->_helpers[$s_id] = $value;
        return $this;
    }

    /**
     * 注册帮助器。
     *
     * @param  string  $name
     * @return MyApp
     */
    public function unRegisterHelper ($name)
    {
        $this->_helpers[] = $this->_instantTask($name);
        return $this;
    }

    /**
     * 注册帮助器。
     *
     * @param  string  $name
     * @return MyHelper
     */
    public function loadHelper ($name)
    {
        $s_id = md5($name, true);
        if (array_key_exists($s_id, $this->_helpers))
        {
            $helperRoot = $this->_helperRoot;
            if (empty($helperRoot))
            {
                //默认就在根目录
                $helperRoot = $this->_router->getDocumentRoot();
            }
            $helperClass = $this->_helpers[$s_id];
            $helperPath = $helperRoot . DIRECTORY_SEPARATOR . $helperClass . '.class.php';
            if (is_file($helperPath) && is_readable($helperPath))
            {
                require_once $helperPath;
                if (! class_exists($helperClass))
                {
                    throw new SException($helperClass, 0x00002014);
                }
                if (! is_subclass_of($helperClass, 'MyHelper'))
                {
                    throw new SException($helperClass, 0x00002015);
                }
                return new $helperClass($this);
            }
            else
            {
                throw new SException($helperPath, 0x00002013);
            }
        }
    }

    /**
     * 调用控制器。
     */
    protected function _invokeController ()
    {
        $controllerRoot = $this->_appRoot;
        if (empty($controllerRoot))
        {
            //默认就在根目录
            $controllerRoot = $this->_documentRoot;
        }
        $controllerClass = $this->getRouter()
            ->getController();
        $controllerPath = $controllerRoot . DIRECTORY_SEPARATOR . $controllerClass . '.class.php';
        if (is_file($controllerPath) && is_readable($controllerPath))
        {
            require_once $controllerPath;
            if (class_exists($controllerClass) && is_subclass_of($controllerClass, 'MyController'))
            {
                $this->mark("controller:{$controllerClass} begin");
                new $controllerClass($this);
                $this->mark("controller:{$controllerClass} end", false);
            }
            else
            {
                throw new SException($controllerClass, 0x00002003);
            }
        }
        else
        {
            throw new SException($controllerPath, 0x00002002);
        }
    }

    /**
     * 执行前端控制逻辑。
     *
     * @return void
     */
    public function run ()
    {
        $this->mark('app_run_begin');
        $s_request_uri = $_SERVER['REQUEST_URI'];
        $a_request_uri = explode("?", $s_request_uri);
        $a_request_uri = explode("/", $a_request_uri[0]);
        array_shift($a_request_uri);
        if (isset($a_request_uri[0]) && preg_match('/^[a-z]/', $a_request_uri[0], $matches))
        {
            $f_ctl = $this->_router->getControllerField();
            $this->_request->set($f_ctl, $a_request_uri[0]);
            array_shift($a_request_uri);
            if (isset($a_request_uri[0]) && preg_match('/^[a-z]/', $a_request_uri[0], $matches))
            {
                $f_act = $this->getRouter()
                    ->getActionField();
                $this->_request->set($f_act, $a_request_uri[0]);
                array_shift($a_request_uri);
            }
        }
        $this->_request->set('_OTHER_DATA_REQUEST_URI_', $a_request_uri);
        $_dispatch_count = 1;
        $msg = '';
        try
        {
            //执行跟输入无关的任务
            $this->mark('app_beginning_tasks_begin');
            $this->_invokeBeginningTasks();
            $this->mark('app_beginning_tasks_end', false);
            $this->_dispatchFlag = true;
            while ($_dispatch_count <= $this->_dispatchMax)
            {
                try
                {
                    //执行主控制动作
                    $this->_router->parse($this->_request);
                    $this->_invokeController();
                }
                catch (Exception $e)
                {
                    if (0x00002009 == $e->getCode())
                    {
                        $_dispatch_count ++;
                        continue;
                    }
                    else
                    {
                        throw new Exception($e->getMessage(), $e->getCode());
                    }
                }
                break;
            }
            //
            $this->mark('app_ending_tasks_begin');
            $this->_invokeEndingTasks();
            $this->mark('app_ending_tasks_end', false);
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
        }
        $this->mark("app_run_end_with_msg:$msg", false);
        $this->_debug();
    }

    /**
     * 重新路由
     *
     * @param string $ctl
     * @param string $act
     */
    public function reRoute ($ctl, $act = '')
    {
        $f_ctl = $this->getRouter()
            ->getControllerField();
        $this->getRequest()
            ->set($f_ctl, $ctl);
        $f_act = $this->getRouter()
            ->getActionField();
        $this->getRequest()
            ->set($f_act, $act);
        if (true == $this->_dispatchFlag)
        {
            $message = "reroute_to {$ctl}, {$act}";
            throw new Exception($message, 0x00002009);
        }
    }

    /**
     * 获取当前运行时的路由解释器。
     *
     * @return MyRouter
     */
    public function getRouter ()
    {
        return $this->_router;
    }

    /**
     * 获取保持的环境数据收集控件实例。
     *
     * @return MyRequest
     */
    public function getRequest ()
    {
        return $this->_request;
    }

    /**
     * 获取保持的信息回应控件的实例。
     *
     * @return MyResponse
     */
    public function getResponse ()
    {
        return $this->_response;
    }

    protected $_markLevel = 1;

    public function mark ($name, $is_begin = true)
    {
        if (1 || DEBUG_MODE)
        {
            if (true == $is_begin)
            {
                $this->_markLevel ++;
                $level = $this->_markLevel;
            }
            else
            {
                $level = $this->_markLevel;
                if (1 < $this->_markLevel)
                {
                    $this->_markLevel --;
                }
            }
            $this->_markers[] = array(
                'l' => $level ,
                'n' => $name ,
                't' => microtime() ,
                'mu' => memory_get_usage() ,
                'mp' => memory_get_peak_usage()
            );
        }
    }

    protected function _debug ()
    {
        list ($bsm, $bss) = explode(' ', ss_get_global('ss_boot_up_mark'));
        $s = '<pre>Debug @';
        $s .= date('Y-m-d H:i:s', $bss) . " \tsystem_boot_up\n";
        foreach ($this->_markers as $v)
        {
            list ($sm, $ss) = explode(' ', $v['t']);
            $t = ($ss - $bss) + ($sm - $bsm);
            $l = ' ';
            for ($i = 1; $i < $v['l']; $i ++)
            {
                $l .= "\t";
            }
            $s .= "(" . $v['mu'] . "," . $v['mp'] . ") " . $t . $l . $v['n'] . " \n";
        }
        $s .= '</pre>';
        if (DEBUG_MODE)
        {
            //SLog::getInst()->log('routor_debug:',$s,'info');
        }
    }

    /**
     * 获取唯一实例。
     *
     * @return MyApp
     */
    static public function getInstance ()
    {
        if (null === MyApp::$_instance)
        {
            MyApp::$_instance = new MyApp();
        }
        return MyApp::$_instance;
    }
}

/**
 * 定义控制器的基础行为抽象类
 */
abstract class MyController
{

    /**
     * 保存当前运行时的前端控制器。
     *
     * @var MyApp
     */
    protected $_app;

    /**
     * front request
     *
     * @var MyRequest
     */
    protected $_request;

    /**
     * front response
     *
     * @var MyResponse
     */
    protected $_response;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @param  MyApp $app
     * @return void
     */
    final public function __construct (MyApp $app)
    {
        $this->_app = &$app;
        $this->_request = &$app->getRequest();
        $this->_response = &$app->getResponse();
        //
        $action = $app->getRouter()
            ->getAction();
        $real_action = $this->_recognize($action);
        if (empty($real_action) || ! method_exists($this, $real_action))
        {
            $app->mark('fail_recognize:' . $real_action);
            $app->mark('goto_reroute', false);
            $default_controller = $app->getRouter()
                ->getDefaultController();
            return $app->reRoute($default_controller);
        }
        //
        try
        {
            $app->mark('action:' . $real_action . ' begin');
            $this->$real_action();
            $this->_response->output();
            $app->mark('action:' . $real_action . ' end', false);
            return;
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            SLog::getInst()->log('action_error', $msg);
            $app->mark('action:' . $real_action . ' exception', false);
            if ('JSON' == $this->_response->getOutputType())
            {
                $a_result['code'] = 'E00000';
                if (DEBUG_MODE)
                {
                    $a_result['msg'] = $msg;
                }
                else
                {
                    $a_result['msg'] = E00000;
                }
                $this->_response->jsonOut($a_result);
                $this->_response->output();
                return;
            }
            else
            {
                if (DEBUG_MODE)
                {
                    echo $msg;
                    return;
                }
                else
                {
                    throw new SException($msg, $e->getCode());
                }
            }
        }
    }

    /**
     * 默认
     */
    public function index ()
    {
        echo "hello action";
    }

    /**
     * 确认动作
     * @param  string $name
     * @return string
     */
    abstract protected function _recognize ($action);
}

/**
 * 灵活的帮助器
 */
abstract class MyHelper
{

    /**
     * 保存当前运行时的前端控制器。
     *
     * @var MyApp
     */
    protected $_app;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @param  MyApp $app
     * @return void
     */
    final public function __construct (MyApp $app)
    {
        $this->_app = $app;
    }

    /**
     * 任务必须具备执行能力。
     * @return void
     */
    abstract public function execute ();
}

/**
 * 定义环境数据收集控件
 */
final class MyRequest
{

    /**
     * 存储运行时数据。
     *
     * @var array
     */
    private $_runtime;

    /**
     * 存储COOKIE数据。
     *
     * @var array
     */
    private $_cookie;

    /**
     * 存储GET数据。
     *
     * @var array
     */
    private $_get;

    /**
     * 存储GET数据。
     *
     * @var array
     */
    private $_orgGet;

    /**
     * 存储POST数据。
     *
     * @var array
     */
    private $_post;

    /**
     * 存储POST数据。
     *
     * @var array
     */
    private $_orgPost;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @return void
     */
    public function __construct ()
    {

        $this->_runtime = array();
        $this->_cookie = $this->_formatData($_COOKIE);
        //$_COOKIE = array();
        $this->_get = $this->_formatData($_GET);
        $this->_orgGet = $_GET;
        $_GET = array();
        $this->_post = $this->_formatData($_POST);
        $this->_orgPost = $_POST;
        //$_POST = array();
    }

    /**
     * 格式化指定的数组数据并返回结果。
     *
     * @param  array $data
     * @return array
     */
    private function _formatData ($data)
    {
        $a_result = array();
        reset($data);
        while (false != (list ($m_key, $m_value) = each($data)))
        {
            $a_result[md5($m_key, true)] = self::_trimData($m_value);
        }
        return $a_result;
    }

    /**
     * 清除空白
     * @param maxie $m_value
     */
    private function _trimData ($m_value)
    {
        if (is_array($m_value))
        {
            foreach ($m_value as $k => $v)
            {
                $m_value[$k] = self::_trimData($v);
            }
            return $m_value;
        }
        else
        {
            return trim($m_value);
        }
    }

    /**
     * 获取指定名称的环境数据。
     *
     * 注意：这个方法不支持指定来源的显性读取。
     *
     * @param  string $name
     * @return mixed
     */
    public function fetch ($name)
    {

        $s_id = md5($name, true);
        if (array_key_exists($s_id, $this->_runtime))
        {
            return $this->_runtime[$s_id];
        }
        if (array_key_exists($s_id, $this->_post))
        {

            return $this->_post[$s_id];
        }
        if (array_key_exists($s_id, $this->_get))
        {
            return $this->_get[$s_id];
        }
        if (array_key_exists($s_id, $this->_cookie))
        {
            return $this->_cookie[$s_id];
        }
        throw new SException($name, 0x00002001);
    }

    /**
     * 获取指定名称的POST数据。
     *
     * 注意：暂时不支持指定来源的显性读取。
     *
     * @param  string $name
     * @return mixed
     */
    public function fetchPost ($name = '')
    {
        if (empty($name))
        {
            return $this->_orgPost;
        }
        else
        {
            $s_id = md5($name, true);

            if (array_key_exists($s_id, $this->_post))
            {
                return $this->_post[$s_id];
            }
            else
            {
                throw new SException($name, 0x00002001);
            }
        }
    }

    /**
     * 获取指定名称的GET数据。
     *
     * @param  string $name
     * @return string
     */
    public function fetchGet ($name = '')
    {
        if (empty($name))
        {
            return $this->_orgGet;
        }
        else
        {
            $s_id = md5($name, true);
            if (array_key_exists($s_id, $this->_get))
            {
                return $this->_get[$s_id];
            }
            else
            {
                throw new SException($name, 0x00002001);
            }
        }
    }

    /**
     * 获取指定一部分名称的GET数据。
     *
     * @param  string $name
     * @return string
     */
    public function fetchSomeGets ($a_get_keys)
    {
        $a_result = array();
        reset($a_get_keys);
        foreach ($a_get_keys as $key)
        {
            $s_id = md5($key, true);
            if (array_key_exists($s_id, $this->_get))
            {
                $a_result[$key] = $this->_get[$s_id];
            }
        }
        return $a_result;
    }

    /**
     * 获取指定一部分名称的POST数据。
     *
     * @param  string $name
     * @return string
     */
    public function fetchSomePost ($a_post_keys)
    {
        $a_result = array();
        reset($a_post_keys);
        foreach ($a_post_keys as $key)
        {
            $s_id = md5($key, true);
            if (array_key_exists($s_id, $this->_post))
            {
                $a_result[$key] = $this->_post[$s_id];
            }
        }
        return $a_result;
    }

    /**
     * 获取指定名称的COOKIE数据。
     *
     * @param  string $name
     * @return string
     */
    public function fetchCookie ($name)
    {
        $s_id = md5($name, true);
        if (array_key_exists($s_id, $this->_cookie))
        {
            return $this->_cookie[$s_id];
        }
        else
        {
            throw new SException($name, 0x00002001);
        }
    }

    /**
     * 获取指定名称的COOKIE数据。
     *
     * @param  string $name
     * @return string
     */
    public function fetchDataUri ()
    {
        return $this->fetch('_OTHER_DATA_REQUEST_URI_');
    }

    /**
     * 设置一个环境数据。
     *
     * @param  string         $name
     * @param  mixed          $value
     * @return MyRequest
     */
    public function set ($name, $value)
    {
        $s_id = md5($name, true);
        $this->_runtime[$s_id] = $value;
        return $this;
    }
}

/**
 * 输出数据响应类
 */
class MyResponse
{

    /**
     *视图引擎
     *
     * @var MyView
     */
    protected $_viewEngine;

    /**
     *视图ID
     *
     * @var string
     */
    protected $_view;

    /**
     *待输出的响应数据
     *
     * @var Array()
     */
    protected $_data;

    /**
     *待输出格式
     *
     * @var string
     */
    protected $_outputType;

    /**
     *类初始化
     *
     * @return MyResponse
     */
    public function __construct ()
    {
        $this->_view = '';
        $this->_data = array();
        $this->_outputType = 'HTML';
        $this->_viewEngine = new MyView();
    }

    /**
     * 获取试图引擎
     *
     * @return MyView
     */
    public function getViewEngine ()
    {
        return $this->_viewEngine;
    }

    /**
     * 设置视图ID
     *
     * @param  string  $view
     * @return MyResponse
     */
    public function setView ($view)
    {
        $view = trim($view);
        $this->_view = $view;
        return $this;
    }

    /**
     * 设置输出类型
     *
     * @param  string  $view
     * @return MyResponse
     */
    public function setOutputType ($type = 'HTML')
    {
        $allow_types = array(
            'HTML' ,
            'JSON' ,
            'XML'
        );
        if (! in_array($type, $allow_types))
        {
            $type = 'HTML';
        }
        $this->_outputType = $type;
        return $this;
    }

    /**
     * 设置输出类型
     *
     * @param  string  $view
     * @return MyResponse
     */
    public function getOutputType ()
    {
        return $this->_outputType;
    }

    /**
     * 设置待输出的响应数据
     *
     * @param  array          $data
     * @return MyResponse
     */
    public function setData ($data = array())
    {
        if (! is_array($data))
        {
            throw new SException($data, 0x00002012);
        }
        else
        {
            $this->_data = $data;
        }
        return $this;
    }

    /**
     * 输出原始格式
     *
     * @param  string  $blob
     * @return void
     */
    public function raw ($blob)
    {
        $this->setView('RAW');
        $this->setData(array(
            'blob' => $blob
        ));
    }

    /**
     * 输出错误信息
     *
     * @param  string  $blob
     * @return void
     */
    public function error ($blob)
    {
        //TODO
    }

    /**
     * 输出
     *
     * @return void
     */
    public function output ()
    {
        $data = $this->_data;
        switch ($this->_view)
        {
            case 'RAW':
                echo $data['blob'];
                break;
            default:
                if (! empty($this->_view))
                {
                    foreach ($data as $key => $value)
                    {
                        $this->_viewEngine->assign($key, $value);
                    }
                    $this->_viewEngine->display($this->_view);
                }
                else
                {
                    $this->raw($data);
                }
                break;
        }
    }

    /**
     * 渲染,不输出
     *
     * @return void
     */
    public function render ()
    {
        if (! empty($this->_view))
        {
            $data = $this->_data;
            foreach ($data as $key => $value)
            {
                $this->_viewEngine->assign($key, $value);
            }
            return $this->_viewEngine->fetch($this->_view);
        }
        else
        {
            return $this->_data;
        }
    }

    /**
     * 重新设置试图
     *
     * @return void
     */
    public function reset ()
    {
        $this->_view = '';
        $this->_data = array();
    }

    /**
     * 重定向URL
     * @param string $url
     *
     * @return void
     */
    public static function redirect ($s_url)
    {
        header('Location:' . $s_url);
        exit();
    }

    public function jsonOut ($a_result = array())
    {
        //$s_result = json_encode($a_result);
        $s_result = SString::jsonEncode($a_result);
        return $this->raw($s_result);
    }

    /**
     * 重定向URL
     * @param string $url
     *
     * @return void
     */
    public static function redirectTop ($s_url)
    {
        echo "<script type=\"text/javascript\">";
        echo "top.location = '{$s_url}';";
        echo "</script>";
        exit(0);
    }
}

/**
 * 作为前端控制器体系中的路由解释控件，它负责对指定的{派发令牌}做认知性翻译处理。
 */
final class MyRouter
{

    /**
     * 存储用于决定控制器的环境数据的字段名称。
     *
     * @var string
     */
    protected $_controllerField;

    /**
     * 存储用于决定操作的环境数据的字段名称。
     *
     * @var string
     */
    protected $_actionField;

    /**
     * 存储路由解释规则。
     *
     * @var array
     */
    protected $_rules;

    /**
     * 存储要派发的控制器的名称。
     *
     * @var string
     */
    protected $_controller;

    /**
     * 存储要执行的操作的名称。
     *
     * @var string
     */
    protected $_action;

    /**
     * 存储默认的控制器名称。
     *
     * @var string
     */
    protected $_defaultController;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @return void
     */
    public function __construct ()
    {
        $this->_controllerField = 'controller';
        $this->_actionField = 'action';
        $this->_rules = array();
        $this->_defaultController = 'DefaultController';
    }

    /**
     * 添加从别名到控制器的路由解释规则。
     *
     * @param  string    $alias
     * @param  string    $name
     * @return MyRouter
     */
    public function addRule ($alias, $name)
    {
        $s_id = md5($alias, true);
        $this->_rules[$s_id] = $name;
        return $this;
    }

    /**
     * 设默认的控制器的名称。
     *
     * @param  string    $name
     * @return MyRouter
     */
    public function setDefaultController ($name)
    {
        if (! is_string($name))
        {
            $name = strval($name);
        }
        $this->_defaultController = $name;
        return $this;
    }

    /**
     * 获取 设默认的控制器。
     *
     * @return string
     */
    public function getDefaultController ()
    {
        return $this->_defaultController;
    }

    /**
     * 获取要派发的控制器的名称。
     *
     * @return string
     */
    public function getController ()
    {
        return $this->_controller;
    }

    /**
     * 获取要执行的操作的名称。
     *
     * @return string
     */
    public function getAction ()
    {
        return $this->_action;
    }

    /**
     * 自定义控制器探测的字段名称和其子键名称。
     *
     * @param  string    $field
     * @return MyRouter
     */
    public function registerControllerField ($field)
    {
        if (! is_string($field))
        {
            $field = strval($field);
        }
        $this->_controllerField = $field;
        return $this;
    }

    /**
     * 自定义操作探测的字段名称和其子键名称。
     *
     * @param  string    $field
     * @return MyRouter
     */
    public function registerActionField ($field)
    {
        if (! is_string($field))
        {
            $field = strval($field);
        }
        $this->_actionField = $field;
        return $this;
    }

    /**
     * 自定义控制器探测的字段名称和其子键名称。
     *
     * @param  string    $field
     * @return MyRouter
     */
    public function getControllerField ()
    {
        return $this->_controllerField;
    }

    /**
     * 自定义操作探测的字段名称和其子键名称。
     *
     * @param  string    $field
     * @return MyRouter
     */
    public function getActionField ()
    {
        return $this->_actionField;
    }

    /**
     * 对指定的派发令牌做解释操作。
     *
     * @param  MyRequest  $request
     * @return MyRouter
     */
    public function parse (MyRequest $request)
    {
        //控制器
        try
        {
            $key = $request->fetch($this->_controllerField);
            $key = md5($key, true);
            if (! is_string($key))
            {
                $key = strval($key);
            }
            if (array_key_exists($key, $this->_rules))
            {
                $this->_controller = $this->_rules[$key];
            }
            else
            {
                $this->_controller = $this->_defaultController;
            }
        }
        catch (Exception $ex)
        {
            $this->_controller = $this->_defaultController;
        }
        //action
        try
        {
            $key = $request->fetch($this->_actionField);
            if (! is_string($key))
            {
                $key = strval($key);
            }
            $this->_action = $key;
        }
        catch (Exception $ex)
        {
            $this->_dataPool = '';
        }
        return $this;
    }
}

/**
 * 声明用于前端控制器的任务的基本行为规范。
 */
abstract class MyTask
{

    /**
     * 保存当前运行时的前端控制器。
     *
     * @var MyApp
     */
    protected $_app;

    /**
     * CONSTRUCTOR FUNCTION.
     *
     * @param  MyApp $app
     * @return void
     */
    public function __construct (MyApp $app)
    {
        $this->_app = $app;
    }

    /**
     * 任务必须具备执行能力。
     *
     * 第二参数$types由前端控制器声明任务类别，以满足同一任务不同时期完成的需求。
     *
     * @param  constant $type
     * @return void
     */
    abstract function execute ($type);
}

/**
 * 视图类，整合smarty等一引擎在这里，或者自己写别的辅助模板引擎
 */
final class MyView
{

    /**
     * 视图引擎
     *
     * @var Smarty
     */
    protected $_engine = null;

    /**
     * 数据
     *
     * @var array
     */
    protected $_viewPool = array();

    function __construct ()
    {
    }

    /**
     * 设置视图引擎，默认smarty
     *
     * @param  Smarty  $engine
     * @return MyView
     */
    public function setEngine ($engine)
    {
        $this->_engine = $engine;
        return $this;
    }

    /**
     * 输出赋值
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function assign ($key, $value)
    {
        if (null != $this->_engine && method_exists($this->_engine, 'assign'))
        {
            $this->_engine->assign($key, $value);
        }
        else
        {
            $this->_viewPool[$key] = $value;
        }
    }

    /**
     * 获取输出值
     *
     * @param string $id
     * @return string
     */
    public function fetch ($id)
    {
        if (null !== $this->_engine && method_exists($this->_engine, 'fetch'))
        {
            return $this->_engine->fetch($id);
        }
        else
        {
            return serialize($this->_viewPool);
        }
    }

    /**
     * 获取输出值
     *
     * @param string $id
     * @return void
     */
    public function display ($id)
    {
        if (null !== $this->_engine && method_exists($this->_engine, 'display'))
        {
            $this->_engine->display($id);
        }
        else
        {
            var_dump($this->_viewPool);
        }
    }
}

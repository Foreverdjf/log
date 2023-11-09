<?php

use Neo\NeoFrame;

/**
 * 借用了 CodeIgniter 的路由解析
 */
class CodeIgniter
{
	/**
	 * 初始化CI路由
	 *
	 * @param NeoFrame $neo
	 */
	public static function initCI(NeoFrame $neo)
	{
		$URI = new CI_URI();
		$RTR = new CI_Router($URI, $neo['controllers_dir'], $neo['routes']);

		$className = ucfirst($RTR->fetch_class());
		$methodName = $RTR->fetch_method();
		$classNamespace = '\\App\\Controller\\' . ucfirst(str_replace('/', '\\', $RTR->fetch_directory() . $className));


		$params = array_slice($URI->rsegments, 2);

		$neo['initParams'] = $params;
		$neo['CIClass'] = $className;
		$neo['CIClassNamespace'] = $classNamespace;
		$neo['CIMethod'] = $methodName;
		if (! class_exists($classNamespace)) {
			static::show_404();
		}
	}

	/**
     * 初始化路由处理：调用对应的类和方法
     *
     * @param NeoFrame $neo
     */
    public static function init(NeoFrame $neo)
    {
	    $className = $neo['CIClass'];
	    $methodName = 	$neo['CIMethod'];
	    $classNamespace = $neo['CIClassNamespace'];
	    // 限流
	    static::lightTraffic($neo, $className, $methodName);

	    $CI = new $classNamespace($neo);

	    if (! method_exists($CI, $methodName) && ! method_exists($CI, '__call')) {
		    static::show_404();
	    }

	    unset($className, $classNamespace);

	    // 调用
	    call_user_func_array([&$CI, $methodName], $neo['initParams']);
    }

    /**
     * 限流
     *
     * @param NeoFrame $neo
     * @param string   $class
     * @param string   $method
     */
    public static function lightTraffic(NeoFrame $neo, $class, $method)
    {
        if (! $class || ! $method) {
            return;
        }

        // 限制访问人数
        $limited = $neo['lighttraffic_limited_user_num'];

        if (! $limited) {
            return;
        }

        // 白名单: 白名单内的接口不会限制流量，其他接口限制流量。留空表示忽略白名单功能。
        $whiteList = $neo['lighttraffic_white_list'];
        // 黑名单: 黑名单内的接口才会限制流量，其他接口不限流量。留空则忽略黑名单功能。
        $blackList = $neo['lighttraffic_black_list'];

        if (! $whiteList && ! $blackList) {
            return;
        }

        $func = strtolower("{$class}.{$method}");

        if ($whiteList) {
            if (in_array($func, explode(',', strtolower($whiteList)))) {
                return;
            }
        }

        if ($blackList) {
            if (! in_array($func, explode(',', strtolower($blackList)))) {
                return;
            }
        }

        $redis = $neo->redis->getRedis();
        $key = 'lighttraffic:' . $func . ':' . formatDate('mdHis');

        if ($redis->get($key) >= $limited) {
            printOutJSON(
                [
                    'code' => 314159,
                    'msg' => 'Too many online users, please try again.',
                ],
                500
            );
        } else {
            $redis->incr($key);
            $redis->expire($key, 60);
        }
    }

    /**
     * 路由不存在
     */
    public static function show_404()
    {
        \Neo\Page::Neo404();
    }

    /**
     * 显示错误
     *
     * @param string $message
     * @param int    $httpResponseCode
     */
    public static function show_error($message = '', $httpResponseCode = 500)
    {
        \Neo\Page::neoDie($message, '', [], $httpResponseCode);
    }

}

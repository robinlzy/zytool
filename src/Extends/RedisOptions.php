<?php

namespace Ziyanco\Zytool\Extends;

use function Hyperf\Coroutine\go;
use Hyperf\Redis\Redis;
use Swoole\Coroutine;
use function Hyperf\Support\make;

class RedisOptions
{
    /**
     * @var Redis
     */
    private static $redisInstance;


    /**
     * redis key前缀
     */
    public const REDIS_LOCK_KEY_PREFIX = 'lock:';

    /**
     * 重试次数
     */
    private const LOCK_RETRY = 3;
    /**
     * 默认锁30秒
     */
    private const LOCK_SECONDS = 30;

    /**
     * 两分钟
     */
    public const TOW_MINUTE = 120;

    /**
     * 10分钟 默认缓存时间
     */
    public const TEN_MINUTE = 600;


    /**
     * 重试微秒
     */
    private const LOCK_MICROSECOND = 2000;

    /**
     * @var array
     */
    private static $lockedNames = [];


    /**
     * redis
     *
     * @return Redis
     */
    public static function getRedisInstance()
    {
        if (is_null(self::$redisInstance)) {
            self::$redisInstance = make(Redis::class);
        }
        return self::$redisInstance;
    }

    /**
     * 上锁
     *
     * @param string $name 锁名字
     * @param int $expire 锁有效期
     * @param int $lockRetry 重试次数
     * @param float|int $sleep 重试休息微秒
     *
     * @return mixed
     */
    public static function lock(string $name, $data = null, int $expire = self::LOCK_SECONDS, int $lockRetry = self::LOCK_RETRY, int $sleep = self::LOCK_MICROSECOND)
    {
        $name = sprintf($name, $data);
        $lock = false;
        $lockRetry = max($lockRetry, 1);
        $key = self::REDIS_LOCK_KEY_PREFIX . $name;
        while ($lockRetry-- > 0) {
            $kVal = microtime(true) + $expire;
            $lock = self::getLock($key, $expire, $kVal); //上锁
            if ($lock) {
                self::$lockedNames[$key] = $kVal;
                break;
            }
            if (\Hyperf\Coroutine\Coroutine::inCoroutine()) {
                Coroutine::sleep((float)$sleep / 1000);
            } else {
                usleep($sleep);
            }
        }
        return $lock;
    }

    /**
     * 解锁
     *
     * @return mixed
     */
    public static function unlock(string $name, $data = null)
    {
        $name = sprintf($name, $data);
        $script = <<<'LUA'
            local key = KEYS[1]
            local value = ARGV[1]

            if (redis.call('exists', key) == 1 and redis.call('get', key) == value) 
            then
                return redis.call('del', key)
            end

            return 0
LUA;
        $key = self::REDIS_LOCK_KEY_PREFIX . $name;
        if (isset(self::$lockedNames[$key])) {
            $val = self::$lockedNames[$key];
            return self::execLuaScript($script, [$key, $val]);
        }
        return false;
    }

    /**
     * 获取锁
     * @param $key
     * @param $expire
     * @param $value
     *
     * @return mixed
     */
    private static function getLock($key, $expire, $value)
    {
        $script = <<<'LUA'
            local key = KEYS[1]
            local value = ARGV[1]
            local ttl = ARGV[2]

            if (redis.call('setnx', key, value) == 1) then
                return redis.call('expire', key, ttl)
            elseif (redis.call('ttl', key) == -1) then
                return redis.call('expire', key, ttl)
            end
            
            return 0
LUA;
        return self::execLuaScript($script, [$key, $value, $expire]);
    }

    /**
     * 订单使用
     * @return mixed
     */
    public function increAddExpire(string $key, int $expire)
    {
        $script = <<<'LUA'
            local key = KEYS[1]
            local ttl = ARGV[1]
            local value = 1
            if redis.call('EXISTS',key) == 1 then
                return redis.call("INCR",KEYS[1])
            elseif (redis.call('setnx', key, value) == 1) then
                redis.call('expire', key, ttl)
                return value
            end
LUA;
        return self::execLuaScript($script, [$key, $expire]);
    }

    /**
     * 设置缓存
     *
     * @param     $key
     * @param     $data
     * @param int $expire
     * @return bool
     */
    public static function set($key, $data, $expire = self::TOW_MINUTE)
    {
        return self::getRedisInstance()->set($key, json_encode($data, true), $expire);
    }

    /**
     * 获取缓存
     *
     * @param $key
     * @return array|mixed
     */
    public static function get($key)
    {
        $data = self::getRedisInstance()->get($key);

        return $data ? json_decode($data, true) : '';
    }

    /**
     * 删除缓存
     *
     * @param $key
     * @return int
     */
    public static function del($key)
    {
        return self::getRedisInstance()->del($key);
    }

    /**
     * 执行lua脚本.
     *
     * @param string $script
     * @param int $keyNum
     * @return mixed
     */
    private static function execLuaScript($script, array $params, $keyNum = 1)
    {
        $hash = self::getRedisInstance()->script('load', $script);
        return self::getRedisInstance()->evalSha($hash, $params, $keyNum);
    }

    /**
     * 最终的redis批量设置方法
     * @param array $setData key=>value的数组
     * @return bool
     */
    public static function doMSet(array $setData, $expire)
    {
        $keys = array_keys($setData);
        if ($setData && is_string(array_shift($keys))) {
            $redis = self::getRedisInstance();
            $redis->mset($setData);

            go(function () use ($setData, $redis, $expire) {
                foreach ($setData as $k => $v) {
                    $redis->expire((string)$k, $expire);
                }
            });
        }
        return true;
    }

    /**
     * 批量获取缓存
     *
     * @param $key
     * @return array|mixed
     */
    public static function mget($key)
    {
        $data = self::getRedisInstance()->mget($key);
        return $data;
    }

    /**
     * 批量获取缓存
     * @param $key
     * @param $hashKeys
     * @return mixed
     */
    public static function hMGet($key, $hashKeys)
    {
        $data = self::getRedisInstance()->hMGet($key, $hashKeys);
        return $data;
    }

    /**
     * redis数据的Hash，设置
     * @param $key
     * @return mixed
     */
    public static function incr($key)
    {
        return self::getRedisInstance()->incr($key);
    }

    /**
     * redis数据的Hash，设置
     * @param $key
     * @return mixed
     */
    public static function decr($key)
    {
        return self::getRedisInstance()->decr($key);
    }

    /**
     * redis数据的Hash，设置
     * @param $key
     * @param $hashKey
     * @param $value
     * @return bool|int
     */
    public static function hSet($key, $hashKey, $value)
    {
        return self::getRedisInstance()->hSet($key, (string)$hashKey, is_array($value) ? json_encode($value) : $value);
    }

    /**
     * redis数据的Hash，获取
     * @param $key
     * @param $hashKey
     * @return false|string
     */
    public static function hGet($key, $hashKey)
    {
        $data = self::getRedisInstance()->hGet($key, (string)$hashKey);
        return $data ? json_decode($data, true) : [];
    }

    /**
     * 缓存删除
     * @param $key
     * @param $hashKey
     * @param ...$otherHashKeys
     * @return bool|int
     */
    public static function hDel($key, $hashKey, ...$otherHashKeys)
    {
        return self::getRedisInstance()->hDel($key, $hashKey, ...$otherHashKeys);
    }

    /**
     * 取得全部数据
     * @param $key
     * @return array
     */
    public static function hGetAll($key)
    {
        return self::getRedisInstance()->hGetAll($key);
    }

    /**
     * 一次性插入
     * @param $key
     * @param $data
     * @return bool
     */
    public static function hMSet($key, $hashKeys)
    {
        return self::getRedisInstance()->hMSet($key, $hashKeys);
    }

    /**
     * 新增一个有序值
     * @param $key
     * @param $hashKeys
     * @param $value
     * @return int
     */
    public static function zAdd($key, $hashKeys, $value)
    {
        return self::getRedisInstance()->zAdd($key, $hashKeys, $value);
    }

    /**
     * 删除有序集合的值
     * @param $key
     * @param $member
     * @return int
     */
    public static function zRem($key, $member)
    {
        return self::getRedisInstance()->zRem($key, $member);
    }

    /**
     * 递增排序
     * @param $key
     * @param int $start
     * @param int $end
     * @param null $withscores
     * @return array
     */
    public static function zRange($key, $start = 0, $end = -1, $withscores = null)
    {
        return self::getRedisInstance()->zRange($key, $start, $end, $withscores);
    }

    /**
     * 递减排序
     * @param $key
     * @param int $start
     * @param int $end
     * @param $withscores
     * @return array
     */
    public static function zRevrange($key, $start = 0, $end = -1, $withscores = null)
    {
        return self::getRedisInstance()->zRevRange($key, $start, $end, $withscores);
    }

    /**
     * 一次性插入
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function push($key, $value)
    {
        return self::getRedisInstance()->lPush($key, $value);
    }

    /**
     * 一次性插入
     * @param $key
     * @return mixed
     */
    public static function pop($key)
    {

        return self::getRedisInstance()->rPop($key);
    }

    /**
     * 判断key是否存在
     * @param $key
     * @return mixed
     */
    public static function keyExists($key)
    {
        return self::getRedisInstance()->exists($key);
    }
}
<?php
/*
Fw Cache class
Application-level cache
Caches only for current request lifetime in memory
TODO - long-term cache

You may overload it with somehitng more specific.
For example, good caching class - http://www.phpfastcache.com/

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com
 */

class FwCache {
    static $storage = array();
    static $handler; #TODO - if this handler set, use it instead of $storage

    /**
     * return value from the cache. If no value exists - returns NULL
     * @param  string $key key to lookup in cache
     * @return mixed
     */
    public static function getValue($key) {
        return self::$storage[$key];
    }

    /**
     * place value into the cache
     * @param  string $key  cache key
     * @param  mixed $value value to be placed in cache
     * @return nothing
     */
    public static function setValue($key, $value) {
        self::$storage[$key] = $value;
    }

    /**
     * remove key/value from the cache
     * @param  string $key key to lookup in cache
     * @return nothing
     */
    public static function remove($key) {
        unset(self::$storage[$key]);
    }

    /**
     * remove all keys with prefix
     * @param  string $prefix prefix key
     * @return nothing
     */
    public static function removeWithPrefix($prefix) {
        $plen = strlen($prefix);
        $keys = array_keys(self::$storage);
        foreach ($keys as $key) {
            if (substr($key, 0, $plen) === $prefix) {
                unset(self::$storage[$key]);
            }
        }
    }

    /**
     * clears whole cache
     * @return nothing
     */
    public static function clear() {
        self::$storage = array();
    }

    public function __construct() {
        # code...
    }
}

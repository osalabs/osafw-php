<?php
/*
Fw Cache class
Application-level cache
2 types of cache:
- request methods - caches only for current request lifetime in memory
- use with memcached by setting fw->config->cache to array of servers and then use fw->cache->get/set/remove

You may overload it with something more specific.
For example, good caching class - http://www.phpfastcache.com/

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class FwCache {
    const int MAX_KEY_LEN = 250 - 40 - 2; #memcached key max length is 250, -40 for sha1 suffix, -2 for suffix separator

    static array $storage = array(); #this is request storage

    private $handler = null; # Memcached handler
    private array $lock_tokens = []; // Store lock tokens per key

    public function __construct(FW $fw) {
        $cache_servers = $fw->config->cache ?? [];
        if ($cache_servers) {
            //check if memcached extension is loaded
            if (!extension_loaded('memcached')) {
                throw new Exception('Memcached extension is not loaded, but config->cache is set');
            }

            $this->handler = new Memcached();
            $this->handler->setOption(Memcached::OPT_BINARY_PROTOCOL, true); //need for increment
            foreach ($cache_servers as $server) {
                // $server can be either just host or host:port
                $server = explode(':', $server);
                $host   = $server[0];
                $port   = $server[1] ?? 11211;
                $this->handler->addServer($host, $port);
            }
        }
    }

    /**
     * return number of items in Request cache
     * @return int
     */
    public function countRequest(): int {
        return count(self::$storage);
    }

    /**
     * return value from the Request cache. If no value exists - returns NULL
     * @param string $key key to lookup in cache
     * @return mixed
     */
    public function getRequestValue(string $key): mixed {
        return self::$storage[$key] ?? null;
    }

    /**
     * place value into the Request cache
     * @param string $key cache key
     * @param mixed $value value to be placed in cache
     * @return void
     */
    public function setRequestValue(string $key, mixed $value): void {
        self::$storage[$key] = $value;
    }

    /**
     * remove key/value from the Request cache
     * @param string $key key to lookup in cache
     * @return void
     */
    public function removeRequest(string $key): void {
        unset(self::$storage[$key]);
    }

    /**
     * remove all keys with prefix in Request cache
     * @param string $prefix prefix key
     * @return void
     */
    public function removeRequestWithPrefix(string $prefix): void {
        $plen = strlen($prefix);
        $keys = array_keys(self::$storage);
        foreach ($keys as $key) {
            if (substr($key, 0, $plen) === $prefix) {
                unset(self::$storage[$key]);
            }
        }
    }

    /**
     * clears whole Request cache
     * @return void
     */
    public function clearRequest(): void {
        self::$storage = array();
    }

    /**
     * normalize memcached key, since memcached keys has restrictions:
     * - max length 250
     * - no spaces
     * - no control characters
     * - no null characters
     *
     * @param string $key
     * @return string
     */
    public function normalizeKey(string $key): string {
        // Replace disallowed characters with percent-encoded equivalents
        $encodedKey = rawurlencode($key);

        // Truncate the key if necessary
        if (strlen($encodedKey) > self::MAX_KEY_LEN) {
            // Generate a SHA-1 hash of the original key
            $hash = sha1($key);

            // Truncate the key to the calculated length and append the hash
            $encodedKey = substr($encodedKey, 0, self::MAX_KEY_LEN) . '__' . $hash;
        }

        return $encodedKey;
    }

    /**
     * return value from the cache. If no value exists - returns NULL
     * @param string $key key to lookup in cache
     * @return mixed
     */
    public function get(string $key): mixed {
        $key = $this->normalizeKey($key);
        if (isset($this->handler)) {
            return $this->handler->get($key);
        } else {
            #fallback to request cache
            return $this->getRequestValue($key);
        }
    }

    /**
     * return multiple values from the cache. If no value exists - returns NULL
     * @param array $keys
     * @return array
     */
    public function getMulti(array $keys): array {
        $result = [];
        if (isset($this->handler)) {
            $keys   = array_map(fn($key) => $this->normalizeKey($key), $keys);
            $result = $this->handler->getMulti($keys);
            if ($result === false) {
                //                $code    = $this->handler->getResultCode();
                //                $message = $this->handler->getResultMessage();
                //                logger("WARN", "Memcached getMulti error: $code $message");
                $result = [];
            }
        } else {
            #fallback to request cache
            foreach ($keys as $key) {
                $value = $this->getRequestValue($key);
                if ($value !== null) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * place value into the cache
     * @param string $key cache key
     * @param mixed $value value to be placed in cache
     * @param int $ttl time to live in seconds (less than number of seconds in 30 days) or unix timestamp
     * @return bool false on error
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool {
        $result = true;
        $key    = $this->normalizeKey($key);
        if (isset($this->handler)) {
            $result = $this->handler->set($key, $value, $ttl);
            if (!$result) {
                $code    = $this->handler->getResultCode();
                $message = $this->handler->getResultMessage();
                logger("WARN", "Memcached set error: $code $message");
            }
        } else {
            #fallback to request cache
            $this->setRequestValue($key, $value);
        }

        return $result;
    }

    /**
     * remove key/value from the cache
     * @param string $key key to lookup in cache
     * @return bool false on error
     */
    public function remove(string $key): bool {
        $result = true;
        $key    = $this->normalizeKey($key);
        if (isset($this->handler)) {
            $result = $this->handler->delete($key);
            if (!$result) {
                $code    = $this->handler->getResultCode();
                $message = $this->handler->getResultMessage();
                logger("WARN", "Memcached delete error: $code $message");
            }
        } else {
            #fallback to request cache
            $this->removeRequest($key);
        }

        return $result;
    }

    /**
     * increment value in the cache
     * @param string $key key to lookup in cache
     * @param int $value value to increment
     * @param int $ttl time to live in seconds (less than number of seconds in 30 days) or unix timestamp
     * @return int|bool new value or false on error
     */
    public function increment(string $key, int $value = 1, int $ttl = 0): int|bool {
        $result = false;
        $nkey   = $this->normalizeKey($key);
        if (isset($this->handler)) {
            $result = $this->handler->increment($nkey, $value, 0, $ttl);
            if ($result === false) {
                $code = $this->handler->getResultCode();

                // Only set the key if the error code indicates that the key was not found
                if ($code == \Memcached::RES_NOTFOUND || $code == \Memcached::RES_NOTSTORED) {
                    $this->handler->set($nkey, $value, $ttl);
                    $result = $value;
                } else {
                    // Log other errors or handle them as needed
                    $message = $this->handler->getResultMessage();
                    logger("WARN", "Memcached increment error: $code $message", [$nkey, $value, $ttl]);
                }
            }
        } else {
            #fallback to request cache
            $old_value = $this->getRequestValue($nkey) ?? 0;
            $new_value = $old_value + $value;
            $this->setRequestValue($nkey, $new_value);
            $result = $new_value;
        }

        return $result;
    }


    /**
     * try to lock the key for the specified time
     *  shortcut for incrementing the key and checking if it's 1
     * @param string $key
     * @param int $ttl
     * @return bool true if the lock was acquired, false if the lock was not acquired
     */
    public function lock(string $key, int $ttl = 30): bool {
        $key    = $this->normalizeKey($key);
        $token  = uniqid('', true); // Generate a unique token
        $result = false;

        if (isset($this->handler)) {
            // Try to add the key with the unique token
            $result = $this->handler->add($key, $token, $ttl);
            if ($result) {
                // Store the token
                $this->lock_tokens[$key] = $token;
                $result                  = true;
            }
        } else {
            // Fallback to request cache
            if (!isset(self::$storage[$key])) {
                self::$storage[$key]     = $token;
                $this->lock_tokens[$key] = $token;
                $result                  = true;
            }
        }

        return $result;
    }


    /**
     * release the lock
     * @param string $key
     * @return bool true if the lock was released, false if the lock was not found or not owned by the caller
     */
    public function unlock(string $key): bool {
        $key = $this->normalizeKey($key);
        // Get the token we stored when we acquired the lock
        if (!isset($this->lock_tokens[$key])) {
            // We don't have the token, cannot unlock
            return false;
        }
        $token = $this->lock_tokens[$key];

        if (isset($this->handler)) {
            // Get the current value of the key
            $current_value = $this->handler->get($key);
            if ($current_value === $token) {
                // Our lock, delete the key
                $result = $this->handler->delete($key);
                unset($this->lock_tokens[$key]);
                return $result;
            }
        } else {
            if (isset(self::$storage[$key]) && self::$storage[$key] === $token) {
                unset(self::$storage[$key]);
                unset($this->lock_tokens[$key]);
                return true;
            }
        }

        // Not our lock, do not delete
        unset($this->lock_tokens[$key]);
        return false;
    }

}

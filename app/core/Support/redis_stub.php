<?php

if (!class_exists('Redis')) {
    class Redis
    {
        public function connect($host, $port = 6379, $timeout = 0.0) {}

        public function auth($password) {}

        public function select($database) {}

        public function setex($key, $ttl, $value) {}

        public function set($key, $value) {}

        public function get($key) {}

        public function del($key) {}

        public function expire($key, $ttl) {}

        public function ping() {}
    }
}

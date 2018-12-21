<?php


namespace ProcessManager\db;


use Predis\Response\Status;

class RedisManager implements DBManagerInterface
{
    /** @var string Prefix for Redis DB */
    private          $keyPrefix = 'PM';
    protected static $connect;

    public function __construct()
    {
        $this->checkConnect();
    }

    /**
     * @return string
     */
    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    /**
     * @param string $keyPrefix
     */
    public function setKeyPrefix(string $keyPrefix)
    {
        $this->keyPrefix = $keyPrefix;
    }

    public function newConnect()
    {
        self::$connect = new \Predis\Client(
            [
                'scheme'             => 'tcp',
                'host'               => 'redis',
                'port'               => 6379,
                'database'           => 0,
                'read_write_timeout' => -1,
                'async'              => false,
                'password'           => getenv('REDIS_PSWD')
            ]
        );
    }

    public function checkConnect()
    {
        if (self::$connect === null) {
            $this->newConnect();
        }
    }

    /**
     * update process state
     *
     * @param int   $id
     * @param array $fields
     *
     * @return mixed
     */
    public function updProcessStateById($id, $fields)
    {
        $oneLevelArray = $this->convertArrayKeysToOneLevel($fields);

        $set = [];
        foreach ($oneLevelArray as $key => $val) {
            $set["{$this->keyPrefix}:{$id}:{$key}"] = $val;
        }

        $answer = true;
        if (count($set) > 0) {
            /** @var Status $status */
            $status = self::$connect->mset($set);
            $answer = $status->getPayload() === 'OK';
        }

        return $answer;
    }

    /**
     * update process state
     *
     * @param int   $id
     * @param array $fields
     *
     * @return bool
     */
    public function rmvFromProcessStateById($id, $fields): bool
    {
        $oneLevelArray = $this->convertArrayKeysToOneLevel($fields);

        $set = [];
        foreach ($oneLevelArray as $key => $val) {
            $set[] = "{$this->keyPrefix}:{$id}:{$key}";
        }

        $answer = true;
        if (count($set) > 0) {
            $deleted = self::$connect->del($set);
            $answer = $deleted > 0;
        }

        return $answer;
    }

    public function convertArrayKeysToOneLevel($array)
    {
        $set = [];

        foreach ($array as $key => $val) {
            if (is_array($val)) {
                foreach ($this->convertArrayKeysToOneLevel($val) as $key2 => $val2) {
                    $set["{$key}:{$key2}"] = $val2;
                }
            } else {
                $set[$key] = $val;
            }
        }

        return $set;
    }

    /**
     * get all process state by id, or some field
     *
     * @param int                 $id
     * @param null|string|mixed[] $field
     *
     * @return mixed
     */
    public function getProcessStateById($id, $field = null)
    {
        if ($field === null) {
            $data = [];
            $keys = self::$connect->keys("{$this->keyPrefix}:{$id}:*");
            if (!$keys !== []) {
                $values = self::$connect->mGet($keys);
                foreach ($keys as $n => $keyFull) {
                    $shortKey = str_replace("{$this->keyPrefix}:{$id}:", '', $keyFull);
                    $data = $this->setArrayElementByKey($data, $shortKey, $values[$n]);
                }
            }
        } else {
            $data = self::$connect->get("{$this->keyPrefix}:{$id}:" . $field);
        }

        return $data;
    }

    /**
     * insert error to error log list
     *
     * @param int    $id
     * @param string $error
     *
     * @return void
     */
    public function addErrorToList($id, string $error)
    {
        $isUpdated = $this->connect->lPush("{$this->keyPrefix}:{$id}:errors", $error);
        if ($isUpdated) {
            $this->connect->lTrim("{$this->keyPrefix}:{$id}:errors", 0, DBManagerInterface::SAVE_LAST_N_ERRORS);
        }

        return $isUpdated;
    }

    /**
     * get all values or vulue by key
     *
     * $getKey example: 'key:123:array' => $_SESSION['key']['123']['array']
     *
     * @param null|string $getKey
     * @param null|mixed  $default
     * @param array       $array
     *
     * @return mixed
     */
    public static function getArrayElementByKey($array = [], $getKey = null, $default = null)
    {
        $data = $array;
        if ($getKey) {
            $keyParts = explode(':', $getKey);
            foreach ($keyParts as $key) {
                if (isset($data[$key])) {
                    $data = $data[$key];
                } else {
                    $data = null;
                    break;
                }
            }
        }

        if ($data === null) {
            $data = $default;
        }

        return $data;
    }


    /**
     * set value in array by key
     *
     * $setKey example: 'key:123:array' => $_SESSION['key']['123']['array']
     *
     * @param array  $array
     * @param string $setKey
     * @param mixed  $setVal
     *
     * @return array
     */
    public static function setArrayElementByKey($array, $setKey, $setVal)
    {
        $link = &$array;
        $keyParts = explode(':', $setKey);
        foreach ($keyParts as $key) {
            if (!isset($link[$key])) {
                $link[$key] = [];
            }
            $link = &$link[$key];
        }
        $link = $setVal;

        return $array;
    }
}
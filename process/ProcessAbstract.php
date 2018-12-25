<?php


namespace ProcessManager\process;

use Predis\Response\Status;
use ProcessManager\db\DBManagerInterface;
use ProcessManager\db\RedisManager;

abstract class ProcessAbstract implements ProcessInterface
{
    /** @var null|DBManagerInterface */
    private $dbManager;
    /** @var null|string id in database */
    private $id;
    /** @var array State saved in DB */
    protected $dbState = [];
    /** @var array Current state of the object */
    protected $objState = [];

    /**
     * ProcessAbstract constructor.
     *
     * @param DBManagerInterface|null $DBManager
     */
    public function __construct(DBManagerInterface $DBManager = null)
    {
        $this->setDBManager($DBManager);
        //set default values
        $className = get_class($this);
        $this->setId(substr(md5($className), 0, 9));
        $this->setClassName(basename(str_replace('\\', '/', $className)));
        $this->setProcessName($this->getClassName());
        $this->setPriority(0);
        $this->setExecutionStep(0);
        $this->setRunningFlag(0);
        $this->setNTriesOfRun(0);
        $this->setMaxNTriesOfRun(0);
        $this->setSecondsBetweenRuns(60);
        $this->setMaxLifetimeWithoutResults(60);
        $this->dbState['lastUpdateDatetime'] = null;
        $this->dbState['data'] = [];
        $this->dbState['errors'] = [];
    }

    /**
     * load process state from db
     *
     * @return $this
     */
    public function loadState()
    {
        $params = $this->getDBManager()->getProcessStateById($this->id);

        foreach ($params as $fieldName => $val) {
            $this->dbState[$fieldName] = $val;
            $this->objState[$fieldName] = $val;
        }

        return $this;
    }

    /**
     * checking existence of process state in db
     *
     * @return bool
     */
    public function hasState(): bool
    {
        return !empty($this->getDBManager()->getProcessStateById($this->id, 'id'));
    }

    /**
     * checking existence of process state in db
     *
     * @return bool
     */
    public function saveState(): bool
    {
        $fieldsForUpdate = $this->getNewFieldsFromArrayCompare(
            $this->objState,
            $this->dbState
        );

        $fieldsForDelete = $this->getNewFieldsFromArrayCompare(
            $this->dbState,
            $this->objState
        );

        $d = true;
        if (count($fieldsForDelete) > 0) {
            $d = $this->getDBManager()->rmvFromProcessStateById($this->id, $fieldsForDelete);
        }

        $u = true;
        if (count($fieldsForUpdate) > 0) {
            $u = $this->getDBManager()->updProcessStateById($this->id, $fieldsForUpdate);
        }

        if ($u && $d) {
            $this->dbState = $this->objState;
        }

        return $u && $d;
    }

    /**
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    public function getNewFieldsFromArrayCompare($new, $old)
    {
        $fields = array_udiff_uassoc(
            $new,
            $old,
            function ($a, $b) {
                return $a === $b ? 0 : 1;
            },
            function ($a, $b) {
                return $a === $b ? 0 : 1;
            }
        );

        foreach ($fields as $key => $field) {
            if (is_array($field) && isset($old[$key])) {
                $fields[$key] = $this->getNewFieldsFromArrayCompare($new[$key], $old[$key]);
            }
        }

        return $fields;
    }

    /**
     * @param DBManagerInterface|null $dbManager
     *
     * @return $this
     */
    public function setDBManager(DBManagerInterface $dbManager = null)
    {
        $this->dbManager = $dbManager;

        return $this;
    }

    /**
     * @return null|DBManagerInterface
     */
    public function getDBManager()
    {
        if ($this->dbManager instanceof RedisManager) {
            $this->dbManager->setKeyPrefix('PM:' . $this->getClassName());
        }
        return $this->dbManager;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->objState['className'];
    }

    /**
     * @param string $className
     */
    public function setClassName(string $className)
    {
        $this->objState['className'] = $className;
    }

    /**
     * set id, witch it have in db
     *
     * @param string $id
     *
     * @return $this
     */
    public function setId(string $id)
    {
        $this->objState['id'] = $id;

        return $this;
    }

    /**
     * get id, witch it have in db
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->objState['id'];
    }

    /**
     * have to be dane before interaction with state
     *
     * @return string
     */
    public function generateIdFromProcessName()
    {
        $this->setId(substr(md5($this->getProcessName()), 0, 9));

        return $this;
    }

    /**
     * Human readable process name
     *
     * @return string
     */
    public function getProcessName(): string
    {
        return $this->objState['processName'];
    }

    /**
     * Human readable process name
     *
     * @param string $processName
     *
     * @return $this
     */
    public function setProcessName(string $processName)
    {
        $this->objState['processName'] = $processName;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPid()
    {
        return $this->objState['pid'];
    }

    /**
     * @param int $pid
     *
     * @return $this
     */
    public function setPid(int $pid)
    {
        $this->objState['pid'] = $pid;

        return $this;
    }

    /**
     * @param integer $priority
     *
     * @return $this
     */
    public function setPriority(int $priority)
    {
        $this->objState['priority'] = $priority;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->objState['priority'];
    }

    /**
     * @return int Step of execution, 0 if execution is not needed
     */
    public function getExecutionStep(): int
    {
        return $this->objState['executionStep'];
    }

    /**
     * @param int $step Step of execution, 0 if execution is not needed
     *
     * @return $this
     */
    public function setExecutionStep(int $step)
    {
        $this->objState['executionStep'] = $step;

        return $this;
    }

    /**
     * @return integer PR_NOT_RUNNING(0) if no running and PR_RUNNING(1) if running
     */
    public function isRunning(): int
    {
        return $this->objState['isRunning'];
    }

    /**
     * @param integer $value PR_NOT_RUNNING(0) if no running and PR_RUNNING(1) if running
     *
     * @return $this
     */
    public function setRunningFlag(int $value)
    {
        $this->objState['isRunning'] = $value;

        return $this;
    }

    /**
     * Current number of try to execute job
     *
     * @return integer
     */
    public function getNTriesOfRun(): int
    {
        return $this->objState['nTriesOfRun'];
    }

    /**
     * Current number of try to execute job
     *
     * @param integer $nTriesOfRun
     *
     * @return $this
     */
    public function setNTriesOfRun(int $nTriesOfRun)
    {
        $this->objState['nTriesOfRun'] = $nTriesOfRun;

        return $this;
    }

    /**
     * Max available tries to execute job, 0 is infinite
     *
     * @return int
     */
    public function getMaxNTriesOfRun(): int
    {
        return $this->objState['maxNTriesOfRun'];
    }

    /**
     * Max available tries to execute job, 0 is infinite
     *
     * @param int $NTries
     *
     * @return $this
     */
    public function setMaxNTriesOfRun(int $NTries)
    {
        $this->objState['maxNTriesOfRun'] = $NTries;

        return $this;
    }

    /**
     * Period job execution
     *
     * @return int
     */
    public function getSecondsBetweenRuns(): int
    {
        return $this->objState['secondsBetweenRuns'];
    }

    /**
     * Period job execution
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function setSecondsBetweenRuns(int $seconds)
    {
        $this->objState['secondsBetweenRuns'] = $seconds;

        return $this;
    }

    /**
     * Max lifetime of process without upd lastUpdateDatetime param.
     * After this time job will be stopped.
     *
     * @return int
     */
    public function getMaxLifetimeWithoutResults(): int
    {
        return $this->objState['maxLifetimeWithoutResults'];
    }

    /**
     * Max lifetime of process without upd lastUpdateDatetime param.
     * After this time job will be stopped.
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function setMaxLifetimeWithoutResults(int $seconds)
    {
        $this->objState['maxLifetimeWithoutResults'] = $seconds;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastUpdateDatetime()
    {
        return $this->objState['lastUpdateDatetime'];
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLastUpdateDatetime($value)
    {
        $this->objState['lastUpdateDatetime'] = $value;

        return $this;
    }

    /**
     * get some job data by key
     *
     * @param string     $key
     * @param null|mixed $default
     *
     * @return mixed|null
     */
    public function getDataByKey(string $key, $default = null)
    {
        return isset($this->objState['data'][$key]) ? $this->objState['data'][$key] : $default;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function setDataByKey($key, $value)
    {
        $this->objState['data'][$key] = $value;

        return $this;
    }

    /**
     * insert error to error log list
     *
     * @param string $error
     *
     * @return $this
     */
    public function addErrorToList($error)
    {
        array_unshift($this->objState['errors'], $error);
        $this->objState['errors'] = array_slice($this->objState['errors'], 0, DBManagerInterface::SAVE_LAST_N_ERRORS);

        return $this;
    }

    /**
     * list of last 50 job errors
     *
     * @return string[]
     */
    public function getErrorList(): array
    {
        return $this->objState['errors'];
    }

    /**
     * ask process to start
     *
     * @return bool
     */
    public function isStartNeeded(): bool
    {
        return
            (
                !$this->isRunning()
                && $this->getExecutionStep() !== 0
                && ($this->getMaxNTriesOfRun() === 0 || $this->getNTriesOfRun() <= $this->getMaxNTriesOfRun())
                && (
                    $this->getLastUpdateDatetime() === null
                    || time() >= (strtotime($this->getLastUpdateDatetime()) + $this->getSecondsBetweenRuns())
                )
            )
//            ||
//            (
//                $this->isRunning()
//                && $this->getExecutionStep() !== 0
//                && ($this->getMaxNTriesOfRun() === 0 || $this->getNTriesOfRun() <= $this->getMaxNTriesOfRun())
//                && time() >= (strtotime($this->getLastUpdateDatetime()) + $this->getMaxLifetimeWithoutResults())
//            )
            ;
    }

    /**
     * ask process to stop
     *
     * @return bool
     */
    public function isStopNeeded(): bool
    {
        return
            //if stop signal from db
            (
                $this->isRunning()
                && $this->getExecutionStep() === 0
            )
            //tries ended
            ||
            (
                $this->isRunning()
                && ($this->getMaxNTriesOfRun() !== 0 && $this->getNTriesOfRun() > $this->getMaxNTriesOfRun())
            )
            //not results from running process
            || (
                $this->isRunning()
                && (
                    $this->getLastUpdateDatetime() !== null
                    && time() >= (strtotime($this->getLastUpdateDatetime()) + $this->getMaxLifetimeWithoutResults())
                )
            );
    }

    /**
     * Update resources in child process after fork.
     * Needed for correct working conections to db and ect.
     *
     * @return $this
     */
    public function updateResourcesAfterFork()
    {
        if ($this->getDBManager() instanceof DBManagerInterface) {
            $this->getDBManager()->newConnect();
        }

        return $this;
    }

    /**
     * Update priority in child process after fork.
     * Cant be more then in parent process
     * for linux it have to be from 20 to 40
     *
     * @return $this
     */
    public function updateProcessPriority()
    {
        pcntl_setpriority($this->getPriority(), getmypid());

        return $this;
    }
}
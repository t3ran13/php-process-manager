<?php


namespace ProcessManager\process;

use ProcessManager\db\DBManagerInterface;
use ProcessManager\db\RedisManager;

abstract class ProcessAbstract implements ProcessInterface
{
    /** @var null|DBManagerInterface */
    private $dbManager;
    /** @var null|string id in database */
    private $id;
    /** @var null|string Human readable process name */
    private $processName = null;
    /** @var int for linux it have to be from 20 to 40 */
    private $priority = 20;
    /** @var null|int */
    private $pid = null;
    /** @var integer 0 if no */
    private $isRunning;
    /** @var integer Step of execution, 0 if execution is not needed */
    private $executionStep;
    /** @var integer current number of tries to execute job */
    private $nTriesOfRun;
    /** @var integer maximal number of tries to execute job, 0 is infinite */
    private $maxNTriesOfRun;
    /** @var integer seconds between job executions */
    private $secondsBetweenRuns;
    /** @var integer results are awaiting max seconds from running process */
    private $maxLifetimeWithoutResults;
    /** @var string */
    private $lastUpdateDatetime;
    /** @var (string|integer)[] */
    private $data = []; //named array
    /** @var string[] */
    private $errors = [];

    /**
     * ProcessAbstract constructor.
     *
     * @param DBManagerInterface|null $DBManager
     */
    public function __construct(DBManagerInterface $DBManager = null)
    {
        $this->setDBManager($DBManager);
        $this->setId(substr(md5(get_class($this)), 0, 9));
    }

    /**
     * load process state from db
     *
     * @return $this
     */
    public function loadState()
    {
        $params = $this->getDBManager()->getProcessStateById($this->id);

        $this->processName = empty($params['processName']) ? null : $params['processName'];
        $this->priority = empty($params['priority']) ? 0 : (integer)$params['priority'];
        $this->pid = empty($params['pid']) ? 0 : (integer)$params['pid'];
        $this->executionStep = empty($params['executionStep']) ? 0 : (integer)$params['executionStep'];
        $this->isRunning = empty($params['isRunning']) ? 0 : (integer)$params['isRunning'];
        $this->nTriesOfRun = empty($params['nTriesOfRun']) ? 0 : (integer)$params['nTriesOfRun'];
        $this->maxNTriesOfRun = empty($params['maxNTriesOfRun']) ? 0 : (integer)$params['maxNTriesOfRun'];
        $this->secondsBetweenRuns = empty($params['secondsBetweenRuns']) ? 0 : (integer)$params['secondsBetweenRuns'];
        $this->maxLifetimeWithoutResults = empty($params['maxLifetimeWithoutResults'])
            ? 0 : (integer)$params['maxLifetimeWithoutResults'];
        $this->lastUpdateDatetime = empty($params['lastUpdateDatetime']) ? null : $params['lastUpdateDatetime'];
        $this->data = empty($params['data']) || !is_array($params['data']) ? [] : $params['data'];
        $this->errors = empty($params['errors']) || !is_array($params['errors']) ? [] : $params['errors'];

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
            $pmPrefix = $this->dbManager->getKeyPrefix();
            $this->dbManager->setKeyPrefix($pmPrefix . ':' . $this->getProcessName());
        }
        return $this->dbManager;
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
        $this->id = $id;

        return $this;
    }

    /**
     * get id, witch it have in db
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Human readable process name
     *
     * @return string
     */
    public function getProcessName(): string
    {
        return $this->processName;
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
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['processName' => $processName]);
        $this->processName = $isUpdated ? $processName : $this->processName;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param int $pid
     *
     * @return $this
     */
    public function setPid(int $pid)
    {
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['pid' => $pid]);
        $this->pid = $isUpdated ? $pid : $this->pid;

        return $this;
    }

    /**
     * @param integer $priority
     *
     * @return $this
     */
    public function setPriority(int $priority)
    {
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['priority' => $priority]);
        $this->priority = $isUpdated ? $priority : $this->priority;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @return int Step of execution, 0 if execution is not needed
     */
    public function getExecutionStep(): int
    {
        return $this->executionStep;
    }

    /**
     * @param int $step Step of execution, 0 if execution is not needed
     *
     * @return $this
     */
    public function setExecutionStep(int $step)
    {
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['executionStep' => $step]);
        $this->executionStep = $isUpdated ? $step : $this->executionStep;

        return $this;
    }

    /**
     * @return integer PR_NOT_RUNNING(0) if no running and PR_RUNNING(1) if running
     */
    public function isRunning(): int
    {
        return $this->isRunning;
    }

    /**
     * @param integer $value PR_NOT_RUNNING(0) if no running and PR_RUNNING(1) if running
     *
     * @return $this
     */
    public function setRunningFlag(int $value)
    {
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['isRunning' => $value]);
        $this->isRunning = $isUpdated ? $value : $this->isRunning;

        return $this;
    }

    /**
     * Current number of try to execute job
     *
     * @return integer
     */
    public function getNTriesOfRun(): int
    {
        return $this->nTriesOfRun;
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
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['nTriesOfRun' => $nTriesOfRun]);
        $this->nTriesOfRun = $isUpdated ? $nTriesOfRun : $this->nTriesOfRun;

        return $this;
    }

    /**
     * Max available tries to execute job, 0 is infinite
     *
     * @return int
     */
    public function getMaxNTriesOfRun(): int
    {
        return $this->maxNTriesOfRun;
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
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['maxNTriesOfRun' => $NTries]);
        $this->maxNTriesOfRun = $isUpdated ? $NTries : $this->maxNTriesOfRun;

        return $this;
    }

    /**
     * Period job execution
     *
     * @return int
     */
    public function getSecondsBetweenRuns(): int
    {
        return $this->secondsBetweenRuns;
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
        $isUpdated = $this->getDBManager()
            ->updProcessStateById($this->getId(), ['secondsBetweenRuns' => $seconds]);
        $this->secondsBetweenRuns = $isUpdated ? $seconds : $this->secondsBetweenRuns;

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
        return $this->maxLifetimeWithoutResults;
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
        $isUpdated = $this->getDBManager()
            ->updProcessStateById($this->getId(), ['secondsBetweenRuns' => $seconds]);
        $this->maxLifetimeWithoutResults = $isUpdated ? $seconds : $this->maxLifetimeWithoutResults;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastUpdateDatetime(): string
    {
        return $this->lastUpdateDatetime;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLastUpdateDatetime($value)
    {
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['lastUpdateDatetime' => $value]);
        $this->lastUpdateDatetime = $isUpdated ? $value : $this->lastUpdateDatetime;

        return $this;
    }

    /**
     * get some job data by key
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function getDataByKey(string $key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setDataByKey($key, $value)
    {
        $isUpdated = $this->getDBManager()->updProcessStateById($this->getId(), ['data' => [$key => $value]]);
        $this->data[$key] = $isUpdated ? $value : $this->data[$key];

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
        $isUpdated = $this->getDBManager()->addErrorToList($this->getId(), $error);
        if ($isUpdated) {
            array_unshift($this->errors, $error);
            $this->errors = array_slice($this->errors, 0, DBManagerInterface::SAVE_LAST_N_ERRORS);
        }

        return $this;
    }

    /**
     * list of last 50 job errors
     *
     * @return string[]
     */
    public function getErrorList(): array
    {
        return $this->errors;
    }

    /**
     * ask process to start
     *
     * @return bool
     */
    public function isStartNeeded(): bool
    {
        return !$this->isRunning()
            && $this->getExecutionStep() !== 0
            && ($this->getMaxNTriesOfRun() === 0 || $this->getNTriesOfRun() <= $this->getMaxNTriesOfRun())
            && time() >= (strtotime($this->getLastUpdateDatetime()) + $this->getSecondsBetweenRuns());
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
            //not results from running process
            || (
                $this->isRunning()
                && time() >= (strtotime($this->getLastUpdateDatetime()) + $this->getMaxLifetimeWithoutResults())
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
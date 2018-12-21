<?php


namespace ProcessManager\process;

use ProcessManager\db\DBManagerInterface;

interface ProcessInterface
{
//    const STATUS_RUN = 'run';
//    const STATUS_RUNNING = 'running';
//    const STATUS_STOP = 'stop';
//    const STATUS_STOPPED = 'stopped';
//    const MODE_REPEAT = 'repeat';
//    const MODE_ONCE = 'once';
    const PR_RUNNING     = 1;
    const PR_NOT_RUNNING = 0;

    /**
     * load process state from db
     *
     * @return $this
     */
    public function loadState();

    /**
     * checking existence of process state in db
     *
     * @return bool
     */
    public function hasState(): bool;

    /**
     * checking existence of process state in db
     *
     * @return bool
     */
    public function saveState(): bool;

    /**
     * @param DBManagerInterface|null $dbManager
     *
     * @return $this
     */
    public function setDBManager(DBManagerInterface $dbManager = null);

    /**
     * @return null|DBManagerInterface
     */
    public function getDBManager();

    /**
     * set id, witch it have in db
     *
     * @param string $id
     *
     * @return $this
     */
    public function setId(string $id);

    /**
     * get id, witch it have in db
     *
     * @return string
     */
    public function getId(): string;

    /**
     * have to be dane before interaction with state
     *
     * @return string
     */
    public function generateIdFromProcessName();

    /**
     * Human readable process name
     *
     * @return string
     */
    public function getProcessName(): string;

    /**
     * Human readable process name
     *
     * @param string $processName
     *
     * @return $this
     */
    public function setProcessName(string $processName);

    /**
     * @param int $pid
     *
     * @return $this
     */
    public function setPid(int $pid);

    /**
     * @return int|null
     */
    public function getPid();

    /**
     * Cant be more then in parent process
     * for linux it have to be from 20 to 40
     *
     * @param integer $priority
     *
     * @return $this
     */
    public function setPriority(int $priority);

    /**
     * Cant be more then in parent process
     * for linux it have to be from 20 to 40
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * @return int Step of execution, 0 if execution is not needed
     */
    public function getExecutionStep(): int;

    /**
     * @param int $step Step of execution, 0 if execution is not needed
     *
     * @return $this
     */
    public function setExecutionStep(int $step);

    /**
     * @return integer PR_NOT_RUNNING(0) if no running and PR_RUNNING(1) if running
     */
    public function isRunning(): int;

    /**
     * @param integer $value PR_NOT_RUNNING(0) if no running and PR_RUNNING(1) if running
     *
     * @return $this
     */
    public function setRunningFlag(int $value);

    /**
     * Current number of try to execute job
     *
     * @return integer
     */
    public function getNTriesOfRun(): int;

    /**
     * Current number of try to execute job
     *
     * @param integer $nTriesOfRun
     *
     * @return $this
     */
    public function setNTriesOfRun(int $nTriesOfRun);

    /**
     * Max available tries to execute job, 0 is infinite
     *
     * @return int
     */
    public function getMaxNTriesOfRun(): int;

    /**
     * Max available tries to execute job, 0 is infinite
     *
     * @param int $NTries
     *
     * @return $this
     */
    public function setMaxNTriesOfRun(int $NTries);

    /**
     * Period job execution
     *
     * @return int
     */
    public function getSecondsBetweenRuns(): int;

    /**
     * Period job execution
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function setSecondsBetweenRuns(int $seconds);

    /**
     * Max lifetime of process without upd lastUpdateDatetime param.
     * After this time job will be stopped.
     *
     * @return int
     */
    public function getMaxLifetimeWithoutResults(): int;

    /**
     * Max lifetime of process without upd lastUpdateDatetime param.
     * After this time job will be stopped.
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function setMaxLifetimeWithoutResults(int $seconds);

    /**
     * @return string
     */
    public function getLastUpdateDatetime();

    /**
     * @param null|string $lastUpdateDatetime
     *
     * @return $this
     */
    public function setLastUpdateDatetime($lastUpdateDatetime);

    /**
     * get some job data by key
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function getDataByKey(string $key);

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setDataByKey($key, $value);

    /**
     * insert error to error log list
     *
     * @param string $error
     *
     * @return $this
     */
    public function addErrorToList($error);

    /**
     * list of last 50 job errors
     *
     * @return string[]
     */
    public function getErrorList(): array;

    /**
     * ask process for start
     *
     * @return bool
     */
    public function isStartNeeded(): bool;

    /**
     * ask process for stop
     *
     * @return bool
     */
    public function isStopNeeded(): bool;

    /**
     * Update priority in child process after fork.
     * Cant be more then in parent process
     * for linux it have to be from 20 to 40
     *
     * @return $this
     */
    public function updateProcessPriority();

    /**
     * Update resources in child process after fork.
     * Needed for correct working conections to db and ect.
     *
     * @return $this
     */
    public function updateResourcesAfterFork();

    /**
     * Signals handlers for process.
     * example:
     *      pcntl_signal(SIGTERM, [$this, 'signalsHandlers']);
     *
     * @return $this
     */
    public function initSignalsHandlers();

    /**
     * Job code that have to be execute
     *
     * @return mixed
     */
    public function start();
}
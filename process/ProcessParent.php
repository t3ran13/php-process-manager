<?php


namespace ProcessManager\process;


use ProcessManager\db\DBManagerInterface;

abstract class ProcessParent extends ProcessParentAbstract
{
    /** @var integer number of processes are running */
    protected $maxRunningProcesses;
    /** @var ProcessRouletteInterface|null */
    protected $processesRoulette;

    /**
     * ProcessParent constructor.
     *
     * @param DBManagerInterface|null $DBManager
     */
    public function __construct(DBManagerInterface $DBManager = null)
    {
        parent::__construct($DBManager);
        $this->processesRoulette = new ProcessRoulette();
    }

    /**
     * number of processes are running
     *
     * @return int
     */
    public function getMaxRunningProcesses(): int
    {
        return $this->maxRunningProcesses;
    }

    /**
     * number of processes are running
     *
     * @param int $n
     *
     * @return $this
     */
    public function setMaxRunningProcesses(int $n)
    {
        $this->maxRunningProcesses = $n;

        return $this;
    }

    /**
     * @param ProcessInterface $process
     *
     * @return $this
     */
    public function addProcess(ProcessInterface $process)
    {
        $this->processesRoulette->addProcess($process);

        return $this;
    }

    /**
     * @return ProcessRouletteInterface
     */
    protected function getProcessRoulette()
    {
        return $this->processesRoulette;
    }

    /**
     * @param ProcessRouletteInterface|null $processesRoulette
     *
     * @return $this
     */
    public function setProcessesRoulette(ProcessRouletteInterface $processesRoulette = null)
    {
        $this->processesRoulette = $processesRoulette;

        return $this;
    }

    /**
     * @param ProcessInterface|ProcessAbstract $process
     *
     * @return int process pid
     */
    protected function forkProcess(ProcessInterface $process)
    {
        /** @var ProcessAbstract $process */
        //before fork
        $pid = pcntl_fork();
        //after fork
        if ($pid === 0) {//child process start
            try {
                $this->clearParentResourcesAfterFork();

                $process->updateResourcesAfterFork();
                $process->updateProcessPriority();
                $process->initSignalsHandlers();
                $process->setPid(getmypid());
                $process->setRunningFlag(1);

                $process->start();

            } catch (\Throwable $e) {

                $msg = '"' . $e->getMessage() . '" ' . PHP_EOL . $e->getTraceAsString();
                echo PHP_EOL . date('Y-m-d H:i:s') . ' process \'' . $process->getJobName() . '\' got exception:'
                    . PHP_EOL . $msg;
                $process->errorInsertToLog(date('Y-m-d H:i:s') . '   ' . $msg);

            } finally {
                $process->setRunningFlag(0);
                exit(1);
            }
        }
        //parent process
//        pcntl_wait($status);

        return $pid;
    }
}
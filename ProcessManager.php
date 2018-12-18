<?php


namespace ProcessManager;


use ProcessManager\db\DBManagerInterface;
use ProcessManager\process\ProcessAbstract;
use ProcessManager\process\ProcessInterface;
use ProcessManager\process\ProcessParent;

class ProcessManager extends ProcessParent
{
    /** @var integer number processes are running now */
    protected $nRunningProcesses;
    private $isStopSignal = false;

    public function initSignalsHandlers()
    {
        pcntl_signal(SIGTERM, [$this, 'signalsHandlers']); //kill
        pcntl_signal(SIGINT, [$this, 'signalsHandlers']); //ctrl+c
        pcntl_signal(SIGHUP, [$this, 'signalsHandlers']); //restart process
//        pcntl_signal(SIGCHLD, [$this, 'signalsHandlers']);
    }

    public function signalsHandlers($signo, $signinfo)
    {
        echo PHP_EOL . ' --- process ' . $this->getProcessName() . ' got signal=' . $signo . ' and signinfo='
            . print_r($signinfo, true);

        switch ($signo) {
            case SIGINT:
            case SIGTERM:
            case SIGHUP:
                $this->isStopSignal = true;
                foreach ($this->getProcessRoulette() as $process) {
                    /** @var ProcessInterface|ProcessAbstract $process */
                    if ($process->isRunning()) {
                        $processPid = $process->getPid();
                        posix_kill($processPid, SIGTERM);
                        echo PHP_EOL . ' --- To process \'' . $process->getProcessName() . '\' sent TERMINATE signal';
                    }
                }
                break;
            default:
        }
    }


    public function start()
    {
        echo PHP_EOL . date('Y-m-d H:i:s') . ' PROCESS_MANAGER is started';
        $this->updateProcessPriority();

        $this->initSignalsHandlers();
        //set main process params
        $this->loadState();
        $this->setPid(getmypid());
        $this->setRunningFlag(1);

        //Main process have to work all time
        while (!$this->isStopNeeded() && !$this->isStopSignal) {
            $this->setLastUpdateDatetime(date('Y-m-d H:i:s'));
            echo PHP_EOL . $this->getLastUpdateDatetime() . ' PROCESS_MANAGER is running';

            //calculate number of running processes before
            $nRunningProcesses = 0;
            foreach ($this->getProcessRoulette() as $process) {
                /** @var ProcessInterface|ProcessAbstract $process */
                $process->loadState();
                if ($process->isRunning()) {
                    $nRunningProcesses++;
                }
            }
            $this->nRunningProcesses = $nRunningProcesses;

            //Roulette starts from last not executed process
            foreach ($this->getProcessRoulette() as $process) {
                /** @var ProcessInterface|ProcessAbstract $process */
                if ($this->nRunningProcesses >= $this->getMaxRunningProcesses()) {
                    break;
                }
                try {
                    $process->loadState();
                    if ($process->isStartNeeded()) {
                        $pid = $this->forkProcess($process);
                        if ($pid > 0) {
                            $this->nRunningProcesses++;
                        }

                    } elseif ($process->isStopNeeded()) {
                        posix_kill($process->getPid(), SIGTERM);
                    }
                } catch (\Throwable $e) {
                    $msg = '"' . $e->getMessage() . '" ' . PHP_EOL . $e->getTraceAsString();
                    echo PHP_EOL . date('Y-m-d H:i:s') . ' process \'' . $process->getJobName() . '\' got exception:'
                        . PHP_EOL . $msg;
                    $process->errorInsertToLog(date('Y-m-d H:i:s') . '   ' . $msg);
                }
            }

            sleep(1);
            pcntl_signal_dispatch();
            $this->loadState();
        }

        //init connect to db
        // get listeners list
        $this->setRunningFlag(0);

        echo PHP_EOL . date('Y-m-d H:i:s') . ' PROCESS_MANAGER is stopped';
    }

    /**
     * clear parent resourses in child process
     *
     * @return void
     */
    public function clearParentResourcesAfterFork()
    {
        $this->setProcessesRoulette(null);
        $this->setDBManager(null);
    }
}
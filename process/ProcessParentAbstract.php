<?php


namespace ProcessManager\process;


abstract class ProcessParentAbstract extends ProcessAbstract implements ProcessParentInterface
{
    /**
     * number of processes are running
     *
     * @return int
     */
    abstract public function getMaxRunningProcesses(): int;

    /**
     * number of processes are running
     *
     * @param int $n
     *
     * @return $this
     */
    abstract public function setMaxRunningProcesses(int $n);

    /**
     * @param ProcessInterface $process
     *
     * @return $this
     */
    abstract public function addProcess(ProcessInterface $process);

    /**
     * @return ProcessRouletteInterface
     */
    abstract protected function getProcessRoulette();

    /**
     * @param ProcessInterface $process
     *
     * @return int process pid
     */
    abstract protected function forkProcess(ProcessInterface $process);

    /**
     * clear parent resourses in child process
     *
     * @return void
     */
    abstract public function clearParentResourcesAfterFork();
}
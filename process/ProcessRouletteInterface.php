<?php


namespace ProcessManager\process;


interface ProcessRouletteInterface
{
    /**
     * @param ProcessInterface $process
     */
    public function addProcess(ProcessInterface $process);

    /**
     * @return ProcessInterface[]
     */
    public function getProcessList();
}
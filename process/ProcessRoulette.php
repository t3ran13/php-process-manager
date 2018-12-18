<?php


namespace ProcessManager\process;


class ProcessRoulette implements ProcessRouletteInterface, \Iterator
{
    private $position = 0;
    private $arrayCounter = 0;
    private $array = [];

    public function __construct() {
        $this->position = 0;
    }

    public function rewind() {
//        $this->position = 0;
        $this->arrayCounter = count($this->array);
    }

    public function current() {
        return $this->array[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        ++$this->position;
        if (!isset($this->array[$this->position])) {
            $this->position = 0;
        }
        --$this->arrayCounter;
        echo PHP_EOL . print_r(__METHOD__ . $this->position, true);
    }

    public function valid() {
//        return isset($this->array[$this->position]);
        return $this->arrayCounter > 0;
    }

    /**
     * @param ProcessInterface $process
     */
    public function addProcess(ProcessInterface $process)
    {
        $this->array[] = $process;
    }

    /**
     * @return ProcessInterface[]
     */
    public function getProcessList()
    {
        return $this->array;
    }
}
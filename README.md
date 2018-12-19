# php-process-manager
Process manager on PHP

## Install Via Composer
```
composer require t3ran13/php-process-manager
```

## Basic Usage

If you need save process state to db
```php
<?php
namespace MyApp;

use ProcessManager\db\RedisManager;
use ProcessManager\ProcessManager;

$db = new RedisManager();

$pm = (new ProcessManager($db))
    ->setProcessName('MainProcess')
    ->setMaxRunningProcesses(2);
if ($pm->hasState()) {
    $pm->loadState();
} else {
    $pm->setPriority(25)
        ->setExecutionStep(1)
        ->setMaxNTriesOfRun(0)
        ->setSecondsBetweenRuns(60)
        ->setMaxLifetimeWithoutResults(30)
        ->saveState();
}

$BEP = (new BlockchainExplorerProcess($db))
    ->setProcessName('BlockchainExplorerProcess');
if ($BEP->hasState()) {
    $BEP->loadState();
} else {
    $BEP->setPriority(30)
        ->setExecutionStep(1)
        ->setMaxNTriesOfRun(7)
        ->setSecondsBetweenRuns(60)
        ->setMaxLifetimeWithoutResults(30)
        ->saveState();
}

$test = new PostIsCreatedHandler($db);
if ($test->hasState()) {
    $test->loadState();
} else {
    $test->setProcessName('PostIsCreatedHandler')
        ->setPriority(35)
        ->setExecutionStep(1)
        ->setMaxNTriesOfRun(0)
        ->setSecondsBetweenRuns(3)
        ->setMaxLifetimeWithoutResults(6)
        ->saveState();
}

$pm->addProcess($BEP)
    ->addProcess($test);
$pm->start();

```
or without state saving
```php
<?php
namespace MyApp;

use ProcessManager\db\RedisManager;
use ProcessManager\ProcessManager;

$db = new RedisManager();

$pm = (new ProcessManager($db))
    ->setProcessName('MainProcess')
    ->setMaxRunningProcesses(2)
    ->setPriority(25)
    ->setExecutionStep(1)
    ->setMaxNTriesOfRun(0)
    ->setSecondsBetweenRuns(60)
    ->setMaxLifetimeWithoutResults(30);

$BEP = (new BlockchainExplorerProcess($db))
    ->setProcessName('BlockchainExplorerProcess')
    ->setPriority(30)
    ->setExecutionStep(1)
    ->setMaxNTriesOfRun(7)
    ->setSecondsBetweenRuns(60)
    ->setMaxLifetimeWithoutResults(30);

$test = new PostIsCreatedHandler($db);
$test->setProcessName('PostIsCreatedHandler')
    ->setPriority(35)
    ->setExecutionStep(1)
    ->setMaxNTriesOfRun(0)
    ->setSecondsBetweenRuns(3)
    ->setMaxLifetimeWithoutResults(6)
    ->saveState();

$pm->addProcess($BEP)
    ->addProcess($test);
$pm->start();

```


#### Process creation

```php
<?php
namespace MyApp;

use ProcessManager\process\ProcessAbstract;

class MyProcess extends ProcessAbstract
{
    private   $isStopSignal = false;

    public function initSignalsHandlers()
    {
        pcntl_signal(SIGTERM, [$this, 'signalsHandlers']); //kill
        pcntl_signal(SIGINT, [$this, 'signalsHandlers']); //ctrl+c
        pcntl_signal(SIGHUP, [$this, 'signalsHandlers']); //restart process
    }

    public function signalsHandlers($signo, $signinfo)
    {
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
            case SIGHUP:
                $this->isStopSignal = true;
                break;
            default:
        }
    }

    public function start()
    {
        echo PHP_EOL . date('Y-m-d H:i:s') . " {$this->getProcessName()} is started";

        while (!$this->isStopNeeded() && !$this->isStopSignal) {
            //some code
            pcntl_signal_dispatch();
        }
    }
    
}
```

or you can create own

```php
<?php
namespace MyApp;

use ProcessManager\process\ProcessInterface;

class MyProcess implements ProcessInterface
{
    // your methods
}
```

## DB manager

Process manager has ready DB manager realization for working with Redis and has next DB structure:

```
- DB0
    - {keyPrefix}:{id}:className
    - {keyPrefix}:{id}:processName
    - {keyPrefix}:{id}:priority
    - {keyPrefix}:{id}:pid
    - {keyPrefix}:{id}:executionStep
    - {keyPrefix}:{id}:isRunning
    - {keyPrefix}:{id}:nTriesOfRun
    - {keyPrefix}:{id}:maxNTriesOfRun
    - {keyPrefix}:{id}:secondsBetweenRuns
    - {keyPrefix}:{id}:maxLifetimeWithoutResults
    - {keyPrefix}:{id}:lastUpdateDatetime
    - {keyPrefix}:{id}:data:*
    - {keyPrefix}:{id}:errors:*
    
    - {keyPrefix}:listeners:{id}:last_update_datetime
    - {keyPrefix}:listeners:{id}:status
    - {keyPrefix}:listeners:{id}:mode
    - {keyPrefix}:listeners:{id}:pid
    - {keyPrefix}:listeners:{id}:handler
    - {keyPrefix}:listeners:{id}:data:last_block
    - {keyPrefix}:listeners:{id}:conditions:{n}:key
    - {keyPrefix}:listeners:{id}:conditions:{n}:value
    
    - {keyPrefix}:events:{listener_id}:{block_n}:{trx_n_in_block}
    
```

Or you can create own DB manager
    
    

```php
<?php
namespace MyApp;

use ProcessManager\db\DBManagerInterface;

class MyDBManager implements DBManagerInterface
{
    public function newConnect(){
        // TODO: Implement newConnect() method.
    }
    public function updProcessStateById($id,$fields){
        // TODO: Implement updProcessStateById() method.
    }
    public function getProcessStateById($id,$field = null){
        // TODO: Implement getProcessStateById() method.
    }
    public function addErrorToList($id,string $error){
        // TODO: Implement addErrorToList() method.
    }
   
}
```
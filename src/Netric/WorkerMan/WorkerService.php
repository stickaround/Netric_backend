<?php

namespace Netric\WorkerMan;

use InvalidArgumentException;
use Netric\WorkerMan\Queue\QueueInterface;
use Netric\Application\Application;
use RuntimeException;

/**
 * Service used to interact with the worker manager
 */
class WorkerService
{
    /**
     * A job queue to send jobs to an pull job info from
     *
     * @var QueueInterface
     */
    private $jobQueue = null;

    /**
     * Array of workers to handle jobs
     *
     * @var WorkerInterface[]
     */
    private $workers = null;

    /**
     * Factory for loading workers
     */
    private WorkerFactory $workerFactory;

    /**
     * Host name of the server
     */
    private string $jobQueueServer = "";

    /**
     * Setup the WorkerService
     *
     * @param Application $application Instance of current running netric application
     * @param QueueInterface $queue The Queue used to push jobs and pull info
     */
    public function __construct(
        QueueInterface $queue,
        WorkerFactory $workerFactory,
        string $jobQueueServer
    ) {
        $this->jobQueue = $queue;
        $this->workerFactory = $workerFactory;
        $this->jobQueueServer = $jobQueueServer;
    }

    /**
     * Add a job to the queue and return immediately with a job handle (id)
     *
     * @param string $workerName The name of the worker to run
     * @param array $jobData Data to be passed to the job
     * @return string A unique id/handle to the queued job
     */
    public function doWorkBackground($workerName, array $jobData)
    {
        return $this->jobQueue->doWorkBackground($workerName, $jobData);
    }

    /**
     * Queue a job to be run in x number of seconds
     *
     * @parma string $workerName The name fo the worker to run
     * @param array $jobData The payload to send to the worker
     * @param int $delayedSecond The number of seconds the job will be delayed before running
     */
    public function doWorkDelayed(string $workerName, array $jobData, int $delayedSeconds): void
    {
        $this->jobQueue->doWorkBackgroundDelayed($workerName, $jobData, $delayedSeconds);
    }

    /**
     * Do work at an interval
     *
     * @param string $workerName
     * @param array $jobData
     * @param int $intervalSeconds
     * @return void
     */
    public function doWorkEvery(string $workerName, array $jobData, int $intervalSeconds): void
    {
        throw new RuntimeException("Not yet implemented");
    }

    /**
     * Process the job queue or wait for new jobs
     *
     * @return bool true on success, false on a failure
     */
    public function processJobQueue()
    {
        // Make sure that we have loaded the workers
        if ($this->workers === null) {
            $this->loadWorkers();
        }

        // Wait for jobs and send them to workers
        return $this->jobQueue->dispatchJobs();
    }

    /**
     * Process a single job
     *
     * @param string $workerName
     * @param array $payload
     * @return bool
     */
    public function processJob(string $workerName, array $payload): bool
    {
        $worker = $this->workerFactory->getWorkerByName($workerName);
        if (!$worker) {
            throw new InvalidArgumentException("Worker $workerName not found");
        }

        $job = new Job();
        $job->setWorkload($payload);
        return $worker->work($job);
    }

    /**
     * Load up workers
     */
    private function loadWorkers()
    {
        $this->workers = [];

        // Load up all workers from the ../Worker directory
        foreach (glob(__DIR__ . "/Worker/*Worker.php") as $filename) {
            // Add each worker as a listener
            $workerName = substr(basename($filename), 0, - (strlen(".php")));
            $workerClass = __NAMESPACE__ . "\\Worker\\" . $workerName;
            $worker = $this->workerFactory->getWorkerByName($workerClass);
            if (!$worker) {
                throw new RuntimeException("Could not load worker: " . $workerClass);
            }
            $this->workers[$workerClass] = $worker;
            $this->jobQueue->addWorker($workerClass, $this->workers[$workerClass]);
        }
    }
}

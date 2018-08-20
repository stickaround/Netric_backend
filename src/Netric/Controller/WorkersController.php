<?php
/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2016 Aereus
 */
namespace Netric\Controller;

use Netric\Mvc;
use Netric\Application\Response\ConsoleResponse;
use Netric\Permissions\Dacl;
use Netric\Entity\ObjType\UserEntity;
use Netric\WorkerMan\WorkerService;
use Netric\Request\RequestInterface;
use Netric\Application\Application;

/**
 * Controller used for interacting with workers from the command line (or API)
 */
class WorkersController extends Mvc\AbstractController
{
    /**
     * Worker for interacting with workers
     *
     * @var WorkerService
     */
    private $workerService = null;
    
    /**
     * Since the only methods in this class are console then we allow for anonymous
     *
     * @return Dacl
     */
    public function getAccessControlList()
    {
        $dacl = new Dacl();
        $dacl->allowGroup(UserEntity::GROUP_EVERYONE);
        return $dacl;
    }

    /**
     * Optionally override the default worker service
     *
     * This will most likely be used in testing and automation
     *
     * @param WorkerService $workerService
     * @return void
     */
    public function setWorkerService(WorkerService $workerService)
    {
        $this->workerService = $workerService;
    }

    /**
     * Install netric by initializing the application db and default account
     *
     * Options:
     *  --deamon = 1 If set then we will not print any output
     *  --runtime = [seconds] The number of seconds to run before returning
     */
    public function consoleProcessAction()
    {
        $request = $this->getRequest();
        $application = $this->getApplication();
        $response = new ConsoleResponse($application->getLog());

        /*
         * Check if we are suppressing output of the response.
         * This is most often used in unit tests.
         */
        if ($request->getParam("suppressoutput")) {
            $response->suppressOutput(true);
        }

        // Get application level service locator
        $serviceManager = $application->getServiceManager();

        // Get the worker service if not already set
        if (!$this->workerService) {
            $this->workerService = $serviceManager->get(WorkerService::class);
        }

        // Process the jobs for an hour
        $timeStart = time();
        if ($request->getParam("runtime") && is_numeric($request->getParam("runtime"))) {
            $timeExit = time() + (int) $request->getParam("runtime");
        } else {
            $timeExit = time() + (60 * 60); // 1 hour
        }
        $numProcessed = 0;

        // Process each job, one at a time
        while ($this->workerService->processJobQueue()) {
            // Increment the number of jobs processed
            $numProcessed++;

            // We break once per hour to restart the script (PHP was not meant to run forever)
            if (($timeStart + time()) >= $timeExit) {
                break;
            }

            // Check to see if the request has been sent a stop signal
            if ($request->isStopping()) {
                $response->writeLine("WorkersController->consoleProcessAction: Exiting job processor");
                break;
            }

            // Be nice to the CPU
            sleep(1);
        }

        $textToWrite = "WorkersController->consoleProcessAction: Processed $numProcessed jobs";
        if (!$request->getParam("daemon")) {
            $response->writeLine($textToWrite);
        } else {
            $application->getLog()->info($textToWrite);
        }

        return $response;
    }

    /**
     * Action for scheduling workers
     */
    public function consoleScheduleAction()
    {
        $application = $this->getApplication();
        $config = $application->getConfig();
        $response = new ConsoleResponse($application->getLog());
        $request = $this->getRequest();

        /*
         * Check if we are suppressing output of the response.
         * This is most often used in unit tests
         */
        if ($request->getParam("suppressoutput")) {
            $response->suppressOutput(true);
        }

        /*
         * Set the universal lock timeout which makes sure only one instance
         * of this is run within the specified number of seconds
         */
        $lockTimeout = ($request->getParam("locktimeout")) ? $request->getParam("locktimeout") : 120;

        // Get application level service locator
        $serviceManager = $application->getServiceManager();

        // Get the worker service if not already set
        if (!$this->workerService) {
            $this->workerService = $serviceManager->get(WorkerService::class);
        }

        // Set a lock name to assure we only have one instance of the scheduler running (per version)
        $uniqueLockName = 'WorkerScheduleAction-' . $config->version;

        // We only ever want one scheduler running so create a lock that expires in 2 minutes
        if (!$application->acquireLock($uniqueLockName, $lockTimeout)) {
            $response->writeLine("WorkersController->consoleScheduleAction: Exiting because another instance is running");
            return $response;
        }

        // Emit a background job for every account to run scheduled jobs every minute
        $this->queueScheduledJobs($application, $request, $uniqueLockName, $lockTimeout);

        // Make sure we release the lock so that the scheduler can always be run
        $application->releaseLock($uniqueLockName);

        $exitMessage = "WorkersController->consoleScheduleAction: Exiting job scheduler";
        if (!$request->getParam("daemon")) {
            $response->writeLine($exitMessage);
        } else {
            $application->getLog()->info($exitMessage);
        }

        return $response;
    }

    /**
     * The main scheduled jobs loop will schedule jobs to be run every minute
     *
     * This is essentially a heartbeat that emits a background job for every
     * account to run any scheduled jobs the account may have.
     *
     * @param Application $application
     * @param RequestInterface $request
     * @param string $uniqueLockName
     * @return void
     */
    private function queueScheduledJobs(
        Application $application,
        RequestInterface $request,
        $uniqueLockName
    ) {
        $running = true;
        while ($running) {
            /*
             * Get all accounts - this function queries the DB each time so we
             * do not need to refresh since this is a long-lived process
             */
            $accounts = $application->getAccounts();
            foreach ($accounts as $account) {
                /*
                 * The ScheduleRunner worker will check for any scheduled jobs
                 * for each account and spawn more background processes. This helps
                 * us distribute the load. If ScheduledWork is taking too long, we
                 * can simply add more worker machines to the cluster
                 */
                $jobData = ['account_id'=>$account->getId()];
                $this->workerService->doWorkBackground("ScheduleRunner", $jobData);
            }

            // Renew the lock to make sure we do not expire since it times out in 2 minutes
            $application->extendLock($uniqueLockName);

            // Exit if we have received a stop signal or are only supposed to run once
            if ($request->isStopping() || $request->getParam('runonce')) {
                // Immediate break the main while loop
                $running = false;
            } else {
                // Sleep for a minute before checking for the next scheduled job
                sleep(60);
            }
        }

        return;
    }
}
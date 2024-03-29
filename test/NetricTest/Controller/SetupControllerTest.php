<?php

namespace NetricTest\Controller;

use PHPUnit\Framework\TestCase;
use Netric\Request\HttpRequest;
use Netric\Account\Account;
use Netric\Account\AccountContainerInterface;
use Netric\Application\Application;
use Netric\Authentication\AuthenticationService;
use Netric\Authentication\AuthenticationIdentity;
use Netric\Controller\SetupController;
use Netric\Log\LogInterface;
use Netric\Account\AccountSetup;
use Ramsey\Uuid\Uuid;

/**
 * Test querying ElasticSearch server
 *
 * Most tests are inherited from IndexTestsAbstract.php.
 * Only define index specific tests here and try to avoid name collision with the tests
 * in the parent class. For the most part, the parent class tests all public functions
 * so private functions should be tested below.
 *
 * @group integration
 */
class SetupControllerTest extends TestCase
{
    /**
     * Initialized controller with mock dependencies
     */
    private SetupController $setupController;

    /**
     * Dependency mocks
     */
    private AuthenticationService $mockAuthService;
    private Account $mockAccount;
    private ModuleService $moduleService;

    protected function setUp(): void
    {
        // Create mocks
        $this->accountSetup = $this->createMock(AccountSetup::class);
        $this->mockLog = $this->createMock(LogInterface::class);
        $this->mockApplication = $this->createMock(Application::class);

        // Provide identity for mock auth service
        $this->mockAuthService = $this->createMock(AuthenticationService::class);
        $ident = new AuthenticationIdentity('blahaccount', 'blahuser');
        $this->mockAuthService->method('getIdentity')->willReturn($ident);

        // Return mock authenticated account
        $this->mockAccount = $this->createStub(Account::class);
        $this->mockAccount->method('getAccountId')->willReturn(Uuid::uuid4()->toString());

        $this->accountContainer = $this->createMock(AccountContainerInterface::class);
        $this->accountContainer->method('loadById')->willReturn($this->mockAccount);

        // Create the controller with mocks
        $this->setupController = new SetupController(
            $this->accountContainer,
            $this->mockAuthService,
            $this->accountSetup,
            $this->mockLog,
            $this->mockApplication,
        );

        $this->setupController->testMode = true;
    }

    /**
     * Make sure the latest version gets returned when queried
     */
    public function testGetVersionAction()
    {
        $ret = $this->setupController->getVersionAction();
        $this->assertNotNull($ret);
    }

    /**
     * Test creating a new account
     *
     * @return void
     */
    public function testPostCreateAccountAction()
    {
        $tempAccountName = 'testpostcreateaccount';
        $accountId = Uuid::uuid4()->toString();
        $accountDetails = [
            "account_id" => $accountId,
            "name" => $tempAccountName,
            "database" => "test_db",
            "description" => "Test Description"
        ];

        // Queue for cleanup
        $this->accountsToCleanup[] = $tempAccountName;

        // Set params in the request
        $request = new HttpRequest();
        $request->setBody(json_encode([
            'account_name' => $tempAccountName,
            'username' => 'test2',
            'email' => 'test2@netric.com',
            'password' => 'PassRandNeverLogin!',
        ]));

        $this->accountSetup->method('getUniqueAccountName')->willReturn($tempAccountName);
        $this->accountSetup->method('createAndInitailizeNewAccount')->willReturn($this->mockAccount);
        $this->mockAccount->method('toArray')->willReturn($accountDetails);

        $request->setParam('buffer_output', 1);
        $response = $this->setupController->postCreateAccountAction($request);
        $output = $response->getOutputBuffer();

        // Make sure the accounts
        $this->assertNotEmpty($output['account_id']);
        $this->assertEquals($output['account_id'], $accountId);
        $this->assertEquals($tempAccountName, $output['name']);
    }
}

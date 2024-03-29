<?php

namespace NetricTest\PaymentGateway;

use Netric\PaymentGateway\AuthDotNetGateway;
use Netric\PaymentGateway\PaymentMethod\CreditCard;
use Netric\PaymentGateway\PaymentMethod\BankAccount;
use Netric\PaymentGateway\ChargeResponse;
use PHPUnit\Framework\TestCase;
use NetricTest\Bootstrap;
use \net\authorize\api\constants\ANetEnvironment;
use Netric\Account\Account;
use Netric\Entity\EntityLoaderFactory;
use Netric\Entity\ObjType\ContactEntity;
use Netric\EntityDefinition\ObjectTypes;
use Ramsey\Uuid\Uuid;

/**
 * Integration test against authorize.net
 *
 * @group integration
 */
class AuthDotNetGatewayTest extends TestCase
{
    /**
     * Authorize.net sandbox login ID
     */
    const API_LOGIN = '47zCW38But';

    /**
     * Authorize.net sandbox transaction key
     */
    const API_TRANSACTION_KEY = '22hj5fXD3Z2p7Q5W';

    /**
     * Authorize.net sandbox key (not sure yet what this is used for)
     */
    const API_KEY = 'Simon';

    /**
     * Authorize.net sandbox endpoint used to simulate requests
     */
    const AUTH_NET_TEST_URL = ANetEnvironment::SANDBOX;

    /**
     * Payment gateway to test
     *
     * @var AuthDotNetGateway
     */
    private $gateway = null;

    /**
     * Cleanup any created profiles
     *
     * @var string[]
     */
    private $profilesToDelete = [];

    /**
     * Setup authorize.net with test account
     */
    protected function setUp(): void
    {
        $this->gateway = new AuthDotNetGateway(
            self::API_LOGIN,
            self::API_TRANSACTION_KEY,
            self::AUTH_NET_TEST_URL
        );
    }

    /**
     * Cleanup any created resrouces
     */
    protected function tearDown(): void
    {
        foreach ($this->profilesToDelete as $profileToken) {
            try {
                $this->gateway->deleteProfile($profileToken);
            } catch (\Exception $ex) {
                // Print error but do not fail the test
                echo "Could not delete profile $profileToken: " . $ex->getMessage();
            }
        }

        parent::tearDown();
    }

    /**
     * Get the test account
     *
     * @return Account
     */
    private function getAccount(): Account
    {
        return Bootstrap::getAccount();
    }

    /**
     * Create a test customer for interacting with
     *
     * @return ContactEntity
     */
    private function getTestCustomer()
    {
        $serviceManager = Bootstrap::getAccount()->getServiceManager();
        $entityLoader = $serviceManager->get(EntityLoaderFactory::class);
        $customer = $entityLoader->create(ObjectTypes::CONTACT, $this->getAccount()->getAccountId());
        $customer->setValue('entity_id', Uuid::uuid4()->toString());
        $customer->setValue('first_name', 'Ellen');
        $customer->setValue('last_name', 'Johnson');
        $customer->setValue('company', 'Souveniropolis');
        $customer->setValue('billing_street', '14 Main Street');
        $customer->setValue('billing_city', 'Pecan Springs');
        $customer->setValue('billing_district', 'TX');
        $customer->setValue('billing_postal_code', '44628');
        $customer->setValue('email', 'test@netric.com');
        return $customer;
    }

    public function testCreatePaymentProfileCreditCard()
    {
        $customer = $this->getTestCustomer();
        $card = new CreditCard();
        $card->setCardNumber('4111111111111111');
        $card->setExpiration(2038, 12);
        $card->setCardCode('123');

        $profileToken = $this->gateway->createPaymentProfileCard($customer, $card);
        $this->profilesToDelete[] = $profileToken;
        $this->assertNotEmpty($profileToken, $this->gateway->getLastError());
    }

    /**
     * Make sure if we try to save the same card to the profile it succeeds
     *
     * @return void
     */
    public function testCreatePaymentProfileCreditCardDuplicate()
    {
        $customer = $this->getTestCustomer();
        $card = new CreditCard();
        $card->setCardNumber('4111111111111111');
        $card->setExpiration(2038, 12);
        $card->setCardCode('123');

        $profileToken = $this->gateway->createPaymentProfileCard($customer, $card);
        $this->assertNotEmpty($profileToken, $this->gateway->getLastError());

        // Try again and make sure we get an error because we need to make sure there is
        // only one profile per card and customer
        $profileTokenAgain = $this->gateway->createPaymentProfileCard($customer, $card);
        $this->assertEmpty($profileTokenAgain);
        // Make sure there is an error
        $this->assertNotEmpty($this->gateway->getLastError());
    }

    /**
     * Save a bank account
     *
     * @return void
     */
    public function testCreateProfileBankAccount()
    {
        // Create a customer and change the zipcode
        $customer = $this->getTestCustomer();
        $customer->setValue('billing_postal_code', '44629');

        $bankAccount = new BankAccount();
        $bankAccount->setAccountType('checking');
        $bankAccount->setRoutingNumber('125000105');
        $bankAccount->setAccountNumber('1234567890');
        $bankAccount->setNameOnAccount('Ellen Johnson');
        $bankAccount->setBankName('Wells Fargo Bank NA');

        $profileToken = $this->gateway->createPaymentProfileBankAccount($customer, $bankAccount);
        $this->profilesToDelete[] = $profileToken;
        $this->assertNotEmpty($profileToken, $this->gateway->getLastError());
    }

    /**
     * Make sure we can create a credit card and a bank profile for the same customer
     *
     * @return void
     */
    public function testCreatePaymentProfileTwoTypes()
    {
        $customer = $this->getTestCustomer();
        $card = new CreditCard();
        $card->setCardNumber('4111111111111111');
        $card->setExpiration(2038, 12);
        $card->setCardCode('123');

        $profileToken = $this->gateway->createPaymentProfileCard($customer, $card);
        $this->profilesToDelete[] = $profileToken;
        $this->assertNotEmpty($profileToken, $this->gateway->getLastError());

        $bankAccount = new BankAccount();
        $bankAccount->setAccountType('checking');
        $bankAccount->setRoutingNumber('125000105');
        $bankAccount->setAccountNumber('1234567890');
        $bankAccount->setNameOnAccount('Ellen Johnson');
        $bankAccount->setBankName('Wells Fargo Bank NA');

        $profileToken = $this->gateway->createPaymentProfileBankAccount($customer, $bankAccount);
        $this->profilesToDelete[] = $profileToken;
        $this->assertNotEmpty($profileToken, $this->gateway->getLastError());
    }



    /**
     * Test charging a saved payment profile
     *
     * @return void
     */
    public function testChargeProfile()
    {
        $customer = $this->getTestCustomer();
        $card = new CreditCard();
        $card->setCardNumber('4111111111111111');
        $card->setExpiration(2038, 12);
        $card->setCardCode('123');

        // Save a new token to the API
        $profileToken = $this->gateway->createPaymentProfileCard($customer, $card);
        $this->assertNotEmpty($profileToken, $this->gateway->getLastError());
        $this->profilesToDelete[] = $profileToken;

        // Create a local netric payment_profile entity with the token above
        $serviceManager = Bootstrap::getAccount()->getServiceManager();
        $entityLoader = $serviceManager->get(EntityLoaderFactory::class);
        $paymentProfile = $entityLoader->create(
            ObjectTypes::SALES_PAYMENT_PROFILE,
            $this->getAccount()->getAccountId()
        );
        $paymentProfile->setValue('token', $profileToken);

        $result = $this->gateway->chargeProfile($paymentProfile, rand(1, 1000));
        $this->assertNotEmpty($result, $this->gateway->getLastError());
    }

    /**
     * Test a one-time charge using a credit card
     *
     * @return void
     */
    public function testChargeCard()
    {
        $customer = $this->getTestCustomer();

        $card = new CreditCard();
        $card->setCardNumber('4111111111111111');
        $card->setExpiration(2038, 12);
        $card->setCardCode('123');
        $response = $this->gateway->chargeCard($customer, $card, rand(1, 1000));
        $this->assertEquals(ChargeResponse::STATUS_APPROVED, $response->getStatus());
    }

    /**
     * Test a failing one-time charge
     *
     * @return void
     */
    public function testChargeCardFail()
    {
        $customer = $this->getTestCustomer();

        $card = new CreditCard();
        $card->setCardNumber('5111111111111111'); // bad number
        $card->setExpiration(2038, 12);
        $card->setCardCode('123');
        $response = $this->gateway->chargeCard($customer, $card, rand(1, 1000));
        $this->assertEquals(ChargeResponse::STATUS_DECLINED, $response->getStatus());
    }
}

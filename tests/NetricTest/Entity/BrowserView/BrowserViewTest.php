<?php
/**
 * Test a browser view object
 */
namespace NetricTest\Entity\BrowserView;

use Netric\Entity\BrowserView\BrowserView;
use Netric\EntityQuery\Where;
use PHPUnit\Framework\TestCase;
use Netric\Entity\FormsFactory;
use NetricTest\Bootstrap;
use Netric\Entity\ObjType\UserEntity;
use Netric\EntityDefinition\ObjectTypes;

class BrowserViewTest extends TestCase
{
    /**
     * Tennant account
     *
     * @var \Netric\Account\Account
     */
    private $account = null;

    /**
     * Form service
     *
     * @var \Netric\Entity\Form
     */
    private $formService = null;

    /**
     * Administrative user
     *
     * We test for this user since he will never have customized forms
     *
     * @var \Netric\User
     */
    private $user = null;

    /**
     * Setup each test
     */
    protected function setUp(): void
{
        $this->account = Bootstrap::getAccount();
        $sm = $this->account->getServiceManager();
        $this->formService = $sm->get(FormsFactory::class);
        $this->user = $this->account->getUser(UserEntity::USER_SYSTEM);
    }

    /**
     * Make sure we can convert a query to an array
     */
    public function testToAndFromArray()
    {
        // Load the new view
        $view = new BrowserView();
        $viewData = array(
            'obj_type' => ObjectTypes::NOTE,
            'conditions' => array(
                array(
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'user_id',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => -3
                ),
            ),
        );
        $view->fromArray($viewData);

        // Make sure toArray returns the same thing (remove null and empty first)
        $viewArray = $view->toArray();
        $viewArray = array_filter($viewArray, function ($val) {
            return !empty($val);
        });
        $this->assertEquals($viewData, $viewArray);
    }
}

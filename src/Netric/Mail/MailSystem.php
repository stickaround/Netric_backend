<?php

declare(strict_types=1);

namespace Netric\Mail;

use Netric\Account\AccountContainer;
use RuntimeException;

/**
 * The MailSystem is used to interact with the global netric mailsystem which
 * may or may not call external services as the system expands.
 */
class MailSystem implements MailSystemInterface
{
    /**
     * The top and second level domain root that will be used to
     * create a third level domain that is unique to the account.
     *
     * @var string
     */
    private string $localhostRoot = "";

    /**
     * Account container used to load accounts
     *
     * @var AccountContainer
     */
    private AccountContainer $accountContainer;

    /**
     * Setup dependencies
     */
    public function __construct(string $localhostRoot, AccountContainer $accountContainer)
    {
        $this->localhostRoot = $localhostRoot;
        $this->accountContainer = $accountContainer;
    }

    /**
     * Returns the default domain for an account
     *
     * @return string The domain that should be used by default for an account
     */
    public function getDefaultDomain(string $accountId): string
    {
        $account = $this->accountContainer->loadById($accountId);

        // This should never really happen, but just in case exit since the caller
        // is trying to load an account that does not exist or is no longer active
        if (!$account) {
            throw new RuntimeException('Account ID ' . $accountId . ' is not valid');
        }

        // Return [accountName].root.com (usually netric.com)
        return $account->getName() . "." . $this->localhostRoot;
    }

    /**
     * This looks for the account ID associated with a domain
     *
     * @return string UUID of the account that owns this domain
     */
    public function getAccountIdFromDomain(string $domain): string
    {
        $domainParts = explode(".", $domain);
        // The first part should be the account, let's test that theory
        if (count($domainParts) === 3 && isset($domainParts[0])) {
            $account = $this->accountContainer->loadByName($domainParts[0]);
            if ($account) {
                return $account->getAccountId();
            }
        }

        // Nothing found, this could be a very common scneario since anyone can
        // attempt to send email to random addresses from the outside world.
        return "";
    }
}
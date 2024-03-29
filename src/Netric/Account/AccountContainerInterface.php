<?php

namespace Netric\Account;

use Netric\Application\DataMapperInterface;
use Netric\Application\Application;
use Netric\Cache;
use Netric\Error\Error;
use Netric\Error\ErrorAwareInterface;

/**
 * IdentityMapper interface for loading accounts
 */
interface AccountContainerInterface
{
    /**
     * Load an account by id
     *
     * @param string $accountId The unique id of the account to get
     * @return Account on success, null on failure
     */
    public function loadById(string $accountId): ?Account;

    /**
     * Get an account by the unique name
     *
     * @param string $name
     * @return Account on success, null on failure
     */
    public function loadByName(string $name): ?Account;

    /**
     * Delete an account
     *
     * @param Account $account The account to delete
     * @return bool true on success, false on failure
     * @throws \RuntimeException If account is not a valid account with an ID
     */
    public function deleteAccount(Account $account): bool;

    /**
     * Create a new account and return the ID
     *
     * @param string $name A unique name for this account
     * @return string Unique id of the created account, 0 on failure
     */
    public function createAccount(string $name): string;

    /**
     * Update an existing account
     *
     * @param Account $accountToUpdate
     * @return bool true on success, false on failure
     */
    public function updateAccount(Account $accountToUpdate);

    /**
     * Get array of all active account IDs
     *
     * This will be uncached and return only the ID and name
     *
     *  @return array [['account_id'=>ID, 'name'=>NAME]]
     */
    public function getAllActiveAccounts(): array;

    /**
     * Get IDs of all accounts to be billed
     *
     * @return string[] The account_id(s) of all accounts due to be billed
     */
    public function getAccountsToBeBilled(): array;
}

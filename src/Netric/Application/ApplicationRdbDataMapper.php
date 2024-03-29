<?php

namespace Netric\Application;

use InvalidArgumentException;
use Netric\Account\Account;
use Netric\Account\Module\DataMapper\ModuleRdbDataMapper;
use Netric\Error\ErrorAwareInterface;
use Netric\Error\Error;
use Netric\Db\Relational\PgsqlDb;
use Netric\Db\Relational\RelationalDbInterface;
use Netric\EntityDefinition\DataMapper\EntityDefinitionRdbDataMapper;
use Netric\Entity\DataMapper\EntityPgsqlDataMapper;
use Ramsey\Uuid\Uuid;

/**
 * Access account data in a relational database
 */
class ApplicationRdbDataMapper implements DataMapperInterface, ErrorAwareInterface
{
    /**
     * Handle to database
     *
     * @var RelationalDbInterface
     */
    private $database = null;

    /**
     * Host of db server
     *
     * @var string
     */
    private $host = "";

    /**
     * Database name
     *
     * @var string
     */
    private $databaseName = "";

    /**
     * Db username
     *
     * @var string
     */
    private $username = "";

    /**
     * Password for username
     *
     * @var string
     */
    private $password = "";

    /**
     * Errors array
     *
     * @var Error[]
     */
    private $errors = [];

    /**
     * Table constants
     */
    const TABLE_ACCOUNT = 'account';
    const TABLE_ACCOUNT_USER = 'account_user';

    /**
     * Construct and initialize dependencies
     *
     * @param string $host
     * @param string $databaseName System database name
     * @param string $username System database username
     * @param string $password System database password
     */
    public function __construct(
        $host,
        $databaseName,
        $username,
        $password
    ) {
        $this->host = $host;
        $this->databaseName = $databaseName;
        $this->username = $username;
        $this->password = $password;

        // Create an instance of the new Relational Database of PgSql
        $this->database = new PgsqlDb(
            $this->host,
            $this->databaseName,
            $this->username,
            $this->password
        );
    }

    /**
     * Get an account by id
     *
     * @param string $id The unique id of the account to get
     * @param Account $account Reference to Account object to initialize
     * @return bool true on success, false on failure/not found
     */
    public function getAccountById($id, Account $account)
    {
        // Check first if we have database connection before getting the account data
        if (!$this->checkDbConnection()) {
            return false;
        }

        $sql = 'SELECT * FROM ' . self::TABLE_ACCOUNT . ' WHERE account_id=:account_id';
        $result = $this->database->query($sql, ["account_id" => $id]);

        if ($result->rowCount()) {
            $row = $result->fetch();
            return $account->fromArray($row);
        }

        return false;
    }

    /**
     * Get an account by the unique name
     *
     * @param string $name The name of the account that we will be getting
     * @param Account $account Reference to Account object to initialize if set
     * @return array|bool Return the account if found, false on failure/not found
     */
    public function getAccountByName($name, Account $account = null)
    {
        // Check first if we have database connection before getting the account data
        if (!$this->checkDbConnection()) {
            return false;
        }

        $sql = 'SELECT * FROM ' . self::TABLE_ACCOUNT . ' WHERE name=:name';
        $result = $this->database->query($sql, ["name" => $name]);

        if ($result->rowCount()) {
            $row = $result->fetch();

            if ($account) {
                return $account->fromArray($row);
            }

            return $row;
        }

        return false;
    }

    /**
     * Get an array of accounts
     *
     * @return array
     *  [['account_id'=>ID, 'name'=>NAME]]
     */
    public function getAccounts(): array
    {
        // Check first if we have database connection before getting the account data
        if (!$this->checkDbConnection()) {
            return false;
        }

        $ret = [];
        $sqlParams = [];

        $sql = 'SELECT * FROM ' . self::TABLE_ACCOUNT . ' WHERE status=1';

        $result = $this->database->query($sql, $sqlParams);
        foreach ($result->fetchAll() as $row) {
            $ret[] = [
                "account_id" => $row['account_id'],
                "name" => $row['name'],
                "database" => $row['database'],
            ];
        }

        return $ret;
    }

    /**
     * Get IDs of all accounts to be billed
     *
     * @return string[] The account_id(s) of all accounts due to be billed
     */
    public function getAccountsToBeBilled(): array
    {
        $ret = [];

        // Check first if we have database connection before getting the account data
        if (!$this->checkDbConnection()) {
            return $ret;
        }

        $sqlParams = [
            'active' => Account::STATUS_ACTIVE,
            'pastdue' => Account::STATUS_PASTDUE
        ];
        $sql = 'SELECT account_id FROM ' . self::TABLE_ACCOUNT . ' WHERE ' .
            '(status=:active OR status=:pastdue)' .
            " AND (billing_next_bill <= now() OR billing_next_bill IS NULL)";

        $result = $this->database->query($sql, $sqlParams);
        foreach ($result->fetchAll() as $row) {
            $ret[] = $row['account_id'];
        }

        return $ret;
    }

    /**
     * Get account and username from email address
     *
     * @param string $emailAddress The email address to pull from
     * @return array("account"=>"accountname", "username"=>"the login username")
     */
    public function getAccountsByEmail($emailAddress)
    {
        $ret = [];

        // Check accounts for a username matching this address
        $sql = 'SELECT 
                    ' . self::TABLE_ACCOUNT . '.name as account, 
                    ' . self::TABLE_ACCOUNT_USER . '.username
                FROM 
                    ' . self::TABLE_ACCOUNT . ', ' . self::TABLE_ACCOUNT_USER . ' 
                WHERE
                    ' . self::TABLE_ACCOUNT . '.account_id=' . self::TABLE_ACCOUNT_USER . '.account_id 
                    AND ' . self::TABLE_ACCOUNT_USER . '.email_address=:email_address';

        $result = $this->database->query($sql, ["email_address" => strtolower($emailAddress)]);
        foreach ($result->fetchAll() as $row) {
            $ret[] = [
                'account' => $row['account'],
                'username' => $row['username'],
            ];
        }

        return $ret;
    }

    /**
     * Set account and username from email address
     *
     * @param string $accountId The id of the account user is interacting with
     * @param string $username The user name - unique to the account
     * @param string $emailAddress The email address to pull from
     * @return bool true on success, false on failure
     */
    public function setAccountUserEmail(string $accountId, $username, $emailAddress)
    {
        $ret = false;

        if (empty($accountId) || empty($username)) {
            return $ret;
        }

        // Delete any existing entries for this user name attached to this account
        $this->database->delete(
            self::TABLE_ACCOUNT_USER,
            ["account_id" => $accountId, "username" => $username]
        );

        // Insert into self::TABLE_ACCOUNT_USER table
        if ($emailAddress) {
            $insertData = [
                "account_id" => $accountId,
                "email_address" => $emailAddress,
                "username" => $username
            ];
            $result = $this->database->insert(
                self::TABLE_ACCOUNT_USER,
                $insertData,
                'account_id'
            );
            $ret = ($result) ? true : false;
        }

        return $ret;
    }

    /**
     * Adds an account to the database
     *
     * @param string $name A unique name for this account
     * @pasram string $orgName Optional organization name. Will use $name of not set
     * @return string Unique id of the created account, exception on failure
     */
    public function createAccount($name, string $orgName = ""): string
    {
        $newAccountId = Uuid::uuid4()->toString();

        // Create account in antsystem
        $insertData = [
            "account_id" => $newAccountId,
            "org_name" => ($orgName) ? $orgName : $name,
            "name" => $name,
        ];

        // If it fails for some reason, it will throw an exception
        $this->database->insert(self::TABLE_ACCOUNT, $insertData);

        return $newAccountId;
    }

    /**
     * Update an existing account
     *
     * @param Account $account The account to save changes to
     * @return bool true on success, false on failure
     */
    public function updateAccount(Account $account): bool
    {
        if (empty($account->getAccountId())) {
            throw new InvalidArgumentException(
                "You tried to update a non-existing account"
            );
        }

        // Select which fields we can update - not all fields should be updated
        // - so we shouldn't just do a toArray/fromArray like we do with entities
        $data = [
            'org_name' => $account->getOrgName(),
            'status' => $account->getStatus(),
            'billing_next_bill' => $account->getBillingNextBill() ?
                $account->getBillingNextBill()->format("Y-m-d") : null,
            'billing_last_billed' =>  $account->getBillingLastBilled() ?
                $account->getBillingLastBilled()->format("Y-m-d") : null,
            'billing_month_interval' => $account->getBillingMonthInterval(),
            'main_account_contact_id' => !empty($account->getMainAccountContactId()) ?
                $account->getMainAccountContactId() : null,
        ];

        return $this->database->update(
            self::TABLE_ACCOUNT,
            $data,
            ["account_id" => $account->getAccountId()]
        );
    }

    /**
     * Delete an account by id
     *
     * @param string $accountId The id of the account user is interacting with
     * @return bool true on success, false on failure - call getLastError for details
     */
    public function deleteAccount(string $accountId): bool
    {
        if (empty($accountId)) {
            throw new \RuntimeException("accountId must be provided");
        }

        // Tables with account_id column to cleanup before deleting the account
        $cleanup = [
            self::TABLE_ACCOUNT_USER,
            EntityDefinitionRdbDataMapper::ENTITY_TYPE_TABLE,
            EntityPgsqlDataMapper::ENTITY_TABLE,
            ModuleRdbDataMapper::TABLE_MODULES,
        ];
        foreach ($cleanup as $table) {
            $this->database->delete($table, ["account_id" => $accountId]);
        }

        // Now delete the actual account
        $ret = $this->database->delete(self::TABLE_ACCOUNT, ["account_id" => $accountId]);

        if ($ret) {
            return true;
        }

        if ($ret === 0) {
            $this->errors[] = new Error("Accountid $accountId does not exists.");
        }

        return false;
    }

    /**
     * Get the last error (if any)
     *
     * @return Error | null
     */
    public function getLastError()
    {
        if (count($this->errors)) {
            return $this->errors[count($this->errors) - 1];
        } else {
            return null;
        }
    }

    /**
     * Get all errors
     *
     * @return \Netric\Error\Error[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Obtain a lock so that only one instance of a process can run at once
     *
     * @param string $uniqueLockName Globally unique lock name
     * @param int $expiresInSeconds Expire after defaults to 1 day or 86400 seconds
     * @return bool true if lock obtained, false if the process name is already locked (running)
     */
    public function acquireLock($uniqueLockName, $expiresInSeconds = 86400)
    {
        if (!$uniqueLockName) {
            throw new \InvalidArgumentException("Unique lock name is required to obtain a lock");
        }

        // Get the process lock
        $sql = "SELECT id, ts_entered FROM worker_process_lock " .
            "WHERE process_name=:process_name";

        $result = $this->database->query($sql, ["process_name" => $uniqueLockName]);
        if ($result->rowCount()) {
            $row = $result->fetch();
            $timeEntered = strtotime($row['ts_entered']);
            $now = time();

            // Check to see if the process has expired (run too long)
            if (($now - $timeEntered) >= $expiresInSeconds) {
                // Update the lock and return true so the caller can start a new process
                $ret = $this->database->update(
                    "worker_process_lock",
                    ["ts_entered" => date('Y-m-d H:i:s')],
                    ["id" => $row['id']]
                );

                if ($ret) {
                    return true;
                }
            }
        } else {
            $insertData = ["process_name" => $uniqueLockName, "ts_entered" => date('Y-m-d H:i:s')];
            $ret = $this->database->insert("worker_process_lock", $insertData, 'id');

            if ($ret) {
                return true;
            }
        }

        $this->errors[] = new Error("Could not create lock: $uniqueLockName");

        // The process is still legitimately running
        return false;
    }

    /**
     * Clear a lock so that only one instance of a process can run at once
     *
     * @param string $uniqueLockName Globally unique lock name
     */
    public function releaseLock($uniqueLockName)
    {
        $this->database->delete("worker_process_lock", ["process_name" => $uniqueLockName]);
    }

    /**
     * Refresh the lock to extend the expires timeout
     *
     * @param string $uniqueLockName Globally unique lock name
     * @return bool true on success, false on failure
     */
    public function extendLock($uniqueLockName)
    {
        $result = $this->database->update(
            "worker_process_lock",
            ["ts_entered" => date('Y-m-d H:i:s')],
            ["process_name" => $uniqueLockName]
        );
        return ($result) ? true : false;
    }

    /**
     * Closes the database connection
     */
    public function close()
    {
        $this->database->close();
    }

    /**
     * Function that will check if we have database connection
     *
     * @return bool Returns true if we have db connection otherwise returns false
     */
    private function checkDbConnection()
    {
        // If we do not have a database connection, then we log this as an error
        if (!$this->database->checkConnection()) {
            $this->errors["noDbConnection"] = new Error("There is no database connection.");
            return false;
        }

        return true;
    }
}

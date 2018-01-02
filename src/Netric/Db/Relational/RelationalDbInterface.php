<?php
namespace Netric\Db\Relational;

/**
 * In most cases we try to keep relational db usage as generic as possible so
 * that a dependent class could use any of the supported drivers to store
 * and retrieve state. In cases where this is not possible we require that
 * a DataMapper created for each specific driver and be tested appropriately
 * via integration tests to assure all drivers work as designed.
 */
interface RelationalDbInterface
{
    /**
     * Prepares and executes a statement returning a Results object
     *
     * Example:
     * $oRDbConnection->query(
     *      "SELECT id FROM users WHERE nane = :name",
     *      [ 'name' => 1 ]
     * )->fetchAll();
     *
     * @param string $sqlQuery
     * @param array $params
     * @return Result Result set
     */
    public function query($sqlQuery, array $params = []);

    /**
     * Insert a row into a table
     *
     * @param string $tableName
     * @param array $params Associative array where key = columnName
     * @throws DatabaseQueryException from $this->query if the query fails
     * @return int ID created for the primary key (if exists) otherwize 0
     */
    public function insert(string $tableName, array $params);

    /**
     * Update a table row by matching conditional params
     *
     * @param string $tableName
     * @param array $params
     * @param array $whereParams
     * @return int Number of rows updated
     */
    public function update(string $tableName, array $params, array $whereParams);

    /**
     * Delete a table row by matching conditional params
     *
     * @param string $tableName
     * @param array $whereParams
     * @return int Number of rows updated
     */
    public function delete(string $tableName, array $whereParams);

    /**
     * Starts a DB Transaction.
     *
     * @return bool
     */
    public function beginTransaction();

    /**
     * Commits the current DB transaction.
     *
     * @return bool
     */
    public function commitTransaction();

    /**
     * Rolls back the current DB transaction.
     *
     * @return bool
     */
    public function rollbackTransaction();

    /**
     * Get the last inserted id of a sequence
     *
     * @param string $sequenceName If null then primary key is used
     * @return int
     */
    public function getLastInsertId($sequenceName = null);

    /**
     * Set a namespace for all database transactions
     * 
     * This will not be implemented in the AbstractRelationalDb class because
     * the concept of namespaces is so unique to each database system.
     * 
     * For exmaple, in postgresql a namespace is called a schema. In mysql
     * databases are essentially schemas.
     *
     * @param string $namespace
     * @param bool $createIfMissing If true then create the namespace if it could not be set
     * @return void
     * @throws DatabaseQueryException on failure to create if missing
     */
    public function setNamespace(string $namespace, bool $createIfMissing = false);

    /**
     * Create a unique namespace for segregating user data
     *
     * @param string $namespace
     * @return bool true on success
     * @throws DatabaseQueryException on failure
     */
    public function createNamespace(string $namespace);

    /**
     * Delete a unique namespace
     *
     * @param string $namespace
     * @return bool true on success
     * @throws DatabaseQueryException on failure
     */
    public function deleteNamespace(string $namespace);
}

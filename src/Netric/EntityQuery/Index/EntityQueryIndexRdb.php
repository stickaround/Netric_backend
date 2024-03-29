<?php

namespace Netric\EntityQuery\Index;

use Netric\EntityDefinition\Field;
use Netric\EntityDefinition\EntityDefinition;
use Netric\EntityQuery\EntityQuery;
use Netric\EntityQuery\Where;
use Netric\EntityQuery\Results;
use Netric\EntityQuery\Aggregation;
use Netric\Account\Account;
use Netric\Entity\Entity;
use Netric\EntityQuery\Aggregation\AggregationInterface;
use Netric\Db\Relational\RelationalDbContainer;
use Netric\Db\Relational\RelationalDbInterface;
use Netric\Entity\EntityValueSanitizer;
use Netric\EntityGroupings\GroupingLoader;

/**
 * Relational Database implementation of indexer for querying objects
 */
class EntityQueryIndexRdb extends IndexAbstract implements IndexInterface
{
    /**
     * Set table const
     */
    const ENTITY_TABLE = 'entity';

    /**
     * Database container
     *
     * @var RelationalDbContainer
     */
    private $databaseContainer = null;

    /**
     * Handles the sanitizing of condition values in the query
     *
     * @var EntityValueSanitizer
     */
    private $entityValueSanitizer = null;

    /**
     * Get groupings
     * 
     * @var GroupingLoader
     */
    private GroupingLoader $groupingLoader;

    /**
     * Setup this index
     *
     * @param RelationalDbContainer $databaseContainer Used to get active database connection for the right account
     * @param EntityValueSanitizer $entityValueSanitizer Handles the sanitizing of condition values in the query
     * @param GroupingLoader $groupingLoader Get groupings for sort and index updates
     */
    protected function setUp(RelationalDbContainer $dbContainer, EntityValueSanitizer $entityValueSanitizer, GroupingLoader $groupingLoader)
    {
        $this->databaseContainer = $dbContainer;
        $this->entityValueSanitizer = $entityValueSanitizer;
        $this->groupingLoader = $groupingLoader;
    }

    /**
     * Get active database handle
     *
     * @param string $accountId The account being acted on
     * @return RelationalDbInterface
     */
    private function getDatabase(string $accountId): RelationalDbInterface
    {
        return $this->databaseContainer->getDbHandleForAccountId($accountId);
    }

    /**
     * Save an object to the index
     *
     * @param Entity $entity Entity to save
     * @return bool true on success, false on failure
     */
    public function save(Entity $entity)
    {
        // Get the account id from the entity
        $accountId = $entity->getAccountId();

        $def = $entity->getDefinition();
        $tableName = self::ENTITY_TABLE;

        // Get indexed text
        $fields = $def->getFields();
        $fieldTextValues = [];
        foreach ($fields as $field) {
            if ($field->type != Field::TYPE_GROUPING_MULTI && $field->type != Field::TYPE_OBJECT_MULTI) {
                $fieldTextValues[] = strtolower(strip_tags($entity->getValue($field->name)));
            }
        }

        $sql = "UPDATE $tableName
                SET tsv_fulltext=to_tsvector('english', :full_text_terms)
                WHERE entity_id=:entity_id";

        /*
         * We will be using rdb::query() here instead of rdb::update()
         * since we are using to_vector() pgsql function and not updating a field using a normal data
         */
        $queryParams = ["entity_id" => $entity->getEntityid(), "full_text_terms" => implode(" ", $fieldTextValues)];
        $result = $this->getDatabase($accountId)->query($sql, $queryParams);

        return $result->rowCount() > 0;
    }

    /**
     * Delete an object from the index
     *
     * @param string $objectId Unique id of object to delete
     * @return bool true on success, false on failure
     */
    public function delete($objectId)
    {
        // Nothing need be done because this index queries the source persistent store
        return true;
    }

    /**
     * Execute a query and return the results
     *
     * @param EntityQuery $query The query to execute
     * @param Results $results Optional results set to use. Otherwise create new.
     * @return Results
     */
    protected function queryIndex(EntityQuery $query, Results $results = null)
    {
        // If re-running an existing result (getting the next page) then clear
        if ($results !== null) {
            $results->clearEntities();
        }

        // Create results object if it does not exist
        if ($results === null) {
            $results = new Results($query, $this);
        }

        // Get the account id from the Entity Query
        $accountId = $query->getAccountId();

        // Make sure that we have an entity definition before executing a query
        $entityDefinition = $this->getDefinition($query->getObjType(), $accountId);

        // Should never happen, but just in case if we do not have an entity definition throw an exception
        if (!$entityDefinition) {
            throw new \RuntimeException(
                "No entity definition" . var_export($query->toArray(), true)
            );
        }

        // Start building the condition string
        $conditionString = "";
        $queryConditions = $this->entityValueSanitizer->sanitizeQuery($query);

        // Loop thru the query conditions and check for special fields
        foreach ($queryConditions as $condition) {
            $whereString = $this->buildConditionStringAndSetParams($entityDefinition, $condition);

            // Make sure that we have built an advanced condition string
            if (!empty($whereString)) {
                // Wrap all AND queries in () to make order of operations clear when OR is encoutered
                if ($condition->bLogic == Where::COMBINED_BY_AND) {
                    if ($conditionString) {
                        $conditionString .= ") AND (";
                    } elseif (empty($conditionString)) {
                        $conditionString .= " ( ";
                    }
                } elseif ($condition->bLogic == Where::COMBINED_BY_OR) {
                    $conditionString .= ($conditionString) ? " OR " : " (";
                }

                $conditionString .= $whereString;
            }
        }

        // Close any dangling opening (
        if ($conditionString) {
            $conditionString .= ")";
        }

        /*
         * If there is no f_deleted field condition set and entityDefinition has f_deleted field
         * We will make sure that we will get the non-deleted records
         */
        if (!$query->fieldIsInWheres('f_deleted')) {
            // If $conditionString is not empty, then we will just append the "and" blogic
            if (!empty($conditionString)) {
                $conditionString .= " AND ";
            }

            $castType = $this->castType(Field::TYPE_BOOL);
            $conditionString .= "(f_deleted is not true)";
        }

        // Add entity_definition_id constraint
        if (!empty($conditionString)) {
            $conditionString .= " AND ";
        }

        // Add entity type
        $conditionString .= self::ENTITY_TABLE .
            ".entity_definition_id='" .
            $entityDefinition->getEntityDefinitionId() . "'";

        // Add account
        $conditionString .= " AND "  .
            self::ENTITY_TABLE .
            ".account_id='$accountId'";

        // Get order by from $query and setup the sort order
        $sortOrder = [];
        $sortedGroupingFields = [];
        if (count($query->getOrderBy())) {
            $orderBy = $query->getOrderBy();

            foreach ($orderBy as $sort) {
                // Check if this is a grouping field
                $sortedField = $entityDefinition->getField($sort->fieldName);
                if ($sortedField->type == Field::TYPE_GROUPING) {
                    $sortedGroupingFields[] = $sort->fieldName;
                    $sortOrder[] = "grp_srt_{$sort->fieldName} $sort->direction";
                } else {
                    $sortOrder[] = "field_data->>'{$sort->fieldName}' $sort->direction";
                }
            }
        }

        // Of course, we select from the entity table
        $from = self::ENTITY_TABLE;

        $select = self::ENTITY_TABLE . ".*";
        foreach ($sortedGroupingFields as $groupingFieldName) {
            //$select .= ", grptbl_$groupingFieldName.sort_order as grp_srt_$groupingFieldName";
            $groupings = $this->groupingLoader->get(
                $query->getObjType() . '/' . $groupingFieldName,
                $query->getAccountId()
            );
            $groups = $groupings->getAll();
            $select .= ", CASE";
            foreach ($groups as $group) {
                $select .= "  WHEN (field_data->>'$groupingFieldName' = '{$group->groupId}') THEN '{$group->sortOrder}'";
            }
            $select .= " ELSE '0'";
            $select .= "END as grp_srt_$groupingFieldName";
        }

        // Start constructing query
        $sql = "SELECT $select FROM $from";

        // Set the query condition string if it is available
        if (!empty($conditionString)) {
            $sql .= " WHERE $conditionString";
        }

        // Check if we have order by string
        if (count($sortOrder)) {
            $sql .= " ORDER BY " . implode(", ", $sortOrder);
        }

        // Check if we need to add limit
        if (!empty($query->getLimit())) {
            $sql .= " LIMIT {$query->getLimit()}";
        }

        // Check if we need to add offset
        if (!empty($query->getOffset())) {
            $sql .= " OFFSET {$query->getOffset()}";
        }

        $result = $this->getDatabase($accountId)->query($sql);

        // Process the raw data of entities and update the $results
        $this->processEntitiesRawData($entityDefinition, $result->fetchAll(), $results);

        // Set the total num of the Results
        $this->setResultsTotalNum($entityDefinition, $results, $conditionString);

        // Get the aggregations and update the Results' aggregations
        if ($query->hasAggregations()) {
            $aggregations = $query->getAggregations();
            foreach ($aggregations as $agg) {
                $this->queryAggregation($entityDefinition, $agg, $results, $conditionString);
            }
        }

        return $results;
    }

    /**
     * Function that will set the total num for results
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param Results $results The results that we will be updating its total num
     * @param string $conditionString The query condition that will be used for filtering
     */
    private function setResultsTotalNum(EntityDefinition $entityDefinition, Results $results, $conditionString)
    {
        // Get the account id from the entity definition
        $accountId = $entityDefinition->getAccountId();

        // Create the sql string to get the total num
        $sql = "SELECT count(*) as total_num FROM " . self::ENTITY_TABLE;

        // Set the query condition string here if it is available
        if (!empty($conditionString)) {
            $sql .= " WHERE $conditionString";
        }

        $result = $this->getDatabase($accountId)->query($sql);
        if ($result->rowCount()) {
            $row = $result->fetch();
            $results->setTotalNum($row["total_num"]);
        }
    }

    /**
     * Process the raw data of entities and add them in the $results
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param Array $entitiesRawDataArray An array of entities raw data that will be processed
     * @param Results $results Results that will be used where we will add the processed entities
     */
    private function processEntitiesRawData(EntityDefinition $entityDefinition, array $entitiesRawDataArray, Results $results)
    {
        // Get fields for this object type (used in decoding multi-valued fields)
        $ofields = $entityDefinition->getFields();

        foreach ($entitiesRawDataArray as $rawData) {
            $entityData = json_decode($rawData['field_data'], true);

            // Use data from actual fields for account and entity
            $entityData['entity_id'] = $rawData['entity_id'];
            $entityData['uname'] = $rawData['uname'];
            $entityData['account_id'] = $rawData['account_id'];
            $entityData['entity_definition_id'] = $rawData['entity_definition_id'];

            // Decode multival fields into arrays of values
            foreach ($ofields as $fname => $fdef) {
                if ($fdef->type == Field::TYPE_GROUPING_MULTI || $fdef->type == Field::TYPE_OBJECT_MULTI) {
                    if (isset($entityData[$fname])) {
                        $dec = $entityData[$fname];
                        if ($dec !== false) {
                            $entityData[$fname] = $dec;
                        }
                    }
                }

                if (
                    $fdef->type == Field::TYPE_GROUPING || $fdef->type == Field::TYPE_OBJECT
                    || $fdef->type == Field::TYPE_GROUPING_MULTI || $fdef->type == Field::TYPE_OBJECT_MULTI
                ) {
                    if (isset($entityData[$fname . "_fval"])) {
                        $dec = $entityData[$fname . "_fval"];
                        if ($dec !== false) {
                            $entityData[$fname . "_fval"] = $dec;
                        }
                    }
                }
            }

            // Set and add entity
            $entity = $this->entityFactory->create($entityDefinition->getObjType(), $entityDefinition->getAccountId());
            $entity->fromArray($entityData);
            $entity->resetIsDirty();
            $results->addEntity($entity);
        }
    }

    /**
     * Build the conditions string using the $condition argument provided
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param Array $condition The where condition that we are dealing with
     * @return string Query condition string with param values pre-populated and quoted
     */
    public function buildConditionStringAndSetParams(EntityDefinition $entityDefinition, $condition): string
    {
        // Get the account id from the entity definition
        $accountId = $entityDefinition->getAccountId();

        $fieldName = $condition->fieldName;
        $operator = $condition->operator;

        // If we have a full text condition, then return a vector search
        if ($fieldName === "*") {
            return "(tsv_fulltext @@ plainto_tsquery(" . $this->getDatabase($accountId)->quote($condition->value) . "))";
        }

        // Should never happen, but just in case if operator is missing throw an exception
        if (!$operator) {
            throw new \RuntimeException("No operator provided for " . var_export($condition, true));
        }

        // Get the Field Definition using the field name provided in the $condition
        $field = $this->getFieldUsingFieldName($entityDefinition, $fieldName);

        // After sanitizing the condition value, then we are now ready to build the condition string
        $value = pg_escape_string($condition->value);

        // If the field is not indexed, log it so that we can optimize slow queries
        if ($field->isIndexed != true) {
            $this->logUnindexedQueryOnField($entityDefinition, $fieldName);
        }

        $castType = $this->castType($field->type);
        $conditionString = "";
        switch ($operator) {
            case Where::OPERATOR_EQUAL_TO:
                $conditionString = $this->buildIsEqual($entityDefinition, $field, $condition);
                break;
            case Where::OPERATOR_NOT_EQUAL_TO:
                $conditionString = $this->buildIsNotEqual($entityDefinition, $field, $condition);
                break;
            case Where::OPERATOR_GREATER_THAN:
                switch ($field->type) {
                    case Field::TYPE_OBJECT_MULTI:
                    case Field::TYPE_OBJECT:
                    case Field::TYPE_GROUPING_MULTI:
                    case Field::TYPE_TEXT:
                        break;
                    default:
                        if ($field->type == Field::TYPE_TIMESTAMP) {
                            $value = (is_numeric($value)) ? date("Y-m-d H:i:s T", $value) : $value;
                        } elseif ($field->type == Field::TYPE_DATE) {
                            $value = (is_numeric($value)) ? date("Y-m-d", $value) : $value;
                        }

                        $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType > '$value'";
                        break;
                }
                break;
            case Where::OPERATOR_LESS_THAN:
                switch ($field->type) {
                    case Field::TYPE_OBJECT_MULTI:
                    case Field::TYPE_OBJECT:
                    case Field::TYPE_GROUPING_MULTI:
                    case Field::TYPE_TEXT:
                        break;
                    default:
                        if ($field->type == Field::TYPE_TIMESTAMP) {
                            $value = (is_numeric($value)) ? date("Y-m-d H:i:s T", $value) : $value;
                        } elseif ($field->type == Field::TYPE_DATE) {
                            $value = (is_numeric($value)) ? date("Y-m-d", $value) : $value;
                        }

                        $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType < '$value'";
                        break;
                }
                break;
            case Where::OPERATOR_GREATER_THAN_OR_EQUAL_TO:
                switch ($field->type) {
                    case Field::TYPE_OBJECT_MULTI:
                    case Field::TYPE_GROUPING_MULTI:
                    case Field::TYPE_TEXT:
                        break;
                    case Field::TYPE_OBJECT:
                        if ($field->subtype) {
                            $children = $this->getHeiarchyDownObj($field->subtype, $value, $accountId);

                            foreach ($children as $child) {
                                $multiCond[] = "field_data->>'$fieldName' = '$child'";
                            }

                            $conditionString = "(" . implode(" or ", $multiCond) . ")";
                            break;
                        }
                        break;
                    default:
                        if ($field->type == Field::TYPE_TIMESTAMP) {
                            $value = (is_numeric($value)) ? date("Y-m-d H:i:s T", $value) : $value;
                        } elseif ($field->type == Field::TYPE_DATE) {
                            $value = (is_numeric($value)) ? date("Y-m-d", $value) : $value;
                        }

                        $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType >= '$value'";
                        break;
                }
                break;
            case Where::OPERATOR_LESS_THAN_OR_EQUAL_TO:
                switch ($field->type) {
                    case Field::TYPE_OBJECT_MULTI:
                    case Field::TYPE_GROUPING_MULTI:
                    case Field::TYPE_TEXT:
                        break;
                    case Field::TYPE_OBJECT:
                        if (
                            !empty($field->subtype)
                            && $entityDefinition->parentField == $fieldName
                            && is_numeric($value)
                        ) {
                            $refDef = $this->getDefinition($field->subtype);
                            $refDefTable = $refDef->getTable(true);

                            if ($refDef->parentField) {
                                $conditionString = "field_data->>'$fieldName' in (WITH RECURSIVE children AS
												(
													-- non-recursive term
                                                    SELECT field_data->>'id' AS id FROM $refDefTable 
                                                    WHERE field_data->>'id' = '$value'
													UNION ALL
													-- recursive term
													SELECT $refDefTable.field_data->>'id' as id
													FROM $refDefTable
													JOIN children AS chld
														ON ($refDefTable.field_data->>'{$refDef->parentField}' = chld.id)
												)
												SELECT id
												FROM children)";
                            }
                        }
                        break;
                    default:
                        if ($field->type == Field::TYPE_TIMESTAMP) {
                            $value = (is_numeric($value)) ? date("Y-m-d H:i:s T", $value) : $value;
                        } elseif ($field->type == Field::TYPE_DATE) {
                            $value = (is_numeric($value)) ? date("Y-m-d", $value) : $value;
                        }

                        $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType <= '$value'";
                        break;
                }
                break;
            case Where::OPERATOR_BEGINS:
            case Where::OPERATOR_BEGINS_WITH:
                if ($field->type == Field::TYPE_TEXT) {
                    $conditionString = "lower(field_data->>'$fieldName') LIKE '" . strtolower("$value%") . "'";
                }
                break;
            case Where::OPERATOR_CONTAINS:
                if ($field->type == Field::TYPE_TEXT) {
                    $conditionString = "lower(field_data->>'$fieldName') LIKE '" . strtolower("%$value%") . "'";
                }
                break;
        }

        // If we are dealing with date operators
        if (empty($conditionString)) {
            switch ($field->type) {
                case Field::TYPE_TIMESTAMP:
                case Field::TYPE_DATE:
                    $conditionString = $this->buildConditionWithDateOperators($condition, $castType);
                    break;
                default:
                    break;
            }
        }

        return $conditionString;
    }

    /**
     * Function that will build the conditions with date operators
     *
     * @param Array $condition The where condition that we are dealing with
     * @param String $castType String that will be used if we will be type casting the field
     * @return string
     */
    private function buildConditionWithDateOperators($condition, $castType)
    {
        $conditionString = "";
        $fieldName = $condition->fieldName;
        $value = $condition->value;
        $dateType = $condition->getOperatorDateType();

        switch ($condition->operator) {
                // Operator Date is equal
            case Where::OPERATOR_DAY_IS_EQUAL:
            case Where::OPERATOR_MONTH_IS_EQUAL:
            case Where::OPERATOR_YEAR_IS_EQUAL:
                // If the value is trying to get the current date
                if ($value === "<%current_$dateType%>") {
                    $conditionString = "extract($dateType from (nullif(field_data->>'$fieldName', ''))$castType) = extract('$dateType' from now())";
                } else {
                    $conditionString = "extract($dateType from (nullif(field_data->>'$fieldName', ''))$castType) = '$value'";
                }
                break;

                // Operator Last X DateType
            case Where::OPERATOR_LAST_X_DAYS:
            case Where::OPERATOR_LAST_X_WEEKS:
            case Where::OPERATOR_LAST_X_MONTHS:
            case Where::OPERATOR_LAST_X_YEARS:
                $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType <= (now()-INTERVAL '$value {$dateType}s')$castType";
                break;

                // Operator Next DateType
            case Where::OPERATOR_NEXT_X_DAYS:
            case Where::OPERATOR_NEXT_X_WEEKS:
            case Where::OPERATOR_NEXT_X_MONTHS:
            case Where::OPERATOR_NEXT_X_YEARS:
                $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType >= now()$castType and (nullif(field_data->>'$fieldName', ''))$castType <= (now()+INTERVAL '$value {$dateType}s')$castType";
                break;
        }

        return $conditionString;
    }

    /**
     * Add conditions for "is_eqaul" operator
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param string $field The current field that we will handle to build the is_equal where condition
     * @param Array $condition The where condition that we are dealing with
     */
    private function buildIsEqual(EntityDefinition $entityDefinition, $field, $condition)
    {
        $objectTable = self::ENTITY_TABLE;
        $fieldName = $condition->fieldName;
        $value = pg_escape_string($condition->value);

        // Handle special indexed columns
        switch ($fieldName) {
            case 'uname':
            case 'entity_id':
                return "$fieldName='$value'";
        }

        $castType = $this->castType($field->type);
        $conditionString = "";
        switch ($field->type) {
            case Field::TYPE_OBJECT:
                if ($value) {
                    // New jsonb-based query condition
                    $conditionString = "field_data->>'$fieldName' = '$value'";
                } else {
                    // Value is null/empty or key does not exist
                    $conditionString = "(field_data->>'$fieldName') IS NULL OR field_data->>'$fieldName' = ''";
                }
                break;
            case Field::TYPE_OBJECT_MULTI:
                $conditionString = $this->buildObjectMultiQueryCondition($entityDefinition, $field, $condition);
                break;
            case Field::TYPE_GROUPING_MULTI:
                // Make sure that the grouping value is provided
                if ($value) {
                    $conditionString = "field_data->'{$fieldName}' @> jsonb_build_array('$value')";
                } else {
                    $conditionString = "(field_data->'$fieldName' = 'null'::jsonb OR field_data->'$fieldName' = '[]'::jsonb)";
                }
                break;
            case Field::TYPE_GROUPING:
                $conditionString = $this->buildGroupingQueryCondition($entityDefinition, $field, $condition);
                break;
            case Field::TYPE_TEXT:
                if (empty($value)) {
                    $conditionString = "(field_data->>'$fieldName' IS NULL OR field_data->>'$fieldName' = '')";
                } else {
                    $conditionString = "lower(field_data->>'$fieldName') = '" . strtolower($value) . "'";
                }
                break;
            case Field::TYPE_BOOL:
                $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType = $value";
                break;
            case Field::TYPE_DATE:
            case Field::TYPE_TIMESTAMP:
                if ($field->type == Field::TYPE_TIMESTAMP) {
                    $value = (is_numeric($value)) ? date("Y-m-d H:i:s T", $value) : $value;
                } elseif ($field->type == Field::TYPE_DATE) {
                    $value = (is_numeric($value)) ? date("Y-m-d", $value) : $value;
                }
                if (!empty($value)) {
                    $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType = '$value'";
                } else {
                    $conditionString = "field_data->>'$fieldName' IS NULL OR field_data->>'$fieldName' = ''";
                }
                break;
            default:
                // Make sure value is set and not null or empty
                if (isset($value)) {
                    $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType = '$value'";
                } else {
                    $conditionString = "field_data->>'$fieldName' IS NULL OR field_data->>'$fieldName' = ''";
                }
                break;
        }

        return $conditionString;
    }

    /**
     * Add conditions for "is_not_eqaul" operator
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param Field $field The current field that we will handle to build the is_not_equal where condition
     * @param Array $condition The where condition that we are dealing with
     */
    private function buildIsNotEqual(EntityDefinition $entityDefinition, $field, $condition)
    {
        $objectTable = self::ENTITY_TABLE;
        $fieldName = $condition->fieldName;
        $value = pg_escape_string($condition->value);

        $castType = $this->castType($field->type);
        $conditionString = "";
        switch ($field->type) {
            case Field::TYPE_OBJECT:
                if ($field->subtype) {
                    if (empty($value)) {
                        $conditionString = "field_data->>'$fieldName' IS NOT NULL";
                    } elseif (isset($field->subtype) && $entityDefinition->parentField == $fieldName && $value) {
                        $refDef = $this->getDefinition($field->subtype);
                        $refDefTable = $refDef->getTable(true);
                        $parentField = $refDef->parentField;

                        if ($refDef->parentField) {
                            $conditionString = "$fieldName NOT IN (WITH RECURSIVE children AS
                                    (
                                        -- non-recursive term
                                        SELECT field_data->>'entity_id' FROM $refDefTable WHERE field_data->>'entity_id' = '$value'
                                        UNION ALL
                                        -- recursive term
                                        SELECT $refDefTable.field_data->>'entity_id'
                                        FROM $refDefTable
                                        JOIN children AS chld
                                            ON ($refDefTable.field_data->>'$parentField' = chld.entity_id)
                                    )
                                    SELECT entity_id
                                    FROM children)";
                        }
                    } else {
                        $conditionString = "field_data->>'$fieldName' != '$value'";
                    }
                }
                break;

            case Field::TYPE_OBJECT_MULTI:
                $conditionString = $this->buildObjectMultiQueryCondition($entityDefinition, $field, $condition);
                break;
            case 'object_dereference':
                /*$tmp_cond_str = "";
                if ($field->subtype && $refField) {
                    // Create subquery
                    $subQuery = new \Netric\EntityQuery($field->subtype);
                    $subQuery->where($refField, $operator, $value);
                    $subIndex = new \Netric\EntityQuery\Index\Pgsql($this->account);
                    $tmp_obj_cnd_str = $subIndex->buildConditionStringAndSetParams($subQuery);
                    $refDef = $this->getDefinition($field->subtype);

                    if ($value == "" || $value == "NULL") {
                        $buf .= " " . $objectTable . ".$fieldName is not null ";
                    } else {
                        $buf .= " " . $objectTable . ".$fieldName not in (select id from " . $refDef->getTable(true) . "
                                                                                where $tmp_obj_cnd_str) ";
                    }
                }*/
                break;
            case Field::TYPE_GROUPING_MULTI:
                // Make sure that the grouping value is provided
                if ($value) {
                    $conditionString = "entity_id NOT IN (SELECT entity_id FROM $objectTable WHERE field_data->'{$fieldName}' @> jsonb_build_array('$value'))";
                } else {
                    $conditionString = "(field_data->'$fieldName' != 'null'::jsonb OR field_data->'$fieldName' != '[]'::jsonb)";
                }
                break;
            case Field::TYPE_GROUPING:
                $conditionString = $this->buildGroupingQueryCondition($entityDefinition, $field, $condition);
                break;
            case Field::TYPE_TEXT:
                if (empty($value)) {
                    $conditionString = "(field_data->>'$fieldName' != '' AND field_data->>'$fieldName' IS NOT NULL)";
                } else {
                    $conditionString = "lower(field_data->>'$fieldName') != '" . strtolower($value) . "'";
                }
                break;
            case Field::TYPE_BOOL:
                $conditionString = "(nullif(field_data->>'$fieldName', ''))$castType != $value";
                break;
            case Field::TYPE_DATE:
            case Field::TYPE_TIMESTAMP:
                if ($field->type == Field::TYPE_TIMESTAMP) {
                    $value = (is_numeric($value)) ? date("Y-m-d H:i:s T", $value) : $value;
                } elseif ($field->type == Field::TYPE_DATE) {
                    $value = (is_numeric($value)) ? date("Y-m-d", $value) : $value;
                }
                // Format the string then fall through to default
            default:
                if (!empty($value)) {
                    $conditionString = "((nullif(field_data->>'$fieldName', ''))$castType != '$value' OR field_data->>'$fieldName' IS NULL)";
                } else {
                    $conditionString = "field_data->>'$fieldName' IS NOT NULL";
                }
                break;
        }

        return $conditionString;
    }

    /**
     * Function that will be the query string for Grouping Query Conditions
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param Field $field The current field that we will handle to build the is_not_equal where condition
     * @param Array $condition The where condition that we are dealing with
     * @return string
     */
    private function buildGroupingQueryCondition(EntityDefinition $entityDefinition, $field, $condition)
    {
        $objectTable = self::ENTITY_TABLE;
        $fieldName = $condition->fieldName;
        $value = $condition->value;
        $operator = $condition->operator;
        $conditionString = "";

        if (empty($value)) {
            if ($operator == Where::OPERATOR_EQUAL_TO) {
                $conditionString = "field_data->>'$fieldName' IS NULL";
            } else {
                $conditionString = "field_data->>'$fieldName' IS NOT NULL";
            }
        } else {
            $operatorSign = "=";
            if ($operator == Where::OPERATOR_NOT_EQUAL_TO) {
                $operatorSign = "!=";
            }

            $conditionString = "field_data->>'$fieldName' $operatorSign '$value'";

            // If our operator is not equal to , then we need to add if fieldname is null with or operator
            if ($operator == Where::OPERATOR_NOT_EQUAL_TO) {
                $conditionString = "($conditionString OR field_data->>'$fieldName' IS NULL)";
            }
        }

        return $conditionString;
    }

    /**
     * Function that will be the query string for ObjectMulti Query Conditions
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param Field $field The current field that we will handle to build the is_not_equal where condition
     * @param Array $condition The where condition that we are dealing with
     * @return string
     */
    private function buildObjectMultiQueryCondition(EntityDefinition $entityDefinition, $field, $condition)
    {
        $objectTable = self::ENTITY_TABLE;
        $value = $condition->value;
        $operator = $condition->operator;

        if ($operator == Where::OPERATOR_EQUAL_TO) {
            $conditionString = "field_data->'{$field->name}' @> jsonb_build_array('$value')";
        } else {
            $conditionString = "entity_id NOT IN (SELECT entity_id FROM $objectTable WHERE field_data->'{$field->name}' @> jsonb_build_array('$value'))";
        }

        return $conditionString;
    }

    /**
     * Set aggregation data
     *
     * @param EntityDefinition $entityDefinition Definition for the entity being queried
     * @param AggregationInterface $agg
     * @param Results $results Results that will be used where we will set the aggregate data
     * @param string $objectTable The actual table we are querying
     * @param string $conditionQuery The query condition that will be used for filtering
     */
    private function queryAggregation(EntityDefinition $entityDefinition, AggregationInterface $agg, Results $results, $conditionQuery)
    {
        // Get the account id from entity definition
        $accountId = $entityDefinition->getAccountId();

        $fieldName = $agg->getField();
        $aggTypeName = $agg->getTypeName();

        // Make sure that we have a valid field name
        if (!$fieldName) {
            return false;
        }

        $orderBy = "";
        $jsonbField = "NULLIF(field_data->>'$fieldName', '')::numeric";
        $queryFields = "min($jsonbField) as agg_min,
                        max($jsonbField) as agg_max,
                        avg($jsonbField) as agg_avg,
                        sum($jsonbField) as agg_sum";

        // If we are dealing with aggregate terms, then we need to group the results by $fieldName
        if ($aggTypeName === "terms") {
            $queryFields = "distinct($fieldName) as agg_distinct, count($jsonbField) as cnt";
            $orderBy = "GROUP BY $fieldName";
        }

        $sql = 'SELECT ' . $queryFields . ' FROM ' . self::ENTITY_TABLE;

        // Add "and" operator in the $conditionQuery if it is not empty
        if ($conditionQuery) {
            $sql .= " WHERE $conditionQuery";
        }

        $sql .= " $orderBy";

        $result = $this->getDatabase($accountId)->query($sql);

        // Make sure that we have results before we process the aggregates
        if ($result->rowCount()) {
            $data = null;

            // Determine which type of aggregate we will use to process the results
            switch ($aggTypeName) {
                case 'min':
                case 'sum':
                case 'avg':
                    $row = $result->fetch();
                    $data = $row["agg_$aggTypeName"];
                    break;
                case 'terms':
                    $data = [];
                    foreach ($result->fetchAll() as $row) {
                        $data[] = ["count" => $row["cnt"], "term" => $row[$fieldName]];
                    }
                    break;
                case 'stats':
                    $row = $result->fetch();
                    $data = [
                        "min" => $row["agg_min"],
                        "max" => $row["agg_max"],
                        "avg" => $row["agg_avg"],
                        "sum" => $row["agg_sum"],
                        "count" => $results->getTotalNum()
                    ];
                    break;
                case 'count':
                    $data = $results->getTotalNum();
                    break;
            }

            $results->setAggregation($agg->getName(), $data);
        }
    }

    /**
     * Function that will adds nullif in the fieldName that will be used in jsonb queries
     *
     * @param string $fieldName The name of the field that we will be setting as nullif
     */
    private function castNullIfInteger($fieldName)
    {
        return "nullif($fieldName, '')::int";
    }

    /**
     * Function that will return the cast type based on the field type
     */
    private function castType($fieldType)
    {
        switch ($fieldType) {
            case Field::TYPE_TIMESTAMP:
                return "::timestamp with time zone";
                break;
            case Field::TYPE_DATE:
                return "::date";
                break;
            case Field::TYPE_BOOL:
                return "::boolean";
                break;
            case Field::TYPE_INTEGER:
            case Field::TYPE_NUMBER:
                return "::integer";
                break;
            default:
                return "";
                break;
        }
    }
}

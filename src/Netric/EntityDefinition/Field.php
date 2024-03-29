<?php

namespace Netric\EntityDefinition;

use Netric\Entity\ObjType\UserEntity;

/**
 * Field definition
 */
class Field implements \ArrayAccess
{
    /**
     * Unique id if the field was loaded from a database
     *
     * @var string
     */
    public $id = "";

    /**
     * Field name (REQUIRED)
     *
     * No spaces or special characters allowed. Only alphanum up to 32 characters in lenght.
     *
     * @var string
     */
    public $name = "";

    /**
     * Human readable title
     *
     * If not set then $this->name will be used:
     *
     * @var string
     */
    public $title = "";

    /**
     * The type of field (REQUIRED)
     *
     * @var string
     */
    public $type = "";

    /**
     * The subtype
     *
     * @var string
     */
    public $subtype = "";

    /**
     * Optional mask for formatting value
     *
     * @var string
     */
    public $mask = "";

    /**
     * Is this a required field?
     *
     * @var bool
     */
    public $required = false;

    /**
     * Is this a system defined field
     *
     * Only user fields can be deleted or edited
     *
     * @var bool
     */
    public $system = false;

    /**
     * If read only the user cannot set this value
     *
     * @var bool
     */
    public $readonly = false;

    /**
     * This field value must be unique across all objects
     *
     * @var bool
     */
    public $unique = false;

    /**
     * Optional use_when condition will only display field when condition is met
     *
     * This is used for things like custom fields for posts where each feed will have special
     * custom fields on a global object - posts.
     *
     * @var string
     */
    private $useWhen = "";

    /**
     * Default value to use with this field
     *
     * @var array('on', 'value')
     */
    public $default = null;

    /**
     * Optional values
     *
     * If an associative array then the id is the key, otherwise the value is used
     *
     * @var array
     */
    public $optionalValues = null;

    /**
     * Flag that will indicate that this field needs to be in an indexed column
     *
     * @var boolean
     */
    public $isIndexed = false;

    /**
     * Field type constants
     */
    const TYPE_GROUPING = 'fkey';
    const TYPE_GROUPING_MULTI = 'fkey_multi';
    const TYPE_OBJECT = 'object';
    const TYPE_OBJECT_MULTI = 'object_multi';
    const TYPE_TEXT = 'text';
    const TYPE_BOOL = 'bool';
    const TYPE_DATE = 'date';
    const TYPE_TIME = 'time';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_NUMBER = 'number';
    const TYPE_INTEGER = 'integer';
    const TYPE_ALIAS = 'alias';
    const TYPE_UUID = 'uuid';

    /**
     * Load field definition from array
     *
     * @param array $data
     */
    public function fromArray($data)
    {
        if (isset($data["id"])) {
            $this->id = $data["id"];
        }

        if (isset($data["name"])) {
            $this->name = $data["name"];
        }

        if (isset($data["title"])) {
            $this->title = $data["title"];
        }

        if (isset($data["type"])) {
            $this->type = $data["type"];
        }

        if (isset($data["subtype"])) {
            $this->subtype = $data["subtype"];
        }

        if (isset($data["mask"])) {
            $this->mask = $data["mask"];
        }

        if (isset($data["required"])) {
            $this->required = ($data["required"] === true || (string) $data["required"] == "true" || (string) $data["required"] == "t") ? true : false;
        }

        if (isset($data["system"])) {
            $this->system = ($data["system"] === true || (string) $data["system"] == "true" || (string) $data["system"] == "t") ? true : false;
        }

        if (isset($data["readonly"])) {
            $this->readonly = ($data["readonly"] === true || (string) $data["readonly"] == "true" || (string) $data["readonly"] == "t") ? true : false;
        }

        if (isset($data["unique"])) {
            $this->unique = ($data["unique"] === true || (string) $data["unique"] == "true" || (string) $data["unique"] == "t") ? true : false;
        }

        if (isset($data["use_when"])) {
            $this->setUseWhen($data["use_when"]);
        }

        if (isset($data["default"])) {
            $this->default = $data["default"];
        }

        if (isset($data["optional_values"])) {
            $this->optionalValues = $data["optional_values"];
        }

        if (isset($data["is_indexed"])) {
            $this->isIndexed = $data["is_indexed"];
        }
    }

    /**
     * Get the name of this field
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name for this field
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Conver field definition to array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "title" => $this->title,
            "type" => $this->type,
            "subtype" => $this->subtype,
            "default" => $this->default,
            "mask" => $this->mask,
            "required" => $this->required,
            "system" => $this->system,
            "readonly" => $this->readonly,
            "unique" => $this->unique,
            "use_when" => $this->useWhen,
            "default" => $this->default,
            "optional_values" => $this->optionalValues,
            "is_indexed" => $this->isIndexed
        ];
    }

    /**
     * Set useWhen condition
     *
     * @param string $value
     */
    public function setUseWhen($value)
    {
        // Modify name of the field if this is a new field
        if ($value && !$this->id) {
            $postpend = "";
            $parts = explode(":", $value);
            if (count($parts) > 1) {
                $postpend = "_" . $parts[0] . "_";

                $parts[1] = str_replace("-", "minus", $parts[1]);
                $parts[1] = str_replace("+", "plus", $parts[1]);

                $postpend .= $parts[1];
            }

            if ($postpend) {
                $this->name = $this->name . $postpend;
            }
        }

        $this->useWhen = $value;
    }

    /**
     * Get useWhen condition
     *
     * @return string
     */
    public function getUseWhen()
    {
        return $this->useWhen;
    }

    /**
     * Get a default value based on an event like 'update'
     *
     * TODO: in-progress
     *
     * @param mixed $value The current value
     * @param string $event The event to use the default on
     * @param Entity $obj If set, update the object directly
     * @param AntUser $user If set, use this for user variables
     */
    public function getDefault($value, $event = 'update', $obj = null, $user = null)
    {
        $ret = $value;

        if ($this->default && is_array($this->default) && count($this->default)) {
            $on = ($this->default['on']) ? ($this->default['on']) : '';

            // Check if condition is part of the default
            if (isset($this->default['where']) && $this->default['where'] && $obj) {
                if (is_array($this->default['where'])) {
                    foreach ($this->default['where'] as $condFName => $condVal) {
                        if ($obj->getValue($condFName) != $condVal) {
                            $on = ""; // Do not set default
                        }
                    }
                }
            }

            // Determine appropriate event and action
            switch ($on) {
                case 'create':
                    if ($event == "create" && !$value) {
                        $ret = $this->default['value'];
                    }
                    break;
                    // Fall through to also use update
                case 'update':
                    if ($event == "update") {
                        if (isset($this->default['coalesce']) && is_array($this->default['coalesce']) && $obj) {
                            $ret = $this->getDefaultCoalesce($this->default['coalesce'], $obj, ($this->type == "alias") ? true : false);
                            if (!$ret) {
                                $ret = $this->default['value'];
                            }
                        } else {
                            $ret = $this->default['value'];
                        }
                    }
                    break;
                case 'delete':
                    if ($event == "delete") {
                        $ret = $this->default['value'];
                    }
                    break;
                case 'null':
                    if ($ret === "" || $ret === null || $ret === $this->default['value']) {
                        if (isset($this->default['coalesce']) && $this->default['coalesce'] && is_array($this->default['coalesce']) && $obj) {
                            $ret = $this->getDefaultCoalesce($this->default['coalesce'], $obj, ($this->type == "alias") ? true : false);
                            if (!$ret) {
                                $ret = $this->default['value'];
                            }
                        } else {
                            $ret = $this->default['value'];
                        }
                    }
                    break;
            }
        }

        $hour = 0;
        $minute = 0;
        $second = 0;

        // Convert values
        switch ($this->type) {
            case self::TYPE_NUMBER:
            case self::TYPE_DATE:
            case self::TYPE_TIME:
            case self::TYPE_TIMESTAMP:
                if ("now" == $ret) {
                    $ret = time();
                }
                break;
        }

        // Look for variables
        if (is_string($ret)) {
            if ("<%username%>" == (string) $ret) {
                if ($user) {
                    $ret = $user->getValue('name');
                } else {
                    $ret = "";
                }
            }

            if ("<%userid%>" == (string) $ret) {
                if ($user) {
                    $ret = $user->getEntityId();
                } else {
                    $ret = "";
                }
            }
        }

        if ((
                ($this->type == self::TYPE_OBJECT && $this->subtype == "user") ||
                ($this->type == self::TYPE_OBJECT_MULTI && $this->subtype == "user")) &&
            $ret == UserEntity::USER_CURRENT
        ) {
            if ($user) {
                $ret = $user->getEntityId();
            } else {
                $ret = ""; // TODO: possibly use system or anonymous
            }
        }


        return $ret;
    }

    /**
     * If the default value involves combining more than one field
     *
     * @param
     */
    public function getDefaultCoalesce($cfields, $obj, $alias = false)
    {
        $ret = "";

        foreach ($cfields as $field_to_pull) {
            if (is_array($field_to_pull)) {
                foreach ($field_to_pull as $subcol) {
                    $buf = $obj->getValue($subcol);
                    if ($buf) {
                        if ($ret) {
                            $ret .= " ";
                        }

                        if ($alias) {
                            $ret = $subcol;
                            break;
                        } else {
                            $ret .= $buf;
                        }
                    }
                }
            } else {
                if ($alias) {
                    $ret = $field_to_pull;
                    break;
                } else {
                    $ret = $obj->getValue($field_to_pull);
                }
            }

            // Check if name was found
            if ($ret) {
                break;
            }
        }

        return $ret;
    }

    /**
     * Determine if the type of field is referencing one or many objects
     *
     * @return bool
     */
    public function isObjectReference()
    {
        return ($this->type == self::TYPE_OBJECT || $this->type == self::TYPE_OBJECT_MULTI);
    }

    /**
     * Determine if the type of field is referencing one or many groupings
     *
     * @return bool
     */
    public function isGroupingReference()
    {
        return ($this->type == self::TYPE_GROUPING || $this->type == self::TYPE_GROUPING_MULTI);
    }

    /**
     * Check if this field type supports multiple values
     *
     * @return bool
     */
    public function isMultiValue()
    {
        return ($this->type == self::TYPE_GROUPING_MULTI || $this->type == self::TYPE_OBJECT_MULTI);
    }


    /**
     * ArrayAccess Implementation Functions
     * -------------------------------------------------------------------
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}

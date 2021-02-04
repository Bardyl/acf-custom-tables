<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;
use AcfExtended\Core\Utils\AcfFields;
use AcfExtended\Core\Utils\Database;
use AcfExtended\Core\Utils\FieldsTypes;

class Field {
    public Database $db;

    public string $sqlType;

    public function __construct() {
        $type = strtolower((new \ReflectionClass($this))->getShortName());
        $this->db = new Database();

        add_filter('acf_extended__' . $type . '__sql_type', array($this, 'getSqlType'));
        add_filter('acf_extended__' . $type . '__format_for_save', array($this, 'formatForSave'), 10, 2);
        add_filter('acf_extended__' . $type . '__format_for_load', array($this, 'formatForLoad'), 10, 3);
    }

    /**
     * @param $parameters
     * @return string
     */
    public function getSqlType($parameters): string {
        if ($this->sqlType === 'varchar') {
            return FieldsTypes::getVarcharField($parameters['maxlength']);
        }
        return $this->sqlType;
    }

    /**
     * @param $value
     * @param $hierarchy
     * @return string|array
     */
    public function formatForSave($value, $hierarchy) {
        return $value;
    }

    /**
     * @param $field
     * @param $postID
     * @return string|array
     */
    public function formatForLoad($field, $postID) {
        $table = ACF::getACFGroupName($field['id']);
        $column = $field['name'];

        return $this->db->getSingleRowValue($table, $column, $postID);
    }

    /**
     * @return bool
     */
    public function isSpecialField(): bool {
        if (isset($this->specialField)) {
            return $this->specialField;
        }
        return false;
    }
}

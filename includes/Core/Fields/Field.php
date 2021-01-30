<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;
use AcfExtended\Core\Utils\Database;

class Field {
    public Database $db;
    public String $sqlType;

    public function __construct() {
        $type = strtolower((new \ReflectionClass($this))->getShortName());
        $this->db = new Database();

        add_filter('acf_extended__' . $type . '__sql_type', array($this, 'getSqlType'));
        add_filter('acf_extended__' . $type . '__format_for_save', array($this, 'formatForSave'), 10, 2);
    }

    public function getSqlType(): String {
        return $this->sqlType;
    }

    public function formatForSave($value, $hierarchy) {
        return $value;
    }

    public function formatForLoad($field, $postID) {
        $table = ACF::getACFGroupName($field['id']);
        $column = $field['name'];

        return $this->db->getSingleRowValue($table, $column, $postID);
    }
}

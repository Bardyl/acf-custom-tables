<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;
use AcfExtended\Core\Utils\Database;

class Field {
    public Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function formatForSave($value) {
        return $value;
    }

    public function formatForLoad($field, $postID) {
        $table = ACF::getACFGroupName($field['id']);
        $column = $field['name'];

        return $this->db->getSingleRowValue($table, $column, $postID);
    }
}

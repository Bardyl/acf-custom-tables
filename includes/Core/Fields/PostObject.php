<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;

class PostObject extends Field {
    public function formatForSave($value) {
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }

    public function formatForLoad($field, $postID) {
        $table = ACF::getACFGroupName($field['id']);
        $column = $field['name'];
        $json = $this->db->getSingleRowValue($table, $column, $postID);
        $data = json_decode($json);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }
        return $json;
    }
}

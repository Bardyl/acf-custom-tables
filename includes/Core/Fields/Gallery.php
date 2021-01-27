<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;

class Gallery extends Field {
    public function formatForSave($value) {
        return json_encode($value);
    }

    public function formatForLoad($field, $postID) {
        $table = ACF::getACFGroupName($field['id']);
        $column = $field['name'];
        $json = $this->db->getSingleRowValue($table, $column, $postID);
        return json_decode($json, true);
    }
}

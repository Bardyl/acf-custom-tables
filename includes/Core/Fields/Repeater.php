<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;

class Repeater extends Field {
    public function formatForSave($value) {

    }

    public function formatForLoad($field, $postID) {
        $parentField = acf_get_field($field['parent']);

        $table = ACF::getACFGroupName($field['id']);
        $column = $field['name'];

        // fields names are composed like this: parent_iteration_subfield (e.g. faq_2_question)
        // So we need to get the second and last part (iteration and value) to parse properly data from database
        $repeater = str_replace($parentField['name'] . '_', '', $column);
        $repeater = explode('_', $repeater, 2);
        $repeaterIteration = $repeater[0];
        $repeaterItem = $repeater[1];

        // Get column value in database
        // Sad but I don't think we could optimize this because of one query for each value… (maybe wordpress is caching it)
        $json = $this->db->getSingleRowValue($table, $parentField['name'] . '_' . $repeaterItem, $postID);

        // Parse json and return value
        $data = json_decode($json, true);
        return $data[$repeaterIteration]['value'];
    }
}

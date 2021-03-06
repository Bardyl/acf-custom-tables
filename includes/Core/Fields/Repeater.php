<?php

namespace AcfExtended\Core\Fields;

use AcfExtended\Core\Utils\ACF;

class Repeater extends Field {
    public String $sqlType = 'text';
    public bool $specialField = true;

    public function formatForSave($value, $hierarchy) {
        // If it's a repeater field, we need to parse it
        // to get values and place it at right places

        // This will act as a temporary array before we plate it's values
        $repeaterValues = array();
        // We parse each row ($i will act as a pointer for the item number
        $i = 0;
        foreach ($value as $row) {
            // And finally, parse every value in each row to get it's value and save it in the right place
            foreach ($row as $itemKey => $itemValue) {
                $itemObject = get_field_object($itemKey, false, true, false);
                $itemName = $itemObject['name'];
                $repeaterValues[$hierarchy . '_' . $itemName][] = array(
                    'id' => 'row-' . $i,
                    'key' => $itemObject['key'],
                    'name' => $itemObject['name'],
                    'value' => $itemValue
                );
            }
            $i++;
        }

        // Now, transform the values to be handled in a database save
        $repeaterData = array();
        foreach ($repeaterValues as $repeaterKey => $repeaterValue) {
            $repeaterData[$repeaterKey] = json_encode($repeaterValue);
        }
        return $repeaterData;
    }

    public function formatForLoad($field, $postID) {
        // TODO: only work with one level under repeater field
        // Get parent field to check if it's a repeater field
        // Only work on one level, be careful
        $parentField = acf_get_field($field['parent']);

        if ($parentField && $parentField['type'] === 'repeater') {
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
        } else {
            // Get table name
            $table = ACF::getACFGroupName($field['id']);
            $fieldData = array();

            // Parse all sub fields in our repeater and get the value in database
            foreach ($field['sub_fields'] as $row) {
                $column = $field['name'] . '_' . $row['name'];
                $json = $this->db->getSingleRowValue($table, $column, $postID);

                if ($json === '') {
                    $fieldData['row-0'] = array();
                    continue;
                }

                $data = json_decode($json, true);

                // Now, build the array like ACF want us to return it and returns it
                $i = 0;
                foreach ($data as $item) {
                    $fieldData[$i][$item['key']] = $item['value'];
                    $i++;
                }
            }
            return $fieldData;
        }
    }
}

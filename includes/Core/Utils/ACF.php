<?php

namespace AcfExtended\Core\Utils;

class ACF {
    /**
     * @param $id
     * @return string|bool
     */
    public static function getACFGroupName($id) {
        global $wpdb;

        $fieldGroup = acf_get_field_group($id);
        $fieldId = $fieldGroup['ID'];

        $groupName = $wpdb->get_var("SELECT post_name FROM {$wpdb->posts} WHERE id = {$fieldId};");
        if ($groupName) {
            return $groupName;
        }
        return false;
    }
}
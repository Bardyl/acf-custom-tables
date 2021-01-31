<?php

namespace AcfExtended\Core\Utils;

class ACF {
    /**
     * @param $id
     * @return string|bool
     */
    public static function getACFGroupName($id) {
        $fieldGroup = acf_get_field_group($id);
        return $fieldGroup['custom_table_name'];
    }
}

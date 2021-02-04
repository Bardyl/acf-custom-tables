<?php

namespace AcfExtended\Core\Utils;

class FieldsTypes {
    /**
     * @param $maxLength
     * @return string
     */
    public static function getVarcharField($maxLength): string {
        $length = 255;
        if (!empty($maxLength)) {
            $length = $maxLength;
        }
        if ($length > 255) {
            $length = 255;
        }
        return 'varchar(' . $length . ')';
    }
}
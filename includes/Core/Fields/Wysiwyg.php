<?php

namespace AcfExtended\Core\Fields;

class Wysiwyg extends Field {
    public string $sqlType = 'text';

    /**
     * Prevent from saving backslash on HTML content in database
     *
     * @param $value
     * @param $hierarchy
     * @return string
     */
    public function formatForSave($value, $hierarchy) {
        return stripslashes($value);
    }
}

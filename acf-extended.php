<?php

/**
 * Plugin name: ACF Extended
 * Version: 1.0
 * Author: Mathieu Menut
 */

namespace AcfExtended;

use AcfExtended\Core\Fields\Field;
use AcfExtended\Core\Fields\Gallery;
use AcfExtended\Core\Fields\PostObject;
use AcfExtended\Core\Fields\Repeater;
use AcfExtended\Core\Utils\ACF;
use AcfExtended\Core\Utils\Database;

define('HUMANOID_ACF_EXTENDED_PATH', __DIR__);

require_once 'vendor/autoload.php';

class HumanoidAcfExtended {
    private Database $db;

    public function __construct() {
        // Initialize database interface to manage our custom SQL tables
        $this->db = new Database();

        /** ACF group of fields admin page */
        add_action('acf/field_group/admin_head', array($this, 'addMetaBox'));
        /** Save ACF group of fields */
        add_action('acf/update_field_group', array($this, 'saveAcfGroupFields'));
        /** Save ACF fields */
        add_action('acf/save_post', array($this, 'saveAcfData'), 5);
        /** Load ACF value */
        add_filter('acf/load_value', array($this, 'loadACFValue'), 99, 3);
    }

    /**
     * Create a WordPress meta box into ACF single group edit page
     */
    public function addMetaBox() {
        add_meta_box('acf-group-field-table', 'Table MySQL', array($this, 'showMetaBox'), 'acf-field-group');
    }

    /**
     * Add an ACF field into $field_group object
     * to store the database table name for the current group
     * This field will be available every time we get the object field
     */
    public function showMetaBox() {
        global $field_group;

        acf_render_field_wrap(array(
            'instructions' => 'Indiquez le nom de la table MySQL dans laquelle sera sauvegardé le contenu de ce groupe de champs.',
            'label' => 'Nom de la table',
            'name' => 'custom_table_name',
            'prefix' => 'acf_field_group', // To return it when getting acf group field under 'custom_table_name'
            'required' => true,
            'type' => 'text',
            'value' => acf_maybe_get($field_group, 'custom_table_name', false),
        ));
    }

    /**
     * This is called every time we create or update group of fields, once
     * It's purpose is to handle creation of custom tables if required and update all fields
     *
     * TODO: For now, we don't need to delete tables / fields. Maybe it's not something we should do
     *
     * @param $group
     */
    public function saveAcfGroupFields($group) {
        // Future table name
        $tableName = $group['custom_table_name'];

        // Group key
        $key = $group['key'];

        // the table does not exists, create it with default fields
        if (!$this->tableExists($tableName)) {
            $this->db->createTable($tableName);
        }

        // Update fields individually
        $fields = acf_get_fields($key);
        foreach ($fields as $field) {
            // First, save field…
            $this->saveField($field);
            // And then, handle sub fields recursively
            if (isset($field['sub_fields'])) {
                $this->saveSubFields($field['sub_fields'], array($field['name']));
            }
        }
    }

    /**
     * Recursive function to manage fields subfields on multiple level
     * ACF has no limitation on this
     *
     * @param $fields
     * @param $hierarchical
     */
    private function saveSubFields($fields, $hierarchical) {
        // Parse all sub fields recursively
        foreach ($fields as $field) {
            // And for each of them, save it before checking if it has subfields itself
            // and doing this all over again
            $this->saveField($field, $hierarchical);
            if (isset($field['sub_fields'])) {
                $hierarchical[] = $field['name'];
                $this->saveSubFields($field['sub_fields'], $hierarchical);
            }
        }
    }

    /**
     * This hook is trigger for each ACF field change (except delete)
     *
     * @param $field
     * @param array $hierarchical
     */
    private function saveField($field, $hierarchical = array()) {
        // Get post parent name
        $postParentName = ACF::getACFGroupName($field['id']);

        // Be careful, if this is altered, the data could not been mapped
        // A manual migration will be required
        // (nota: this is how ACF works every time, even with the default configuration in meta table)
        $fieldName = $field['name'];

        // We want to manage carefully sub fields so we can show directly in the database (and parse them well too)
        // if they are sub (sub) fields of fields
        // So, we store them in database with parent_[parent_]field_name
        if (!empty($hierarchical)) {
            $fieldName = implode('_', $hierarchical) . '_' . $fieldName;
        }

        // This is the main info which will drive the way we'll treat the field inside the custom table
        $fieldType = $field['type'];

        // Get default value for each new entry
        $fieldDefault = $field['default_value'];

        // But we have to check if parent is a repeater field
        // Repeater fields contains complex data and can't be handled with simple types
        // We store json in it, and we need place, so use everytime a repeater field
        $parentFieldType = $this->getACFGroupType($field['parent']);
        if ($parentFieldType === 'repeater') {
            $fieldType = $parentFieldType;
        }

        // Check if custom table has this field
        if (!$this->columnExists($fieldName, $postParentName)) {
            // Field does not exists, create it
            $this->addNewColumn($fieldName, $fieldType, $fieldDefault, $postParentName);
        }

        // Check if column has the good type
        if (!$this->columnMatches($fieldName, $fieldType, $fieldDefault, $postParentName)) {
            $this->updateExistingColumn($fieldName, $fieldType, $fieldDefault, $postParentName);
        }
    }

    /**
     * Called every time a post is saved to save ACF custom fields not in the post meta table
     * but into our custom tables (one by group of fields)
     *
     * TODO: make sure data is not also stored in post meta table
     *
     * @param $postID
     */
    public function saveAcfData($postID) {
        $acfThings = $this->getAcfFieldsValues($_POST['acf']);

        // Update matching tables with new values
        // Existing rows are deleting and re-inserted
        foreach ($acfThings as $table => $group) {
            $this->db->insertOrUpdateRow($table, $postID, $group);
        }

        // Unset to not save in post meta table
        unset($_POST['acf']);
    }

    private function getAcfFieldsValues($fields) {
        $values = array();
        if (!empty($fields)) {
            foreach ($fields as $key => $value) {
                $field = get_field_object($key, false, true, false);
                if (!$field) {
                   continue;
                }
                $table = ACF::getACFGroupName($field['id']);

                // Build hierarchical name of field to get the column field in database
                $hierarchy = $this->getHierarchicalFieldName($field);
                $hierarchy .= '_' . $field['name'];
                if ($hierarchy !== '' && $hierarchy[0] === '_') {
                    $hierarchy = substr($hierarchy, 1);
                }

                // ! Tricky part
                if ($field['type'] === 'repeater') {
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
                    foreach ($repeaterValues as $repeaterKey => $repeaterValue) {
                       $values[$table][$repeaterKey] = json_encode($repeaterValue);
                    }
                } else if ($field['type'] === 'gallery') {
                    $values[$table][$hierarchy] = (new Gallery())->formatForSave($value);
                } else if ($field['type'] === 'post_object') {
                    $values[$table][$hierarchy] = (new PostObject())->formatForSave($value);
                } else if (is_array($value)) {
                    // If it's an array, we'll need to parse it to determine which type of complex
                    // field we're dealing with
                    // This is a recursive loop
                    $values = array_merge_recursive($values, $this->getAcfFieldsValues($value));
                } else {
                    // The easy part is if if it's a value:
                    // retrieve the field in database to add it
                    $values[$table][$hierarchy] = $value;
                }
            }
        }
        return $values;
    }

    private function getHierarchicalFieldName($field) {
        $hierarchy = '';
        if (isset($field['parent'])) {
            $parentField = acf_get_field($field['parent']);
            if ($parentField !== false) {
                $hierarchy .= $this->getHierarchicalFieldName($parentField) . '_' . $parentField['name'];
            }
        }
        return $hierarchy;
    }

    /**
     * Only use this in admin area because it's not optimized. The function
     * is called for every row with one sql query by field
     * It loads data from custom table instead of loading them from post meta default table
     *
     * @param $value
     * @param $postID
     * @param $field
     * @return string|array
     */
    public function loadACFValue($value, $postID, $field) {
        // If no sub fields, that's easy, simply get the value inside our custom table
        if (!isset($field['sub_fields'])) {
            // TODO: clean code
            // TODO: only work with one level under repeater field
            // Get parent field to check if it's a repeater field
            // Only work on one level, be careful
            $parentField = acf_get_field($field['parent']);
            if ($parentField && $parentField['type'] === 'repeater') {
                return (new Repeater())->formatForLoad($field, $postID);
            } else if ($field['type'] === 'gallery') {
                return (new Gallery())->formatForLoad($field, $postID);
            } else if ($field['type'] === 'post_object') {
                return (new PostObject())->formatForLoad($field, $postID);
            } else {
                // Easy way
                return (new Field())->formatForLoad($field, $postID);
            }
        }

        // TODO: clean code
        // TODO: only work with repeater on one level
        if ($field['type'] === 'repeater') {
            // Get table name
            $table = ACF::getACFGroupName($field['id']);
            $fieldData = array();
            // Parse all sub fields in our repeater and get the value in database
            foreach ($field['sub_fields'] as $row) {
                $column = $field['name'] . '_' . $row['name'];
                $json = $this->db->getSingleRowValue($table, $column, $postID);
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

        // For fields with sub items, we must render the tree of all contained values with their field tree keys
        // We're making this in a custom function to handle this recursively
        return $this->loadSubAcfValues($field, $postID);
    }

    /**
     * Recursive function to get sub (sub*) fields if necessary
     *
     * @param $field
     * @param $postID
     * @param string $parentColumns
     * @return array
     */
    private function loadSubAcfValues($field, $postID, $parentColumns = ''): array {
        $data = array();

        // Check if subfields exists
        // (for first level, this is redundant because we test it on main call)
        if (isset($field['sub_fields'])) {
            // Build parent columns variable to get the column name properly
            // Remember that the column name is named after their parents groups field
            if ($parentColumns === '') {
                $parentColumns = $field['name'];
            } else {
                $parentColumns = $parentColumns . '_' . $field['name'];
            }

            // Parse every subfield and get the single field value or re-run the function for sub fields recursively
            foreach ($field['sub_fields'] as $subField) {
                $table = ACF::getACFGroupName($subField['id']);
                $column = $parentColumns . '_' . $subField['name'];

                if (isset($subField['sub_fields'])) {
                    $data[$subField['key']] = $this->loadSubAcfValues($subField, $postID, $parentColumns);
                } else {
                    $data[$subField['key']] = $this->db->getSingleRowValue($table, $column, $postID);
                }
            }
        }
        return $data;
    }

    /**
     * Check if custom table exists in database
     *
     * @param $tableName
     * @return bool
     */
    private function tableExists($tableName): bool {
        $tableName = $this->db->prefix . $tableName;
        $sqlTables = $this->db->getTables();
        foreach ($sqlTables as $table) {
            if ($table[0] === $tableName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $id
     * @return string|bool
     */
    private function getACFGroupName($id) {
        $fieldGroup = acf_get_field_group($id);
        return $fieldGroup['custom_table_name'];
    }

    private function getACFGroupType($id) {
        $field = acf_get_field($id);

        if ($field) {
            return $field['type'];
        }
        return false;
    }

    /**
     * Create custom table to handle a new group of ACF fields
     *
     * @param $column
     * @param $type
     * @param $default
     * @param $table
     */
    private function addNewColumn($column, $type, $default, $table) {
        $sqlType = $this->getTypeFromACFType($type);
        $this->db->addColumn($table, $column, $sqlType, $default);
    }

    /**
     * Alter specific column to match new ACF fields configuration
     *
     * @param $column
     * @param $type
     * @param $default
     * @param $table
     */
    private function updateExistingColumn($column, $type, $default, $table) {
        $sqlType = $this->getTypeFromACFType($type);
        $this->db->updateColumn($table, $column, $sqlType, $default);
    }

    /**
     * Check if custom table already exists
     *
     * @param $column
     * @param $table
     * @return bool
     */
    private function columnExists($column, $table): bool {
        if (empty($this->db->getTablesColumns($table, $column))) {
            return false;
        }
        return true;
    }

    /**
     * First version of mapping the field type of acf custom field
     * with the one we store in our custom tables
     *
     * TODO: upgrade with abstract class with one class by ACF Type
     *
     * @param $acfType
     * @return string
     */
    private function getTypeFromACFType($acfType): string {
        switch ($acfType) {
            case 'number':
            case 'image':
                return 'INT';
            case 'textarea':
                return 'TEXT';
            case 'repeater':
                return 'LONGTEXT';
            default:
                return 'VARCHAR(255)';
        }
    }

    /**
     * Check if the SQL field type is matching with the one given by ACF
     * (with our mapping translation in getTypeFromACFType function
     *
     * @param $column
     * @param $type
     * @param $default
     * @param $table
     * @return bool
     */
    private function columnMatches($column, $type, $default, $table): bool {
        $sqlType = $this->getTypeFromACFType($type);

        $sql = $this->db->getFields($table, $column);
        $type = $sql[0]['Type'];
        $sqlDefault = $sql[0]['Default'];

        if ($type === strtolower($sqlType) && $default === strtolower($sqlDefault)) {
            return true;
        }
        return false;
    }
}

new HumanoidAcfExtended();

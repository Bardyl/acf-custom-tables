<?php

/**
 * Plugin name: ACF Extended
 * Version: 1.0
 * Author: Mathieu Menut
 */

namespace AcfExtended;

define('HUMANOID_ACF_EXTENDED_PATH', __DIR__);

require_once 'Database.php';

class HumanoidAcfExtended {
    private Database $db;

    public function __construct() {
        // Initialize database interface to manage our custom SQL tables
        $this->db = new Database();

        /** Save ACF group of fields */
        add_action('save_post_acf-field-group', array($this, 'saveFields'), 99, 2);
        /** Called every time an acf field is saved in the group field edit view */
        add_action('save_post_acf-field', array($this, 'saveField'), 99, 2);
        /** Save ACF fields */
        add_action('acf/save_post', array($this, 'saveAcfData'), 5);
        /** Load ACF value */
        add_filter('acf/load_value', array($this, 'loadACFValue'), 99, 3);
    }

    /**
     * This is called every time we create or update group of fields, once
     * It's only purpose is to handle creation of custom tables if required
     *
     * TODO: For now, we don't need to delete tables. Maybe it's not something we should do
     *
     * @param $postID
     * @param $post
     */
    public function saveFields($postID, $post) {
        // Future table name
        $postName = get_field_object($post->post_name);

        // the table does not exists, create it
        if (!$this->tableExists($postName)) {
            $this->db->createTable($postName);
        }
    }

    /**
     * This hook is trigger for each ACF field change (except delete)
     * If we have three field
     *
     * @param $postID
     * @param $post
     */
    public function saveField($postID, $post) {
        // Get post parent name
        $postParentId = $post->post_parent;
        $postParentName = $this->getACFGroupName($postParentId);

        // Be careful, if this is altered, the data could not been mapped
        // A manual migration will be required
        // (nota: this is how ACF works every time, even with the default configuration in meta table)
        $fieldName = $post->post_excerpt;

        // Contains everything we know about the field
        $fieldInfo = unserialize($post->post_content);

        // This is the main info which will drive the way we'll treat the field inside the custom table
        $fieldType = $fieldInfo['type'];

        // Check if custom table has this field
        if (!$this->columnExists($fieldName, $postParentName)) {
            // Field does not exists, create it
            $this->addNewColumn($fieldName, $fieldType, $postParentName);
        }

        // Check if column has the good type
        if (!$this->columnTypeMatches($fieldName, $fieldType, $postParentName)) {
            $this->updateExistingColumn($fieldName, $fieldType, $postParentName);
        }
    }

    /**
     * Called every time a post is saved to save ACF custom fields not in the postmeta table
     * but into our custom tables (one by group of fields)
     *
     * TODO: make sure data is not also stored in postmeta table
     *
     * @param $postID
     */
    public function saveAcfData($postID) {
        $acfThings = array();

        // Parse all acf fields
        foreach ($_POST['acf'] as $key => $value) {
            // Get field object to find the parent (for our custom table retrieve)
            $acfField = get_field_object($key);
            $acfFieldName = $acfField['name'];

            // Get SQL table name
            $acfGroup = $this->getACFGroupName($acfField['parent']);

            // Update
            $acfThings[$acfGroup][$acfFieldName] = $value;
        }

        // Update matching tables with new values
        // Existing rows are deleting and re-inserted
        foreach ($acfThings as $table => $group) {
            $this->db->insertOrUpdateRow($table, $postID, $group);
        }
    }

    /**
     * Only use this in admin area because it's not optimized. The function
     * is called for every row with one sql query by field
     * It loads data from custom table instead of loading them from post meta default table
     *
     * @param $value
     * @param $postID
     * @param $field
     * @return string
     */
    public function loadACFValue($value, $postID, $field): string {
        $table = $this->getACFGroupName($field['parent']);
        $column = $field['name'];
        return $this->db->getSingleRowValue($table, $column, $postID);
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
        global $wpdb;
        $groupName = $wpdb->get_var("SELECT post_name FROM {$wpdb->posts} WHERE id = {$id};");
        if ($groupName) {
            return $groupName;
        }
        return false;
    }

    /**
     * Create custom table to handle a new group of ACF fields
     *
     * @param $column
     * @param $type
     * @param $table
     */
    private function addNewColumn($column, $type, $table) {
        $sqlType = $this->getTypeFromACFType($type);
        $this->db->addColumn($table, $column, $sqlType);
    }

    /**
     * Alter specific column to match new ACF fields configuration
     *
     * @param $column
     * @param $type
     * @param $table
     */
    private function updateExistingColumn($column, $type, $table) {
        $sqlType = $this->getTypeFromACFType($type);
        $this->db->updateColumn($table, $column, $sqlType);
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
                return 'INT';
            case 'textarea':
                return 'TEXT';
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
     * @param $table
     * @return bool
     */
    private function columnTypeMatches($column, $type, $table): bool {
        $sqlType = $this->getTypeFromACFType($type);
        $sqlExistingType = $this->db->getFields($table, $column);
        $type = $sqlExistingType[0]['Type'];

        if ($type === strtolower($sqlType)) {
            return true;
        }
        return false;
    }
}

new HumanoidAcfExtended();

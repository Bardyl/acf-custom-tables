<?php

namespace AcfExtended;

/**
 * Interface between our ACF new database scheme implementation and $wpdb
 *
 * Class Database
 * @package AcfExtended
 */
class Database {
    private String $charsetCollate;
    public String $prefix;

    public function __construct() {
        global $wpdb;

        $this->getCharsetCollate();
        $this->prefix = $wpdb->prefix;
    }

    /**
     * Create a new MySQL table
     *
     * @param $table
     */
    public function createTable($table) {
        global $wpdb;

        $table = $wpdb->prefix . $table;

        $sql = "CREATE TABLE $table (
            post_id mediumint(9) NOT NULL,
            PRIMARY KEY (post_id),
            UNIQUE (post_id)
        ) {$this->charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Add new SQL column to existing custom table
     *
     * @param $table
     * @param $column
     * @param $type
     */
    public function addColumn($table, $column, $type) {
        global $wpdb;
        $table = $wpdb->prefix . $table;
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$type};");
    }

    /**
     * Update existing column after some type change in our acf config
     *
     * @param $table
     * @param $column
     * @param $type
     */
    public function updateColumn($table, $column, $type) {
        global $wpdb;
        $table = $wpdb->prefix . $table;
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN {$column} {$type};");
    }

    /**
     * Get all WordPress tables
     *
     * @return array
     */
    public function getTables(): array {
        global $wpdb;
        return $wpdb->get_results('SHOW TABLES', ARRAY_N);
    }

    /**
     * Get all columns (or some of them) of custom SQL table
     *
     * @param $table
     * @param null $column
     * @return array
     */
    public function getTablesColumns($table, $column = null): array {
        global $wpdb;
        $table = $wpdb->prefix . $table;

        $like = '';
        if ($column) {
            $like = " LIKE '{$column}'";
        }
        return $wpdb->get_results("SHOW COLUMNS FROM {$table}{$like};", ARRAY_N);
    }

    /**
     * Get information about all columns of a specific one like their type
     *
     * @param $table
     * @param null $column
     * @return array
     */
    public function getFields($table, $column = null): array {
        global $wpdb;
        $table = $wpdb->prefix . $table;

        $where = '';
        if ($column) {
            $where = " WHERE Field = '{$column}'";
        }
        return $wpdb->get_results("SHOW FIELDS FROM {$table}{$where};", ARRAY_A);
    }

    /**
     * Update line of data in one of our custom table
     * Called when we save a post in the back-office
     *
     * @param $table
     * @param $postID
     * @param $values
     */
    public function insertOrUpdateRow($table, $postID, $values) {
        global $wpdb;
        $table = $wpdb->prefix . $table;
        $values['post_id'] = $postID;
        $wpdb->replace($table, $values);
    }

    /**
     * Retrieve data from our custom tables
     *
     * @param $table
     * @param $column
     * @param $postID
     * @return string
     */
    public function getSingleRowValue($table, $column, $postID): string {
        global $wpdb;
        $table = $wpdb->prefix . $table;
        $data = $wpdb->get_var("SELECT {$column} FROM {$table} WHERE post_id = {$postID};");
        if ($data) {
            return $data;
        }
        return '';
    }

    /**
     * Get WordPress database collate
     */
    private function getCharsetCollate() {
        global $wpdb;
        $this->charsetCollate = $wpdb->get_charset_collate();
    }
}

<?php
namespace SimpleORM;

use mysql_xdevapi\Exception;

/**
 * @property string $created_at
 * @property string $updated_at
 */
abstract class Model {
// <editor-fold desc="class properties">
    protected array $resolved_data;
    protected array $data;
    protected array $orgdata;

    private static ?self $_instance = null;

    public string $_tablename;
    public ?array $foreign_keymap = null;
    public array $conditional_foreign_keymap = array();
    public array $hidden_keys = array();
    public string $primary_key = "id";
    public bool $database_timestamps = true;

    protected static array $where = array();
    protected static array $groupby = array();
    protected static int $limit = 0;
    protected static string $orderby = "";

    private bool $is_dirty = false;
    private bool $is_new = true;
    /**
     * @var bool
     */
    private bool $is_deleted = false;

    // </editor-fold>

    public function __construct($table = null, $autofill = null)
    {
        if ($table == null) $table = get_called_class();
        $this->_tablename = strtolower($table);
        if(method_exists($this, "boot")) $this->boot();
        if ($autofill != null) {
            $this->Fill($autofill);
        }
        if ($this->foreign_keymap === null) {
            $this->foreign_keymap = Database_Structure::GetFKForTable($table);
        }
    }

    // <editor-fold desc="Query builder chaining functions">

    /**
     * @return static
     */
    public static function Select(): self
    {
        $me = get_called_class();
        return new $me();
    }

    public function WHERE($where, $type = SQL::AND, $group = 0): Model
    {
        if (count($where) == 0) return $this;
        $data = [
            "type" => $type,
            "group" => $group,
            "values" => $where
        ];
        self::$where[] = $data;
        return $this;
    }

    public function GROUPWHERE($where, $type = SQL::AND): Model
    {
        return $this->WHERE($where, $type, true);
    }

    public function AND(): Model
    {
        self::$where[] = "AND";
        return $this;
    }

    public function OR(): Model
    {
        self::$where[] = "OR";
        return $this;
    }

    public function ISNULL($field): Model
    {
        self::$where[] = "`{$field}` IS NULL";
        return $this;
    }

    public function LIMIT($limit): Model
    {
        self::$limit = $limit;
        return $this;
    }

    public function ORDERBY($field, $sort = SQL::Ascending): Model
    {
        self::$orderby = "`$field` $sort";
        return $this;
    }

    public function DATEWHERE($date, $field = "created_at"): Model
    {
        if (strlen($date) > 0)
            self::$where[] = "date(`{$field}`) = \"{$date}\"";
        return $this;
    }

    public function GROUPBY($fields): Model
    {
        if (is_array($fields)) {
            self::$groupby = array_merge(self::$groupby, $fields);
        } else {
            self::$groupby[] = $fields;
        }
        return $this;
    }

    /*
     * A final function for the Query Builder
     *      although very similar to GET but this bypasses
     *      the DBCollection since we're only looking for one row
     *
     * Note any use of LIMIT will be ignored
     */
    public function FIRST($select_fields = "*")
    {
        $sql = MySQL::getInstance();
        $get_called_class = get_called_class();
        $query = $this->QueryBuilderBuild($sql, $select_fields, 1);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }

        $row = $result->fetch_assoc();
        return new $get_called_class($get_called_class, $row);
    }

    /*
     * A final function for the Query Builder
     *      This is the same as FIRST but if the record doesn't exist it will create it
     *      optionally $callback will be called if a new record is created
     */

    public function FIRSTORNEW($callback = null, $select_fields = "*")
    {
        $find_results = $this->FIRST($select_fields);       // Try to find
        if ($find_results) return $find_results;             // If found return, nothing else to do!

        $get_called_class = get_called_class();             // If not found we create a new one
        $result_to_return = new $get_called_class($get_called_class);
        // Opt: Run the call back function to let the caller know we created a new record
        if ($callback != null) $callback($result_to_return);
        return $result_to_return;
    }

    /*
     * A final function for the Query Builder
     *      This function takes the chained functions and builds the query
     *  Note: We are not able to create a function that creates a new record if no results
     *          due to the fact this returns a dbCollection
     */
    /**
     * @param string $select_fields
     * @return dbCollection
     */
    public function GET($select_fields = "*"): ?dbCollection
    {
        $sql = MySQL::getInstance();
        $get_called_class = get_called_class();
        $query = $this->QueryBuilderBuild($sql, $select_fields);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }

        $result_to_output = new dbCollection();
        while ($row = $result->fetch_assoc()) {
            $result_to_output[] = new $get_called_class($get_called_class, $row);
        }
        return $result_to_output;
    }

    // </editor-fold>

    public static function getForUpdateOnly($id)
    {
        $get_called_class = get_called_class();
        $result_to_return = new $get_called_class($get_called_class);
        $result_to_return->is_new = false;
        $result_to_return->is_dirty = true;
        $result_to_return->{$result_to_return->primary_key} = $id;
        return $result_to_return;
    }

    public function GetChildren($dest): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = strtolower($dest);
        $sql = MySQL::getInstance();

        $fks = Database_Structure::GetFKForTable($table);

        $fk = function ($fks, $source_class) {
            foreach ($fks as $key => $fk) {
                if ($fk['class'] == $source_class) return [$key, $fk['column']];
            }
            return null;
        };
        list($dest_column, $source_column) = $fk($fks, $get_called_class);
        $value = $sql->real_escape_string($this->{$source_column});
        $query = sprintf(/** @lang sql */ "SELECT * FROM `%s` WHERE `%s` = '%s'", $table, $dest_column, $value);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }
        $result_to_output = new dbCollection();
        while ($row = $result->fetch_assoc()) {
            $result_to_output[] = new $dest($dest, $row);
        }
        return $result_to_output;
    }

    public static function GetBySource($source): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = strtolower(get_called_class());
        $sql = MySQL::getInstance();

        $source_class = get_class($source);
        $fks = Database_Structure::GetFKForTable($table);
        $fk = function ($fks, $source_class) {
            foreach ($fks as $key => $fk) {
                if ($fk['class'] == $source_class) return [$key, $fk['column']];
            }
            return null;
        };
        list($dest_column, $source_column) = $fk($fks, $source_class);
        $value = $sql->real_escape_string($source->{$source_column});
        $query = sprintf(/** @lang sql */ "SELECT * FROM `%s` WHERE `%s` = '%s'", $table, $dest_column, $value);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }
        $result_to_output = new dbCollection();
        while ($row = $result->fetch_assoc()) {
            $result_to_output[] = new $get_called_class($get_called_class, $row);
        }
        return $result_to_output;
    }

    public static function SimpleWhere($where, $type = "AND")
    {
        $get_called_class = get_called_class();
        $table = strtolower(get_called_class());
        $sql = MySQL::getInstance();

        if (!is_array($where)) {
            $dwhere['id'] = $where;
        } else {
            $dwhere = $where;
        }
        foreach ($dwhere as $x) {
            if (count($x) == 2) {
                $value = $sql->real_escape_string($x[1]);
                $fields[] = "`{$x[0]}` = '$value'";
            }
            if (count($x) == 3) {
                $value = $sql->real_escape_string($x[2]);
                $fields[] = "`{$x[0]}` {$x[1]} '$value'";
            }
        }
        $field_list = join($type, $fields);

        $query = sprintf(/** @lang sql */ "SELECT * FROM %s WHERE %s", $table, $field_list);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }
        $result_to_output = new dbCollection();
        while ($row = $result->fetch_assoc()) {
            $result_to_output[] = new $get_called_class($get_called_class, $row);
        }
        return $result_to_output;
    }

    public static function All(): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = strtolower($get_called_class);
        $sql = MySQL::getInstance();

        $query = sprintf(/** @lang sql */ "SELECT * FROM %s", $table);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }

        $result_to_output = new dbCollection();
        while ($row = $result->fetch_assoc()) {
            $result_to_output[] = new $get_called_class(get_called_class(), $row);
        }
        return $result_to_output;
    }

    public static function find($value, $otherkey = null)
    {
        $get_called_class = get_called_class();
        $result_to_return = new $get_called_class($get_called_class);
        $sql = MySQL::getInstance();

        $key = $otherkey ?: $result_to_return->primary_key;

        if (!is_numeric($value)) $value = "'" . $sql->real_escape_string($value) . "'";

        $query = sprintf(/** @lang sql */ "SELECT * FROM %s WHERE %s = %s", $result_to_return->_tablename, $key, $value);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Find {$result_to_return->_tablename}.{$key} = {$value} returned 0 rows");
            return null;
        }
        $row = $result->fetch_assoc();
        $result_to_return->fill($row);
        return $result_to_return;
    }

    public static function findOrNew($value, $callback = null, $otherkey = null)
    {
        $find_results = self::find($value, $otherkey);                 // Try to find
        if ($find_results) return $find_results;             // If found return, nothing else to do!

        $get_called_class = get_called_class();             // If not found we create a new one
        $result_to_return = new $get_called_class($get_called_class);
        // Opt: Run the call back function to let the caller know we created a new record
        if ($callback != null) $callback($result_to_return);
        return $result_to_return;
    }

    /**
     * Function: QueryBuilderBuild
     *      Builds the query based on the chained functions (ones in call caps)
     * @param MySQL $sql Instance of the mysqli client
     * @param string $select_fields What fields to select
     * @param int|null $hardlimit opt: if defined will ignore limit in the chain and use this
     * @return string the mysql query
     */
    private function QueryBuilderBuild(MySQL $sql, string $select_fields = "*", int $hardlimit = null): string
    {
        $data = self::$where;
        $limit = $hardlimit ? $hardlimit : self::$limit;

        $sort = self::$orderby;
        $groupby = self::$groupby;
        $field_list = "";
        foreach ($data as $d) {
            if (is_array($d)) {
                $fields = [];
                foreach ($d['values'] as $v) {
                    if (count($v) == 2) {
                        $value = $v[1];
                        $fields[] = "`{$v[0]}` = '$value'";
                    }
                    if (count($v) == 3) {
                        $value = $v[2];
                        $fields[] = "`{$v[0]}` {$v[1]} '$value'";
                    }
                }
                if ($d['group']) $field_list .= "(";
                $field_list .= join(' ' . $d['type'] . ' ', $fields);
                if ($d['group']) $field_list .= ")";
            } else {
                $field_list .= " {$d} ";
            }
        }
        $field_list = trim($field_list);
        $field_list = rtrim($field_list, "AND");
        $field_list = rtrim($field_list, "OR");
        $field_list = trim($field_list);

        if ($field_list != "")
            $query = sprintf(/** @lang sql */ "SELECT %s FROM %s WHERE %s", $select_fields, $this->_tablename, $field_list);
        else
            $query = sprintf(/** @lang sql */ "SELECT %s FROM %s", $select_fields, $this->_tablename);

        if (count($groupby) > 0) $query .= " GROUP BY " . join(',', $groupby);
        if ($sort != "") $query .= " ORDER BY {$sort}";
        if ($limit != 0) $query .= " LIMIT {$limit}";

        self::$where = array();
        self::$orderby = "";
        self::$limit = 0;
        return $query;
    }

    public function save($ignoreifdirty = false)
    {
        if ($this->is_dirty == false && $ignoreifdirty == false) return; // Nothing to save
        $sql = MySQL::getInstance();
        if ($this->is_new) {
            if ($this->database_timestamps) {
                $this->created_at = SQL::Now;
                $this->updated_at = SQL::Now;
            }
            $sql->insertArray($this->_tablename, $this->data);
            $this->orgdata[$this->primary_key] = $this->data[$this->primary_key] = $sql->insert_id;
        } else {
            if ($this->database_timestamps) {
                $this->updated_at = SQL::Now;
            }
            $filter_sql_data = array_filter($this->data, function ($v, $k) { // Filter out anything that didn't change
                return !(strcmp($this->orgdata[$k] ?? "", $v) === 0);
            }, ARRAY_FILTER_USE_BOTH);
            $sql->updateArray($this->_tablename, $filter_sql_data, $this->primary_key, $this->data[$this->primary_key]);
            $this->is_dirty = false;
            $this->is_new = false;
        }
    }

    public function delete()
    {
        $sql = MySQL::getInstance();
        $value = is_numeric($this->data[$this->primary_key]) ? $this->data[$this->primary_key] : "'" . $sql->real_escape_string($this->data[$this->primary_key]) . "'";

        $query = /** @lang MySQl */
            "DELETE FROM `($this->_tablename) WHERE `{$this->primary_key}` = $value";
        $sql->query($query);
        $this->is_deleted = true;
    }

    public function rollback()
    {
        $this->data = $this->orgdata;
        $this->is_dirty = false; // Since we're back to in-sync with the server's database we're not "dirty" anymore.
    }

    public function refresh()
    {
        // return $this->find($this->data[$this->primary_key], $this->primary_key); // very similar to this function but it's static so it will return an new instance of the class
        $sql = MySQL::getInstance();

        $value = is_numeric($this->data[$this->primary_key]) ? $this->data[$this->primary_key] : "'" . $sql->real_escape_string($this->data[$this->primary_key]) . "'";

        $query = /** @lang MySql */
            "SELECT * FROM {$this->_tablename} WHERE {$this->primary_key} = {$value}";
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Find {$this->_tablename}.{$this->primary_key} = {$value} returned 0 rows");
            return false;
        }
        $row = $result->fetch_assoc();
        $this->fill($row);
        $this->is_dirty = false;
        return true;
    }

    public function ResolveForeignKeys($ignore_hidden_keys = true)
    {
        foreach ($this->conditional_foreign_keymap as $key => $_class) {
            $class = call_user_func($_class, $this);
            $this->ResolveForeignKey($ignore_hidden_keys, $key, $class);
        }
        foreach ($this->foreign_keymap as $key => $class) {
            if (is_array($class)) $this->ResolveForeignKey($ignore_hidden_keys, $key, $class['class'], $class['column']);
            else $this->ResolveForeignKey($ignore_hidden_keys, $key, $class);
        }
    }

    public function ResolveForeignKey($ignore_hidden_keys, $key, $class, $fkcol = 'id')
    {
        $result_value = $this->data[$key];
        if (empty($result_value)) return; // Don't resolve null.
        if ($ignore_hidden_keys && in_array($key, $this->hidden_keys)) {
            return; // Don't resolve hidden keys, we'll lazy load them if read.
        }
        $data = SQLCache::GetFromCache($class, $result_value, function ($class, $key, $col) {
            return $class::find($key, $col);
        }, $fkcol);
        if ($data === null) {
            Debug::Warn("$key in $class failed ($result_value)");
            return;
        }
        $data->ResolveForeignKeys();
        $this->ResolvedSet($key, $data);
    }

    public function toArray($hide_db_time, $showempty = false): array
    {
        return array_merge(
            array_filter($this->data, function ($v, $k) use ($hide_db_time, $showempty) {
                if (empty($v) && $showempty == false) return false;
                return !isset($this->foreign_keymap[$k]) && !$this->isHiddenKey($k, $hide_db_time);
            }, ARRAY_FILTER_USE_BOTH),
            $this->toArrayWith($this->resolved_data ?? array(), $hide_db_time, $showempty)
        );
    }
    // Merging arrays while calling toArray() on each element
    // Better way to do this??
    private function toArrayWith($array, $hide_db_time, $showempty = false): array
    {
        $out = array();
        foreach ($array as $k => $v) $out[$k] = $v->toArray($hide_db_time, $showempty);
        return $out;
    }

    public function isHiddenKey($key, $hide_db_time): bool
    {
        if ($hide_db_time && ($key == "created_at" || $key == "updated_at")) return true;
        return in_array($key, $this->hidden_keys);
    }

    // <editor-fold desc="Magic Functions">

    public function ResolvedSet($name, $value)
    {  // not really magic but along with same lines as __set
        $this->resolved_data[$name] = $value;
    }

    // Magic set function
    public function __set($name, $value)
    {
        if (isset($this->data[$name]) && $this->data[$name] === $value) return; // Ignore set if it's the same value
        $this->data[$name] = $value;
        $this->is_dirty = true;
    }

    // Magic get function
    public function __get($name)
    {
        $name = strtolower($name);
        $name = str_replace(" ", "_", $name);

        if ($this->is_deleted) {
            debug::Warn("Attempting to access deleted dbObject, please rollback first.");
            return null;
        }
        if (in_array($name, $this->hidden_keys) && !empty($this->data[$name]) && empty($this->resolved_data[$name])) {
            // Lazy load hidden keys that are unresolved
            if (isset($this->foreign_keymap[$name]))
                $this->ResolveForeignKey(false, $name, $this->foreign_keymap[$name]);
            if (isset($this->conditional_foreign_keymap[$name]))
                $this->ResolveForeignKey(false, $name, call_user_func($this->conditional_foreign_keymap[$name], $this));
        }
        if (isset($this->resolved_data[$name])) {
            return $this->resolved_data[$name];
        }
        return $this->data[$name];
    }

    // Magic isset function
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    // Magic unset function
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function __toString()
    {
        if (isset($this->data[$this->primary_key])) return $this->data[$this->primary_key];
        throw new Exception(get_called_class() . " could not be converted to string");
    }

    public function __debugInfo()
    {
        return [
            $this->data
        ];
    }

    private function Fill($args)
    { // Also not magic but works with the data magic uses
        $this->data = $args;
        $this->orgdata = $args;
        $this->is_new = false;
    }
    // </editor-fold>
}

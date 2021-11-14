<?php

namespace SimpleORM;

use mysql_xdevapi\Exception;

/**
 * @property string $created_at
 * @property string $updated_at
 */
abstract class Model
{
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
    private bool $is_deleted = false;

    // </editor-fold>

    /**
     * @param string|null $table
     * @param array|null $autofill
     */
    public function __construct(string $table = null, array $autofill = null)
    {
        if ($table == null) $table = get_called_class();
        $this->_tablename = self::_table_name($table);
        if (method_exists($this, "boot")) $this->boot();
        if ($autofill != null) {
            $this->Fill($autofill);
        }
        if ($this->foreign_keymap === null) {
            $this->foreign_keymap = Database_Structure::GetFKForTable($table);
        }
    }

    /**
     * @param $className
     * @return string
     */
    protected static function _table_name($className): string
    {
        return SQLCache::GetFromCache($className, 'metadata', function ($class, $key, $col) {
            preg_match_all("/(^[a-z]|[A-Z0-9])[a-z]*/", $class, $matches);
            return strtolower(join("_", $matches[0]));
        }, '_db_table');
    }

    // <editor-fold desc="Query builder chaining functions">

    /**
     * Start of the Query Builder
     * @return static
     */
    public static function Select(): self
    {
        $me = get_called_class();
        return new $me();
    }

    /**
     * Query Builder: Where
     * @param array $where
     * @param string $type
     * @param bool $group
     * @return $this
     */
    public function WHERE(array $where, string $type = SQL::AND, bool $group = false): Model
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

    /**
     * Query Builder: Group Where
     * @param array $where
     * @param string $type
     * @return $this
     */
    public function GROUPWHERE(array $where, string $type = SQL::AND): Model
    {
        return $this->WHERE($where, $type, true);
    }

    /**
     * Query Builder: Adds an AND to the query
     * @return $this
     */
    public function AND(): Model
    {
        self::$where[] = "AND";
        return $this;
    }

    /**
     * Query Builder: Adds an OR to the query
     * @return $this
     */
    public function OR(): Model
    {
        self::$where[] = "OR";
        return $this;
    }

    /**
     * Query Builder: Adds a ISNULL check to the query
     * @param string $field
     * @return $this
     */
    public function ISNULL(string $field): Model
    {
        self::$where[] = "`$field` IS NULL";
        return $this;
    }

    /**
     * Query Builder: Adds a LIMIT to the query
     * @param int|string $limit
     * @return self
     */
    public function LIMIT($limit): Model
    {
        self::$limit = $limit;
        return $this;
    }

    /**
     * Query Builder: Ands an ORDER BY to the query
     * @param string $field
     * @param string $sort
     * @return self
     */
    public function ORDERBY(string $field, string $sort = SQL::Ascending): Model
    {
        self::$orderby = "`$field` $sort";
        return $this;
    }

    /**
     * Query Builder: Adds a WHERE $field (defaults to created_at) = $date to the query
     * @param $date
     * @param string $field
     * @return self
     */
    public function DATEWHERE($date, string $field = "created_at"): Model
    {
        if (strlen($date) > 0)
            self::$where[] = "date(`$field`) = \"$date\"";
        return $this;
    }

    /**
     * Query Builder: Adds a GROUP BY to the query
     * @param $fields
     * @return self
     */
    public function GROUPBY($fields): Model
    {
        if (is_array($fields)) {
            self::$groupby = array_merge(self::$groupby, $fields);
        } else {
            self::$groupby[] = $fields;
        }
        return $this;
    }

    /**
     * A final function for the Query Builder
     *   although very similar to GET but this bypasses
     *   the DBCollection since we're only looking for one row
     * @param string $select_fields
     * @return self
     */
    public function FIRST(string $select_fields = "*"): ?Model
    {
        $sql = MySQL::getInstance();
        $get_called_class = get_called_class();
        $query = $this->QueryBuilderBuild($select_fields, 1);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            Debug::Info("Query returned 0 rows");
            return null;
        }

        $row = $result->fetch_assoc();
        return new $get_called_class($get_called_class, $row);
    }

    /**
     * A final function for the Query Builder
     *   This is the same as FIRST but if the record doesn't exist it will create it
     *   optionally $callback will be called if a new record is created
     * @param ?callable $callback
     * @param string $select_fields
     * @return Model
     */
    public function FIRSTORNEW(callable $callback = null, string $select_fields = "*"): Model
    {
        $find_results = $this->FIRST($select_fields);       // Try to find
        if ($find_results) return $find_results;             // If found return, nothing else to do!

        $get_called_class = get_called_class();             // If not found we create a new one
        $result_to_return = new $get_called_class($get_called_class);
        // Opt: Run the call back function to let the caller know we created a new record
        if ($callback != null) $callback($result_to_return);
        return $result_to_return;
    }

    /**
     * A final function for the Query Builder
     *   This function takes the chained functions and builds the query
     *   Note: We are not able to create a function that creates a new record if no results
     *   due to the fact this returns a dbCollection
     * @param string $select_fields
     * @return dbCollection
     */
    public function GET(string $select_fields = "*"): ?dbCollection
    {
        $sql = MySQL::getInstance();
        $get_called_class = get_called_class();
        $query = $this->QueryBuilderBuild($select_fields);
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

    /**
     * Get a Model to only do an update
     * @param $id
     * @return Model
     */
    public static function getForUpdateOnly($id): Model
    {
        $get_called_class = get_called_class();
        $result_to_return = new $get_called_class();
        $result_to_return->is_new = false;
        $result_to_return->is_dirty = true;
        $result_to_return->{$result_to_return->primary_key} = $id;
        return $result_to_return;
    }

    /**
     * @param $dest
     * @return dbCollection|null
     */
    public function GetChildren($dest): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = self::_table_name($dest);
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

    /**
     * @param $source
     * @return dbCollection|null
     */
    public static function GetBySource($source): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = self::_table_name(get_called_class());
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

    /**
     * @param array $where
     * @param string $type
     * @return dbCollection|null
     */
    public static function SimpleWhere(array $where, string $type = "AND"): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = self::_table_name(get_called_class());
        $sql = MySQL::getInstance();

        if (!is_array($where)) {
            $dwhere['id'] = $where;
        } else {
            $dwhere = $where;
        }
        foreach ($dwhere as $x) {
            if (count($x) == 2) {
                $value = $sql->real_escape_string($x[1]);
                $fields[] = "`$x[0]` = '$value'";
            }
            if (count($x) == 3) {
                $value = $sql->real_escape_string($x[2]);
                $fields[] = "`$x[0]` $x[1] '$value'";
            }
        }
        $field_list = join($type, $fields);

        $query = sprintf(/** @lang sql */ "SELECT * FROM `%s` WHERE %s", $table, $field_list);
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

    /**
     * Gets all records
     * @return dbCollection|null
     */
    public static function All(): ?dbCollection
    {
        $get_called_class = get_called_class();
        $table = self::_table_name($get_called_class);
        $sql = MySQL::getInstance();

        $query = sprintf(/** @lang sql */ "SELECT * FROM `%s`", $table);
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

    /**
     * Finds a record by primary key or optional $otherkey
     * @param $value
     * @param string|null $otherkey
     * @return Model
     */
    public static function find($value, string $otherkey = null): ?Model
    {
        $get_called_class = get_called_class();
        $result_to_return = new $get_called_class($get_called_class);
        $sql = MySQL::getInstance();

        $key = $otherkey ?: $result_to_return->primary_key;

        if (!is_numeric($value)) $value = "'" . $sql->real_escape_string($value) . "'";

        $query = sprintf(/** @lang sql */ "SELECT * FROM `%s` WHERE %s = %s", $result_to_return->_tablename, $key, $value);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            return null;
        }
        $row = $result->fetch_assoc();
        $result_to_return->fill($row);
        return $result_to_return;
    }

    /**
     * Finds a record by primary key or optional $otherkey
     *   If no record is found it will create on and call $callback
     * @param $value
     * @param callable|null $callback
     * @param string|null $otherkey
     * @return mixed|Model
     */
    public static function findOrNew($value, callable $callback = null, string $otherkey = null)
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
     * @param string $select_fields What fields to select
     * @param int|null $hardlimit opt: if defined will ignore limit in the chain and use this
     * @return string the mysql query
     */
    private function QueryBuilderBuild(string $select_fields = "*", int $hardlimit = null): string
    {
        $data = self::$where;
        $limit = $hardlimit ?: self::$limit;

        $sort = self::$orderby;
        $groupby = self::$groupby;
        $field_list = "";
        foreach ($data as $d) {
            if (is_array($d)) {
                $fields = [];
                foreach ($d['values'] as $v) {
                    if (count($v) == 2) {
                        $value = $v[1];
                        $fields[] = "`$v[0]` = '$value'";
                    }
                    if (count($v) == 3) {
                        $value = $v[2];
                        $fields[] = "`$v[0]` $v[1] '$value'";
                    }
                }
                if ($d['group']) $field_list .= "(";
                $field_list .= join(' ' . $d['type'] . ' ', $fields);
                if ($d['group']) $field_list .= ")";
            } else {
                $field_list .= " $d ";
            }
        }
        $field_list = trim($field_list);
        $field_list = rtrim($field_list, "AND");
        $field_list = rtrim($field_list, "OR");
        $field_list = trim($field_list);

        if ($field_list != "")
            $query = sprintf(/** @lang sql */ "SELECT %s FROM `%s` WHERE %s", $select_fields, $this->_tablename, $field_list);
        else
            $query = sprintf(/** @lang sql */ "SELECT %s FROM `%s`", $select_fields, $this->_tablename);

        if (count($groupby) > 0) $query .= " GROUP BY " . join(',', $groupby);
        if ($sort != "") $query .= " ORDER BY $sort";
        if ($limit != 0) $query .= " LIMIT $limit";

        self::$where = array();
        self::$orderby = "";
        self::$limit = 0;
        return $query;
    }

    /**
     * Commit to database
     * @param false $ignoreifdirty
     * @return Model|null
     */
    public function save(bool $ignoreifdirty = false): ?Model
    {
        if ($this->is_dirty == false && $ignoreifdirty == false) return null; // Nothing to save
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
        return $this;
    }

    /**
     * Deletes from the database
     */
    public function delete()
    {
        $sql = MySQL::getInstance();
        $value = is_numeric($this->data[$this->primary_key]) ? $this->data[$this->primary_key] : "'" . $sql->real_escape_string($this->data[$this->primary_key]) . "'";

        $query = sprintf(/** @lang MySQl */ "DELETE FROM `%s` WHERE `%s` = %s", $this->_tablename, $this->primary_key, $value);
        $sql->query($query);
        $this->is_deleted = true;
    }

    /**
     * Undo any changes since getting the record from the database
     */
    public function rollback()
    {
        $this->data = $this->orgdata;
        $this->is_dirty = false; // Since we're back to in-sync with the server's database we're not "dirty" anymore.
    }

    /**
     * Will get the most current record from the database
     * @return bool
     */
    public function refresh(): bool
    {
        $sql = MySQL::getInstance();

        $value = is_numeric($this->data[$this->primary_key]) ? $this->data[$this->primary_key] : "'" . $sql->real_escape_string($this->data[$this->primary_key]) . "'";

        $query = sprintf(/** @lang MySQL */ "SELECT * FROM `%s` WHERE %s = %s", $this->_tablename, $this->primary_key, $value);
        $result = $sql->query($query);

        if ($result->num_rows == 0) {
            return false;
        }
        $row = $result->fetch_assoc();
        $this->fill($row);
        $this->is_dirty = false;
        return true;
    }

    /**
     * @param bool $ignore_hidden_keys
     */
    public function ResolveForeignKeys(bool $ignore_hidden_keys = true)
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

    /**
     * @param bool $ignore_hidden_keys
     * @param $key
     * @param $class
     * @param string $fkcol
     * @return bool
     */
    public function ResolveForeignKey(bool $ignore_hidden_keys, $key, $class, string $fkcol = 'id'): Bool
    {
        $result_value = $this->data[$key];
        if (empty($result_value)) return false; // Don't resolve null.
        if ($ignore_hidden_keys && in_array($key, $this->hidden_keys)) {
            return false; // Don't resolve hidden keys, we'll lazy load them if read.
        }
        $data = SQLCache::GetFromCache($class, $result_value, function ($class, $key, $col) {
            return $class::find($key, $col);
        }, $fkcol);
        if ($data === null) {
            Debug::Warn("$key in $class failed ($result_value)");
            return false;
        }
        $data->ResolveForeignKeys();
        $this->ResolvedSet($key, $data);
        return true;
    }

    /**
     * @param bool $hide_db_time
     * @param bool $showempty
     * @return array
     */
    public function toArray(bool $hide_db_time, bool $showempty = false): array
    {
        return array_merge(
            array_filter($this->data, function ($v, $k) use ($hide_db_time, $showempty) {
                if (empty($v) && $showempty == false) return false;
                return !isset($this->foreign_keymap[$k]) && !$this->isHiddenKey($k, $hide_db_time);
            }, ARRAY_FILTER_USE_BOTH),
            $this->toArrayWith($this->resolved_data ?? array(), $hide_db_time, $showempty)
        );
    }

    /**
     * @param $array
     * @param bool $hide_db_time
     * @param bool $showempty
     * @return array
     */
    private function toArrayWith($array, bool $hide_db_time, bool $showempty = false): array
    {
        // Merging arrays while calling toArray() on each element
        // Better way to do this??
        $out = array();
        foreach ($array as $k => $v) $out[$k] = $v->toArray($hide_db_time, $showempty);
        return $out;
    }

    /**
     * @param $key
     * @param bool $hide_db_time
     * @return bool
     */
    public function isHiddenKey($key, bool $hide_db_time): bool
    {
        if ($hide_db_time && ($key == "created_at" || $key == "updated_at")) return true;
        return in_array($key, $this->hidden_keys);
    }

    // <editor-fold desc="Magic Functions">

    /**
     * @param $name
     * @param $value
     */
    public function ResolvedSet($name, $value)
    {  // not really magic but along with same lines as __set
        $this->resolved_data[$name] = $value;
    }

    /**
     * Magic set function
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (isset($this->data[$name]) && $this->data[$name] === $value) return; // Ignore set if it's the same value
        $this->data[$name] = $value;
        $this->is_dirty = true;
    }

    /**
     * Magic get function
     * @param $name
     * @return mixed|null
     */
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

    /**
     * Magic isset function
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic unset function
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * Magic toString
     * @return mixed
     */
    public function __toString()
    {
        if (isset($this->data[$this->primary_key])) return $this->data[$this->primary_key];
        throw new Exception(get_called_class() . " could not be converted to string");
    }

    /**
     * Magic debugInfo
     * @return array[]
     */
    public function __debugInfo()
    {
        return [
            $this->data
        ];
    }

    /**
     * Hydrates the Model
     * @param $args
     */
    private function Fill($args)
    {
        $this->data = $args;
        $this->orgdata = $args;
        $this->is_new = false;
    }
    // </editor-fold>
}

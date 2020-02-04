<?php

namespace Ibex\CrudGenerator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class ModelGenerator.
 */
class ModelGenerator
{
    private $functions = null;

    private $table = null;
    private $properties = null;
    private $modelNamespace = 'App';

    /**
     * ModelGenerator constructor.
     *
     * @param string $table
     * @param string $properties
     * @param string $modelNamespace
     */
    public function __construct(string $table, string $properties, string $modelNamespace)
    {
        $this->table = $table;
        $this->properties = $properties;
        $this->modelNamespace = $modelNamespace;
        $this->_init();
    }

    /**
     * Get all the eloquent relations.
     *
     * @return array
     */
    public function getEloquentRelations()
    {
        return [$this->functions, $this->properties];
    }

    private function _init()
    {
        foreach ($this->_getTableRelations() as $relation) {
            if (true or $relation->ref) {
//                $tableKeys = $this->_getTableKeys($relation->ref_table);
//                $eloquent = $this->_getEloquent($relation, $tableKeys);
            } else {
                $eloquent = 'hasOne';
            }

            $this->functions .= $this->_getFunction('hasOne', $relation->foreign_table_name, $relation->constraint_name, $relation->column_name);
        }
    }

    /**
     * @param $relation
     * @param $tableKeys
     *
     * @return string
     */
    private function _getEloquent($relation, $tableKeys)
    {
        $eloquent = '';
        foreach ($tableKeys as $tableKey) {
            if ($relation->foreign_key == $tableKey->Column_name) {
                $eloquent = 'hasMany';

                if ($tableKey->Key_name == 'PRIMARY') {
                    $eloquent = 'hasOne';
                } elseif ($tableKey->Non_unique == 0 && $tableKey->Seq_in_index == 1) {
                    $eloquent = 'hasOne';
                }
            }
        }

        return $eloquent;
    }

    /**
     * @param string $relation
     * @param string $table
     * @param string $foreign_key
     * @param string $local_key
     *
     * @return string
     */
    private function _getFunction(string $relation, string $table, string $foreign_key, string $local_key)
    {
        list($model, $relationName) = $this->_getModelName($table, $relation);
        $relClass = ucfirst($relation);

        switch ($relation) {
            case 'hasOne':
                $this->properties .= "\n * @property $model $$relationName";
                break;
            case 'hasMany':
                $this->properties .= "\n * @property ".$model."[] $$relationName";
                break;
        }

        return '
    /**
     * @return \Illuminate\Database\Eloquent\Relations\\'.$relClass.'
     */
    public function '.$relationName.'()
    {
        return $this->'.$relation.'(\''.$this->modelNamespace.'\\'.$model.'\', \''.$foreign_key.'\', \''.$local_key.'\');
    }
    ';
    }

    /**
     * Get the name relation and model.
     *
     * @param $name
     * @param $relation
     *
     * @return array
     */
    private function _getModelName($name, $relation)
    {
        $class = Str::studly(Str::singular($name));
        $relationName = '';

        switch ($relation) {
            case 'hasOne':
                $relationName = Str::camel(Str::singular($name));
                break;
            case 'hasMany':
                $relationName = Str::camel(Str::plural($name));
                break;
        }

        return [$class, $relationName];
    }

    /**
     * Get all relations from Table.
     *
     * @return array
     */
    private function _getTableRelations()
    {
        $db = DB::getDatabaseName();
        $sql = <<<SQL
SELECT 
    tc.table_schema as TABLE_NAME, 
    tc.constraint_name, 
    tc.table_name as ref_table, 
    kcu.column_name as COLUMN_NAME, 
    ccu.table_schema AS foreign_table_schema,
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS REFERENCED_COLUMN_NAME 
FROM 
    information_schema.table_constraints AS tc 
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
      ON ccu.constraint_name = tc.constraint_name
      AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name='$this->table';
SQL;
        return DB::select($sql);
    }

    /**
     * Get all Keys from table.
     *
     * @param $table
     *
     * @return array
     */
    private function _getTableKeys($table)
    {
        return DB::select("SHOW KEYS FROM {$table}");
    }
}

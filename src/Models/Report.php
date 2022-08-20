<?php

namespace Eightbitsnl\NovaReports\Models;

use Eightbitsnl\NovaReports\Traits\Reportable;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    use SoftDeletes;

    const OUTPUT_TYPE_CROSSJOIN = "CROSSJOIN";
    const OUTPUT_TYPE_FLAT = "FLAT";

    private $querybuilder;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ["entrypoint", "loadrelation", "query"];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    public $casts = [
        "export_fields" => "array",
        "query" => "array",
        "loadrelation" => "object",
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });

        static::retrieved(function ($model) {
            // $model->querybuilder = $model->getQuerybuilderInstance();
        });
    }

    public function getLoadrelationAttribute($v)
    {
        return is_null($v) ? [] : json_decode($v);
    }

    public function getExportDirAttribute()
    {
        return "reports/" . $this->id . "/";
    }

    public function getExportPathAttribute()
    {
        return $this->export_dir . "/" . date("YmdHis") . ".xlsx";
    }

    /**
     * All the fields that will be in the report
     *
     * @return \Illuminate\Support\Collection Collection of fieldsnames, in dot notation, eg: [base.id, base.created_at, relation_one.id, ...]
     */
    public function getFieldsToReport(): Collection
    {
        // these are the (unsorted) fields that are selected by the user
        $fields = collect($this->export_fields);

        // now want those fields sorted
        // start by finding all exportable fields
        return collect($this->entrypoint::getExportableFields())
            // map them to flattened array in dot notation
            ->map(function ($v, $k) {
                return collect($v["fields"])->map(function ($v) use ($k) {
                    return $k . "." . $v;
                });
            })
            ->flatten()
            // so we can compare them with the $fields the user selected
            ->filter(function ($i) use ($fields) {
                return $fields->contains($i);
            })
            // and return the results as as simple array
            ->values();
    }

    /** @todo move to \Eightbitsnl\NovaReports\Exports */
    public function getLatestExportFile()
    {
        $files = Storage::files($this->export_dir);
        if (count($files)) {
            return collect($files)->last();
        }

        return null;
    }

    /**
     * Mutator for `reportfields` used in
     * Eightbitsnl\NovaReports\Nova\Fields\QuerybuilderField
     *
     * @param string $value
     * @return void
     */
    public function setReportfieldsAttribute($value): void
    {
        $data = json_decode($value, true);

        $this->attributes["entrypoint"] = $data["entrypoint"] ?: null;
        $this->loadrelation = $data["loadrelation"] ?: null;
        $this->query = $data["query"] ?: null;
        $this->export_fields = $data["export_fields"] ?: null;
    }

    /**
     * Accessor for `reportfields` used in
     * Eightbitsnl\NovaReports\Nova\Fields\QuerybuilderField
     *
     * @return array
     */
    public function getReportfieldsAttribute(): array
    {
        return [
            "entrypoint" => $this->entrypoint,
            "loadrelation" => $this->loadrelation,
            "query" => $this->query,
            "export_fields" => $this->export_fields ?: [],
        ];
    }

    /**
     * Get a collection of all Classes that are Reportable
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getReportables(): Collection
    {
        return collect(glob(app_path() . "/*.php"))
            ->map(function ($path) {
                return "App\\" . substr(class_basename($path), 0, -strlen(".php"));
            })
            ->filter(function ($classname) {
                return collect(class_uses_recursive($classname))->has(Reportable::class);
            })
            ->values();
    }

    /**
     * Get a (scoped) list of operators
     *
     * @param string $scope
     * @return \Illuminate\Support\Collection
     */
    public static function getOperators($scope = "*"): Collection
    {
        return collect([
            "equals" => ["*", "select"],
            "does not equal" => ["*", "select"],
            "contains" => ["text"],
            "does not contain" => ["text"],
            "is empty" => ["*"],
            "is not empty" => ["*"],
            "begins with" => ["text"],
            "ends with" => ["text"],
            "date equals" => ["date"],
            "date does not equal" => ["date"],
            "before" => ["date"],
            "before or equal" => ["date"],
            "after" => ["date"],
            "after or equal" => ["date"],

            "scope" => ["scope"],
        ])
            ->filter(function ($o) use ($scope) {
                return array_intersect([$scope], $o);
            })
            ->keys();
    }

    /**
     * Preview a Visual Query Builder
     *
     * @return array
     */
    public function preview(): array
    {
        // $all = $this->querybuilder->get();

        return [
            // 'query' => $this->getRawSql(),
            "count" => $this->getCount(),
            "first" => $this->getQuerybuilderInstance()->first(),
            // 'all' => $all
        ];
    }

    /**
     * The number of rows for the Query Builder Instance
     *
     * @return integer
     */
    public function getCount(): int
    {
        return $this->getQuerybuilderInstance()->count();
    }

    /**
     * Get the Query Builder Instance
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQueryBuilderInstance(): Builder
    {
        $q = is_string($this->query) ? json_decode($this->query, true) : $this->query;

        $group = [];
        $group["query"] = ["children" => $q["children"]];
        $group["query"]["logicalOperator"] = $q["logicalOperator"];
        $method = $q["logicalOperator"] === "All" ? "where" : "orWhere";

        $this->querybuilder = $this->entrypoint::with($this->loadrelation);

        $this->parseQBGroup($this->querybuilder, $group, $method);

        return $this->querybuilder;
    }

    /**
     * Get the Raw SQL query
     *
     * @return string
     */
    private function getRawSql(): string
    {
        $this->querybuilder = $this->getQuerybuilderInstance();
        return vsprintf(str_replace(["?"], ['\'%s\''], $this->querybuilder->toSql()), $this->querybuilder->getBindings());
    }

    /**
     * Parse a Visual Query Builder Group
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $group
     * @param string $method
     * @return void
     */
    protected function parseQBGroup(Builder $query, array $group, $method = "where")
    {
        $query->{$method}(function ($subquery) use ($group) {
            // $sub_method = $group['query']['logicalOperator'] === 'All' ? 'where' : 'orWhere';
            $logicalOperator = strtolower($group["query"]["logicalOperator"]) == "all" ? "AND" : "OR";

            foreach ($group["query"]["children"] as $child) {
                if ($child["type"] === "query-builder-group") {
                    $this->parseQBGroup($subquery, $child, $logicalOperator == "AND" ? "where" : "orWhere");
                } else {
                    $this->parseQBRule($subquery, $child, $logicalOperator);
                }
            }
        });
    }

    /**
     * Parse a single Visual Query Builder Rule
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param [type] $rule
     * @param [type] $logicalOperator
     * @return void
     */
    protected function parseQBRule(&$query, $rule, $logicalOperator = null)
    {
        $method = $logicalOperator == "AND" ? "where" : "orWhere";
        $value = $rule["query"]["value"];

        // parse
        if (Str::startsWith($value, "{{") && Str::endsWith($value, "}}")) {
            $value = $this->parseValue(Str::replaceFirst("{{", "", Str::replaceLast("}}", "", $value)));
        }

        switch ($rule["query"]["operator"]) {
            default:
            case "equals":
                $operator = "=";
                break;
            case "does not equal":
                $operator = "<>";
                break;
            case "contains":
                $operator = "LIKE";
                $value = "%" . $value . "%";
                break;
            case "does not contain":
                $operator = "NOT LIKE";
                $value = "%" . $value . "%";
                break;
            case "is empty":
                $method = $logicalOperator == "AND" ? "whereNull" : "orWhereNull";
                $operator = null;
                $value = null;
                break;
            case "is not empty":
                $method = $logicalOperator == "AND" ? "whereNotNull" : "orWhereNotNull";
                $operator = null;
                $value = null;
                break;
            case "begins with":
                $operator = "LIKE";
                $value = $value . "%";
                break;
            case "ends with":
                $operator = "LIKE";
                $value = "%" . $value;
                break;

            case "date equals":
                $method = $logicalOperator == "AND" ? "whereDate" : "orWhereDate";
                $operator = "=";
                break;
            case "date does not equal":
                $method = $logicalOperator == "AND" ? "whereDate" : "orWhereDate";
                $operator = "<>";
                break;
            case "date before":
            case "before":
                $method = $logicalOperator == "AND" ? "whereDate" : "orWhereDate";
                $operator = "<";
                break;
            case "date before or equal":
            case "before or equal":
                $method = $logicalOperator == "AND" ? "whereDate" : "orWhereDate";
                $operator = "<=";
                break;
            case "date after":
            case "after":
                $method = $logicalOperator == "AND" ? "whereDate" : "orWhereDate";
                $operator = ">";
                break;
            case "date after or equal":
            case "after or equal":
                $method = $logicalOperator == "AND" ? "whereDate" : "orWhereDate";
                $operator = ">=";
                break;
            case "scope":
                switch ($logicalOperator) {
                    default:
                    case "AND":
                        return $query->{$rule["query"]["rule"]}($value);
                        break;
                    case "OR":
                        return $query->orWhere(function ($query) use ($rule, $value) {
                            $query->{$rule["query"]["rule"]}($value);
                        });
                        break;
                }

                break;
        }

        $query->{$method}($rule["query"]["rule"], $operator, $value);
    }

    /**
     * Parse placeholder values
     *
     * @param [type] $value
     * @return void
     */
    public function parseValue($value)
    {
        switch ($value) {
            default:
                return $value;
                break;

            case "NOW":
                return now();
                break;

            case "START_OF_DAY":
            case "START_OF_MONTH":
            case "START_OF_QUARTER":
            case "START_OF_YEAR":
            case "START_OF_DECADE":
            case "START_OF_CENTURY":
            case "START_OF_MILLENNIUM":
            case "START_OF_WEEK":
            case "START_OF_HOUR":
            case "START_OF_MINUTE":
            case "START_OF_SECOND":
            case "END_OF_DAY":
            case "END_OF_MONTH":
            case "END_OF_QUARTER":
            case "END_OF_YEAR":
            case "END_OF_DECADE":
            case "END_OF_CENTURY":
            case "END_OF_MILLENNIUM":
            case "END_OF_WEEK":
            case "END_OF_HOUR":
            case "END_OF_MINUTE":
            case "END_OF_SECOND":
                return now()->{lcfirst(Str::studly(strtolower($value)))}();
                break;

            case preg_match("/ADD_[0-9]+_DAY/", $value):
            case preg_match("/ADD_[0-9]+_MONTH/", $value):
            case preg_match("/SUB_[0-9]+_DAY/", $value):
            case preg_match("/SUB_[0-9]+_MONTH/", $value):
                list($do, $val, $unit) = explode("_", $value);

                $do = lcfirst(Str::studly(strtolower($do)));
                $unit = Str::studly(strtolower($unit));

                return now()
                    ->startOfDay()
                    ->{$do . $unit}($val);

                break;
        }
    }

    /**
     * Execute the reports query as a "select" statement.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getModels()
    {
        return $this->getQuerybuilderInstance()
            ->with($this->loadrelation)
            ->get();
    }

    /**
     * Retrieve the "count" result of the reports query.
     *
     * @param  string  $columns
     * @return int
     */
    public function getModelCount()
    {
        return $this->getQueryBuilderInstance()->count();
    }

    /**
     * Get the Rows
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRows(): Collection
    {
        return $this->getModels()->reduce(function ($result, $model) {
            $rows = $this->getRowsForModel($model);
            return $result->concat($rows);
        }, collect());
    }

    /**
     * Get a Collection for rows for a single model
     *
     * @param Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\Collection Collection of rows
     */
    public function getRowsForModel($model)
    {
        switch ($this->grouping_option) {
            default:
            case static::OUTPUT_TYPE_CROSSJOIN:
                return $this->getRowsForModelCrossjoined($model);
                break;

            case static::OUTPUT_TYPE_FLAT:
                return $this->getRowsForModelFlat($model);
                break;
        }
    }

    /**
     * Get fields to report for the Entrypoint
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFieldsToReportForEntryPoint(): Collection
    {
        if (!isset($this->fields_to_report_for_entry_point)) {
            $this->fields_to_report_for_entry_point = $this->getFieldsToReport()->filter(function ($fieldname) {
                list($group, $field) = explode(".", $fieldname);
                return $group == strtolower(class_basename($this->entrypoint));
            });
        }

        return $this->fields_to_report_for_entry_point;
    }

    /**
     * Get fields to report for the Relations
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFieldsToReportForRelations(): Collection
    {
        if (!isset($this->fields_to_report_for_relations)) {
            $this->fields_to_report_for_relations = $this->getFieldsToReport()->diff($this->getFieldsToReportForEntryPoint());
        }

        return $this->fields_to_report_for_relations;
    }

    /**
     * Get fields to report for the relations, as an associative array
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFieldsToReportForRelationsAssoc(): Collection
    {
        if (!isset($this->fields_to_report_for_relations_assoc)) {
            $this->fields_to_report_for_relations_assoc = collect(
                $this->getFieldsToReportForRelations()->reduce(function ($result, $fieldname) {
                    list($relation, $attr) = explode(".", $fieldname);

                    if (!array_key_exists($relation, $result)) {
                        $result[$relation] = [];
                    }

                    $result[$relation][] = $attr;

                    return $result;
                }, [])
            );
        }

        return $this->fields_to_report_for_relations_assoc;
    }

    /**
     * Get a hydrated data collection
     *
     * @param Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\Collection Collection of data
     */
    protected function getHydratedDataCollection($model): Collection
    {
        $fields_entrypoint = $this->getFieldsToReportForEntryPoint();
        $fields_relationshops = $this->getFieldsToReportForRelationsAssoc();

        // start with a simple array of only the fields for the given model
        $base = $model->only(
            $fields_entrypoint
                ->map(function ($fieldname) {
                    return explode(".", $fieldname)[1];
                })
                ->toArray()
        );

        // now prefix the model classname the field:
        // `{attr}` will become `{classname}.{attr}'
        $base = collect($base)->mapWithKeys(function ($v, $k) use ($model) {
            return [strtolower(class_basename($model)) . "." . $k => $v];
        });

        // fill $rel_data relations
        $rel_data = $fields_relationshops
            ->map(function ($relation_fields, $relation_name) use ($model) {
                $related = $model->$relation_name;
                $related_rows = null;

                if (is_a($related, \Illuminate\Database\Eloquent\Collection::class)) {
                    if ($related->count() > 0) {
                        $related_rows = $related->map(function ($rel) use ($relation_fields) {
                            return collect($rel->only($relation_fields));
                        });
                    }
                } else {
                    if (!is_null($related)) {
                        $related_rows = collect([$related->only($relation_fields)]);
                    }
                }

                if (is_null($related_rows)) {
                    return collect([array_fill_keys($relation_fields, null)]);
                }

                return $related_rows->map(function ($row) use ($relation_name) {
                    return collect($row)->mapWithKeys(function ($v, $k) use ($relation_name) {
                        return [$relation_name . "." . $k => $v];
                    });
                });
            })
            ->toArray();

        return $base->merge($rel_data);
    }

    /**
     * Undocumented function
     *
     * @param [type] $model
     * @return void
     */
    protected function getRowsForModelFlat($model)
    {
        $hydrated = $this->getHydratedDataCollection($model);

        return collect([
            collect($hydrated)->mapWithKeys(function ($value, $key) {
                if (is_array($value)) {
                    $value = implode(
                        PHP_EOL . PHP_EOL,
                        collect($value)
                            ->map(function ($v, $relation_index) use ($key) {
                                return "#" .
                                    ($relation_index + 1) .
                                    " " .
                                    $key .
                                    PHP_EOL .
                                    implode(
                                        PHP_EOL,
                                        collect($v)
                                            ->map(function ($v, $k) {
                                                return Str::after($k, ".") . ": " . $v;
                                            })
                                            ->toArray()
                                    );
                            })
                            ->toArray()
                    );
                }

                return [$key => $value];
            }),
        ]);
    }

    protected function getRowsForModelCrossjoined($model)
    {
        $hydrated = $this->getHydratedDataCollection($model);

        $fields_for_entry_point = $hydrated->only($this->getFieldsToReportForEntryPoint());
        $fields_for_relations = $hydrated->except($this->getFieldsToReportForEntryPoint())->toArray();

        // crossjoin the base row data , with all relations data
        $crossjoined_rows = call_user_func_array([collect([$fields_for_entry_point]), "crossJoin"], $fields_for_relations);

        $rows = $crossjoined_rows->map(function ($r) {
            $res = [];
            foreach ($r as $i) {
                foreach ($i as $k => $v) {
                    $res[$k] = $v;
                }
            }

            return $res;
        });

        return $rows;
    }
}

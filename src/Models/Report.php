<?php

namespace Eightbitsnl\NovaReports\Models;

use Eightbitsnl\NovaReports\Traits\Reportable;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Report extends Model
{
    use SoftDeletes;

    const OUTPUT_TYPE_CROSSJOIN = "CROSSJOIN";
    const OUTPUT_TYPE_CROSSJOIN_DEEP = "CROSSJOIN_DEEP"; // @todo ?
    const OUTPUT_TYPE_FLAT = "FLAT";

    private $querybuilder;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ["entrypoint", "loadrelation", "query", "export_fields"];

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

    public function getDownloadUrl()
    {
        return route('nova-reports.download', ['report' => $this->uuid]);
    }

    public function getDownloadUrlSigned(string $path)
    {
        $encrypted = Crypt::encryptString(json_encode([
            'u' => optional(auth()->user())->id,
            'p' => $path
        ]));

        $url = URL::temporarySignedRoute(
            'nova-reports.download_signed',
            now()->addMinutes(config('nova-reports.keep_exports_for_minutes')),
            ['encrypted' => $encrypted]
        );

        return $url;
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
            ->map(function ($v, $group) {
                return collect($v["fields"])->map(function ($v, $k) use ($group) {
                    return $group . "." . $k;
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
        $files = Storage::disk(config('nova-reports.filesystem'))->files($this->export_dir);
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
        return []; //
        // return [
        //     "entrypoint" => $this->entrypoint,
        //     "loadrelation" => $this->loadrelation,
        //     "query" => $this->query ?: [
        //         "logicalOperator" => "any",
        //         "children" => [],
        //     ],
        //     "export_fields" => $this->export_fields ?: [],
        // ];
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
     * Get a collection of all Classes that are Entrypoints
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getEntrypoints(): Collection
    {
        return static::getReportables()
            ->filter(function ($classname) {
                return !empty((new $classname())->getReportRules());
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
     * Web Preview a Visual Query Builder
     *
     */
    public function webpreview()
    {
        $firstModel = $this->getModelsQuery()->first();

        $rows = $firstModel ? $this->getRowsForModel($firstModel) : new Collection([]);

        return [
            "count" => $this->getCount(),
            "headings" => $this->getHeadings(),
            "items" => $rows
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
        return $this->getModelsQuery()->count();
    }

    /**
     * Get the Query Builder Instance
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQueryBuilderInstance(): Builder
    {
        return config('nova-reports.querybuilder_class')::forReport($this);
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
     * Instantiate the reports query as a "select" statement
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getModelsQuery(): Builder
    {
        return $this->getQuerybuilderInstance()
            ->with($this->loadrelation);
    }

    /**
     * Execute the reports query as a "select" statement.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getModels()
    {
        return $this->getModelsQuery()
            ->get();
    }

    /**
     * Get the Headings
     *
     * @return \Illuminate\Support\Collection
     */
    public function getHeadings(): Collection
    {
        $exportable_fields = (new $this->entrypoint())::getExportableFields(true);

        return $this->getFieldsToReport()->mapWithKeys(function ($field) use ($exportable_fields) {
            list($group, $field_key) = explode(".", $field);
            return [$field => $group . PHP_EOL . $exportable_fields[$group]["fields"][$field_key]];
        });
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
    public function getRowsForModel(Model $model)
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
        $fields_relationships = $this->getFieldsToReportForRelationsAssoc();

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
        $rel_data = $fields_relationships
            ->map(function ($relation_fields, $relation_name) use ($model) {
                $related = $model->$relation_name;
                $related_rows = null;

                if ($related instanceof \Illuminate\Support\Collection) {
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
     * Get a Collection for rows for a single model, flat
     *
     * @param Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\Collection Collection of rows
     */
    protected function getRowsForModelFlat(Model $model): Collection
    {
        $hydrated = $this->getHydratedDataCollection($model);

        return collect([
            collect($hydrated)->mapWithKeys(function ($value, $key) {
                if (is_iterable($value)) {
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

    /**
     * Get a Collection for rows for a single model, crossjoined
     *
     * @param Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\Collection Collection of rows
     */
    protected function getRowsForModelCrossjoined(Model $model): Collection
    {
        $hydrated = $this->getHydratedDataCollection($model);

        $fields_for_entry_point = $hydrated->only($this->getFieldsToReportForEntryPoint());
        $fields_for_relations = $hydrated->except($this->getFieldsToReportForEntryPoint());

        $crossjoined_rows = call_user_func_array([collect([$fields_for_entry_point]), "crossJoin"], $fields_for_relations->toArray());

        $rows = $crossjoined_rows->map(function ($r) {
            $res = [];
            foreach ($r as $i) {
                foreach ($i as $k => $v) {
                    $res[$k] = $v;
                }
            }

            return $res;
        });

        // @todo
        if ($this->grouping_option == static::OUTPUT_TYPE_CROSSJOIN_DEEP) {
            $rows = collect($rows)
                ->map(function ($row) {
                    $result = [];
                    static::iterable_field_to_rows($row, $result);
                    return $result;
                })
                ->flatten(1);
        }

        return $rows;
    }

    private static function row_first_iterable($row)
    {
        foreach ($row as $k => $v) {
            if (is_iterable($v)) {
                return $k;
            }
        }
        return null;
    }

    private static function iterable_field_to_rows($row, &$result)
    {
        $iterable_field = static::row_first_iterable($row);
        if (is_null($iterable_field)) {
            $result = array_merge($result, [$row]);
            return $row;
        }

        $other_fields = collect($row)->except($iterable_field);

        $rows = [];

        $values = collect($row)->get($iterable_field);
        foreach ($values as $value) {
            $rows[] = $other_fields->merge([$iterable_field => $value])->toArray();
        }

        return array_map(function ($i) use (&$result) {
            return static::iterable_field_to_rows($i, $result);
        }, $rows);
    }
}

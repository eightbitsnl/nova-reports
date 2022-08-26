<?php

namespace Eightbitsnl\NovaReports\Traits;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Relations\Relation;

trait Reportable
{
    // abstract
    abstract public function getReportRules();

    /**
     * The Reportable fields
     *
     * @return array
     */
    public static function getReportableFields() : array
    {
        return (new ReflectionClass(static::class))->getStaticPropertyValue("reportable");
    }

    public static function withRelations($relations = null)
    {
        return empty($relations) ? parent::query() : ($relations === true ? parent::with(static::getReportableRelations()) : parent::with($relations));
    }

    /**
     * Gets list of available relations for this model
     *
     * @return array
     */
    public static function getReportableRelations()
    {
        return collect((new ReflectionClass(static::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            // filter methods that return a Relation
            ->filter(function ($method) {
                return ($type = $method->getReturnType()) && is_subclass_of($type->getName(), Relation::class);
            })
            // map to relation name
            ->map(function ($method) {
                return $method->getName();
            })
            // filter only relations that have a $reportable property
            ->filter(function ($method) {
                $class = (new static())->$method()->getRelated();
                return (new ReflectionClass($class))->hasProperty("reportable");
            })
            ->values()
            ->toArray();
    }

    public static function getReportableRelationsWithFields()
    {
        $relations = collect(static::getReportableRelations());

        return collect($relations)
            ->mapWithKeys(function ($relation) {
                $class = (new static())->$relation()->getRelated();
                $fields = static::getReportableFieldsAssoc($class::getReportableFields());

                return [$relation => $fields];
            })
            ->toArray();
    }

    private static function getReportableFieldsAssoc(array $fields)
    {
        // if $fields is alread an associative array, just return
        if(array_keys($fields) !== range(0, count($fields) - 1))
            return $fields;

        // make $fields assosiative
        return collect($fields)->mapWithKeys(function($value){
            return [$value => $value];
        })->toArray();
    }

    public static function getExportableFields($includeRelations = true)
    {
        $result = [
            strtolower(class_basename(static::class)) => [
                "type" => "main",
                "fields" => static::getReportableFields()
            ],
        ];

        if ($includeRelations) {
            $result = array_merge(
                $result,
                collect(static::getReportableRelationsWithFields())
                    ->mapWithKeys(function ($v, $k) {
                        return [
                            $k => [
                                "type" => "relation",
                                "fields" => $v,
                            ],
                        ];
                    })
                    ->toArray()
            );
        }

        return $result;
    }
}

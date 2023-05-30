<?php

namespace Eightbitsnl\NovaReports\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ReportQueryBuilder extends Builder
{
    public static function forReport(Report $report)
    {
        $q = is_string($report->query) ? json_decode($report->query, true) : $report->query;

        $group = [];
        $group["query"] = ["children" => $q["children"]];
        $group["query"]["logicalOperator"] = $q["logicalOperator"];
        $method = $q["logicalOperator"] === "All" ? "where" : "orWhere";

        return self::parseQBGroup($report->entrypoint::query(), $group, $method)->with($report->loadrelation);
    }

    /**
     * Parse a Visual Query Builder Group
     *
     * @param Builder $query
     * @param array $group
     * @param string $method
     * @return Builder $query
     */
    protected static function parseQBGroup(Builder $query, array $group, $method = "where")
    {
        return $query->{$method}(function ($subquery) use ($group) {
            // $sub_method = $group['query']['logicalOperator'] === 'All' ? 'where' : 'orWhere';
            $logicalOperator = strtolower($group["query"]["logicalOperator"]) == "all" ? "AND" : "OR";

            foreach ($group["query"]["children"] as $child) {
                if ($child["type"] === "query-builder-group") {
                    self::parseQBGroup($subquery, $child, $logicalOperator == "AND" ? "where" : "orWhere");
                } else {
                    self::parseQBRule($subquery, $child, $logicalOperator);
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
    protected static function parseQBRule(&$query, $rule, $logicalOperator = null)
    {
        $method = $logicalOperator == "AND" ? "where" : "orWhere";
        $value = $rule["query"]["value"];

        // parse
        if (Str::startsWith($value, "{{") && Str::endsWith($value, "}}")) {
            $value = self::parseValue(Str::replaceFirst("{{", "", Str::replaceLast("}}", "", $value)));
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
    protected static function parseValue($value)
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
}

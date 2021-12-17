<?php

namespace Eightbitsnl\NovaReports\Models;

use Eightbitsnl\NovaReports\Traits\Reportable;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
	use SoftDeletes;

	private $querybuilder;

	protected $fillable = [
		'entrypoint',
		'loadrelation',
		'query',
	];

	public $casts = [
        'export_fields' => 'array',
        'query' => 'array',
        'loadrelation' => 'object',
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
			
			if( empty($model->uuid))
				$model->uuid = Str::uuid();
			
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
		return 'reports/'.$this->id.'/';
	}

	public function getExportPathAttribute()
	{
		return $this->export_dir.'/'.date('YmdHis').'.xlsx';
	}

	public function getReportfieldsAttribute()
	{
		return [
			'entrypoint' => $this->entrypoint,
			'loadrelation' => $this->loadrelation,
			'query' => $this->query,
			'export_fields' => $this->export_fields ?: []
		];
	}

	public function getReportableFields()
	{
		return collect($this->loadrelation)->reduce(function($result, $relation){

			$class = (new $this->entrypoint)->$relation()->getRelated();
			$fields = collect($class::$reportable)->map(function($field) use($relation){
				return $relation.'.'.$field;
			});

			return $result->concat($fields);

		}, collect($this->entrypoint::$reportable))->toArray();
	}

	public function getFieldsToReport()
	{
		// these are the (unsorted) fields that are selected by the user
		$fields = collect($this->export_fields);

		// now want those fields sorted
		// start by finding all exportable fields
		return collect($this->entrypoint::getExportableFields())

			// map them to flattened array in dot notation
			->map(function($v, $k){
				return collect($v['fields'])->map(function($v) use ($k){
					return $k.'.'.$v;
				});				
			})->flatten()
			// so we can compare them with the $fields the user selected
			->filter(function($i) use ($fields){
				return $fields->contains($i);
			})
			// and return the results as as simple array
			->values()
			->toArray();
	}

	public function getLatestExportFile()
	{
		$files = Storage::files($this->export_dir);
		if( count($files) )
			return collect($files)->last();

		return null;
	}

	public function setReportfieldsAttribute($value)
	{
		$data = json_decode($value,true);

		$this->attributes['entrypoint'] = $data['entrypoint'] ?: null;
		$this->loadrelation = $data['loadrelation'] ?: null;
		$this->query = $data['query'] ?: null;
		$this->export_fields = $data['export_fields'] ?: null;
	}

	/**
	 * Get a list of all Classes that are Reportable
	 *
	 * @return array
	 */
	public static function getReportables()
	{
		return collect(glob(app_path().'/*.php'))
  			->map(function($path){
    			return 'App\\'. substr( class_basename($path), 0, -strlen('.php'));
  			})
  			->filter(function($classname){
    			return collect(class_uses_recursive($classname))->has(Reportable::class);
			  })
  			->values();
	}

	/**
	 * Get a (scoped) list of operators
	 *
	 * @param string $scope
	 * @return array
	 */
	public static function getOperators($scope = '*')
	{

		return collect([
			'equals' => ['*', 'select'],
			'does not equal' => ['*', 'select'],
			'contains' => ['text'],
			'does not contain' => ['text'],
			'is empty' => ['*'],
			'is not empty' => ['*'],
			'begins with' => ['text'],
			'ends with' => ['text'],
			'before' => ['date'],
			'before or equal' => ['date'],
			'after' => ['date'],
			'after or equal' => ['date'],

			'scope' => ['scope'],
		  ])
			->filter(function($o) use ($scope){
				return array_intersect([$scope], $o);
			})
			->keys();

	}
	
	/**
	 * Preview a Visual Query Builder
	 *
	 * @return array
	 */
	public function preview()
	{
		$this->querybuilder = $this->getQuerybuilderInstance();

		// $all = $this->querybuilder->get();

		return [
			// 'query' => $this->getRawSql(),
			'count' => $this->querybuilder->count(),
			'first' => $this->querybuilder->first(),
			// 'all' => $all
		];
	}

	public function getQueryBuilderInstance()
	{
		// dd($this->loadrelation);
		$q =  is_string($this->query) ? json_decode($this->query,true) : $this->query;
		
		$group = [];
		$group['query'] = ['children' => $q['children']];
		$group['query']['logicalOperator'] = $q['logicalOperator'];
		$method = $q['logicalOperator'] === 'All' ? 'where' : 'orWhere';

		$this->querybuilder = $this->entrypoint::with($this->loadrelation);

		$this->parseQBGroup($this->querybuilder, $group, $method);


		return $this->querybuilder;
	}
	/**

	 * Get the Raw SQL query
	 */
	public function getRawSql()
	{
		$this->querybuilder = $this->getQuerybuilderInstance();
		return vsprintf(str_replace(array('?'), array('\'%s\''), $this->querybuilder->toSql()), $this->querybuilder->getBindings());
	}

	/**
	 * Parse a Visual Query Builder Group
	 *
	 * @param [type] $query
	 * @param [type] $group
	 * @param string $method
	 * @return void
	 */
	protected function parseQBGroup($query, $group, $method = 'where')
	{
		$query->{$method}(function ($subquery) use ($group) {

			// $sub_method = $group['query']['logicalOperator'] === 'All' ? 'where' : 'orWhere';
			$logicalOperator = strtolower($group['query']['logicalOperator']) == 'all' ? 'AND' : 'OR';
			
			foreach ($group['query']['children'] as $child) {
				if ($child['type'] === 'query-builder-group') {
					$this->parseQBGroup($subquery, $child, ($logicalOperator=='AND'?'where':'orWhere'));
				} else {
					$this->parseQBRule($subquery, $child, $logicalOperator);
				}
			}
		});

	}

	/**
	 * Parse a single Visual Query Builder Rule
	 *
	 * @param [type] $query
	 * @param [type] $rule
	 * @param [type] $logicalOperator
	 * @return void
	 */
	protected function parseQBRule(&$query, $rule, $logicalOperator = null)
	{
		
		$method = ($logicalOperator == 'AND') ? 'where' : 'orWhere';
		$value = $rule['query']['value'];

		// parse
		if(  Str::startsWith($value, '{{') && Str::endsWith($value,'}}') ){
			$value = $this->parseValue(Str::replaceFirst('{{', '', Str::replaceLast('}}', '', $value)));
		}

		switch($rule['query']['operator'])
		{
			default:
			case 'equals':
				 $operator = '=';
			break;
			case 'does not equal':
				$operator = '<>';
			break;
			case 'contains':
				$operator = 'LIKE';
				$value = '%'.$value.'%';
			break;
			case 'does not contain':
				$operator = 'NOT LIKE';
				$value = '%'.$value.'%';
			break;
			case 'is empty':
				$method = ($logicalOperator == 'AND') ? 'whereNull' : 'orWhereNull';
				$operator = null;
				$value = null;
			break;
			case 'is not empty':
				$method = ($logicalOperator == 'AND') ? 'whereNotNull' : 'orWhereNotNull';
				$operator = null;
				$value = null;
			break;
			case 'begins with':
				$operator = 'LIKE';
				$value = $value.'%';
			break;
			case 'ends with':
				$operator = 'LIKE';
				$value = '%'.$value;
			break;

			case 'before':
				$operator = '<';
			break;
			case 'before or equal':
				$operator = '<=';
			break;
			case 'after':
				$operator = '>';
			break;
			case 'after or equal':
				$operator = '>=';
			break;
			case 'scope':

				switch($logicalOperator)
				{
					default:
					case 'AND':
						return $query->{$rule['query']['rule']}($value);
					break;
					case 'OR':
						return $query->orWhere(function($query) use ($rule, $value){
							$query->{$rule['query']['rule']}($value);
						});
					break;
				}
				
			break;
		}

		$query->{$method}($rule['query']['rule'], $operator, $value);
	}

	/**
	 * Parse placeholder values
	 *
	 * @param [type] $value
	 * @return void
	 */
	public function parseValue($value)
	{
		switch($value)
		{
			default:
				return $value;
			break;

			case 'NOW':
				return now();
			break;
			
			case 'START_OF_DAY':
			case 'START_OF_MONTH':
			case 'START_OF_QUARTER':
			case 'START_OF_YEAR':
			case 'START_OF_DECADE':
			case 'START_OF_CENTURY':
			case 'START_OF_MILLENNIUM':
			case 'START_OF_WEEK':
			case 'START_OF_HOUR':
			case 'START_OF_MINUTE':
			case 'START_OF_SECOND':
			case 'END_OF_DAY':
			case 'END_OF_MONTH':
			case 'END_OF_QUARTER':
			case 'END_OF_YEAR':
			case 'END_OF_DECADE':
			case 'END_OF_CENTURY':
			case 'END_OF_MILLENNIUM':
			case 'END_OF_WEEK':
			case 'END_OF_HOUR':
			case 'END_OF_MINUTE':
			case 'END_OF_SECOND':
				return now()->{lcfirst(Str::studly(strtolower($value)) )}();
			break;

			case preg_match('/ADD_[0-9]+_DAY/', $value):
			case preg_match('/ADD_[0-9]+_MONTH/', $value):
			case preg_match('/SUB_[0-9]+_DAY/', $value):
			case preg_match('/SUB_[0-9]+_MONTH/', $value):
				
				list($do, $val, $unit) = explode('_', $value);
				
				$do = lcfirst(Str::studly(strtolower($do)));
				$unit = Str::studly(strtolower($unit));
			
				return now()->startOfDay()->{$do.$unit}($val);
				
			break;
			
		}
	}


	public function getResults()
	{
		$models = $this->getQuerybuilderInstance()->with($this->loadrelation)->get();

		return $models->reduce(function($result, $model){

			// all the fields that will be reported
			$fieldnames = collect($this->getFieldsToReport());

			// find the attributes
			$attributes = $fieldnames->filter(function($fieldname) use ($model){
				list($group, $field) = explode('.',$fieldname);
				return ($group == strtolower(class_basename($model)));
			});

			// relations
			$relations = $fieldnames->diff($attributes);

			$relations_assoc = collect($relations->reduce(function($result, $fieldname){
				list($relation, $attr) = explode('.', $fieldname);

				if( !array_key_exists($relation, $result) )
					$result[$relation] = [];

				$result[$relation][] = $attr;

				return $result;

			}, []));


			// start with a simple array of only the attributes for the given model
			$base = $model->only($attributes->map(function($fieldname){
						return explode('.', $fieldname)[1];
					})->toArray());

			// now add the model classname as a prefix to the attributes:
			// `{attr}` will become `{classname}.{attr}'
			$base = collect($base)->mapWithKeys(function($v, $k) use ($model){
				return [strtolower(class_basename($model)).'.'.$k => $v];
			});

			// fill $rel_data relations
			$rel_data = $relations_assoc->map(function($relation_fields, $relation_name) use ($model){
			
				$related = $model->$relation_name;
				$related_rows = null;
				
				// dump( get_class($related), is_a($related, Illuminate\Database\Eloquent\Collection::class) );
				if( is_a($related, \Illuminate\Database\Eloquent\Collection::class))
				{
					if($related->count() > 0)
					{
						$related_rows = $related->map(function($rel) use ($relation_fields){
							return collect($rel->only($relation_fields));
						});
					}
				}

				else
				{
					if(!is_null($related))
						$related_rows = collect([$related->only($relation_fields)]); 
				}

				if(is_null($related_rows))
					$related_rows = collect([array_fill_keys($relation_fields, null)]);

				return $related_rows->map(function($row) use ($relation_name){
					return collect($row)->mapWithKeys(function($v, $k) use ($relation_name){
							return [$relation_name.'.'.$k=>$v];
					});
				});

			})->toArray();
			
			// crossjoin the base row data , with all relations data
			$crossjoined_rows = call_user_func_array( [collect([$base]), 'crossJoin'], $rel_data);

			$rows = $crossjoined_rows->map(function($r){

				$res = [];
				foreach($r as $i)
				{
					foreach($i as $k => $v)
					{
					$res[$k] = $v;
					}
				}

				return $res;
			});

			// $result = $result->concat( collect($rows)->map(function($r){ return collect($r)->values(); }) );
			$result = $result->concat( $rows );
			return $result;

		}, collect());
	}

	

	

}

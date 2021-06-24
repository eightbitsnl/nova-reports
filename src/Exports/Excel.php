<?php

namespace Eightbitsnl\NovaReports\Exports;

use Eightbitsnl\NovaReports\Models\Report;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;

class Excel implements FromQuery, WithHeadings, WithMapping, WithProperties, ShouldAutoSize
{
	use Exportable;
	
	private $report;
	
	public function forReport(Report $report)
	{
		$this->report = $report;
		return $this;
	}

    public function properties(): array
    {
        return [
            'creator'        => config('app.name'),
            // 'lastModifiedBy' => 'ENTER',
            'title'          => $this->report->title,
            'description'    => $this->report->note,
            // 'subject'        => '',
            // 'keywords'       => '',
            // 'category'       => '',
            // 'manager'        => '',
            // 'company'        => '',
        ];
    }
	
	public function map($model): array
	{
		// all the fields that will be reported
		$fieldnames = collect($this->report->getFieldsToReport());

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
		})->toArray();

		return collect($rows)->map(function($r){ return collect($r)->values(); })->toArray();
	}
	
	
	public function headings(): array
	{
		return $this->report->getFieldsToReport();
	}
	
	public function query()
	{
		return $this->report->getQuerybuilderInstance();
	}
}
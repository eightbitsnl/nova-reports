# Nova Reports

## Installation

### Prepare the database

```bash
php artisan vendor:publish --provider="Eightbitsnl\NovaReports\NovaReportsServiceProvider" --tag="migrations"
php artisan migrate
```


### Config Models

1. Add `Reportable` trait to the model(s) you want to report  
	
	```php
	use Eightbitsnl\NovaReports\Traits\Reportable;
	```

1. Config `$reportable` attribute on your model(s)
	```php
	/**
	 * The attributes that are reportable.
	 *
	 * @var array
	 */
	public static $reportable = [
		'id',
		'title',
		'created_at',
		'updated_at',
	];
	```

1. Config `getReportRules()` attribute on your model(s)  
	The method should return the rules that can be selected within the UI and added to a group. A simple set of rules might look like this:  

	```php
	public function getReportRules()
	{
		return [
			[
				'type' => "text",
				'id' => "vegetable",
				'label' => "Vegetable",
			],
			[
				'type' => "radio",
				'id' => "fruit",
				'label' => "Fruit",
				'choices' => [
					['label' => "Apple", 'value' => "apple"],
					['label' => "Banana", 'value' => "banana"]
				]
			],
		];
	}
	```
	See https://dabernathy89.github.io/vue-query-builder/configuration.html#rules


### Usage

#### Accessors

You can use [Accessors](https://laravel.com/docs/8.x/eloquent-mutators#defining-an-accessor) (computed properties) as `$reportable` fields.

```php
public static $reportable = [
	'first_name',
	'last_name',
	'full_name'
];

// Accessor
public function getFullNameAttribute()
{
    return "{$this->first_name} {$this->last_name}";
}

```

#### Dynamic Values

When building a Query, you can use dynamic values. For example, query records that are: `created_at >= {{START_OF_QUARTER}}` or `created_at >= {{SUB_7_DAY}}`

```php
{{NOW}}
{{START_OF_DAY}}
{{START_OF_MONTH}}
{{START_OF_QUARTER}}
{{START_OF_YEAR}}
{{START_OF_DECADE}}
{{START_OF_CENTURY}}
{{START_OF_MILLENNIUM}}
{{START_OF_WEEK}}
{{START_OF_HOUR}}
{{START_OF_MINUTE}}
{{START_OF_SECOND}}
{{END_OF_DAY}}
{{END_OF_MONTH}}
{{END_OF_QUARTER}}
{{END_OF_YEAR}}
{{END_OF_DECADE}}
{{END_OF_CENTURY}}
{{END_OF_MILLENNIUM}}
{{END_OF_WEEK}}
{{END_OF_HOUR}}
{{END_OF_MINUTE}}
{{END_OF_SECOND}}


{{ADD_1_DAY}}
{{ADD_2_DAY}}
etc...

{{ADD_1_MONTH}}
{{ADD_2_MONTH}}
etc...

{{SUB_1_DAY}}
{{SUB_2_DAY}}
etc...

{{SUB_1_MONTH}}
{{SUB_2_MONTH}}
etc...
```
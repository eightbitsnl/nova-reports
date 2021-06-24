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
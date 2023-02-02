<?php
return [


	/*
    |--------------------------------------------------------------------------
    | Filesystem setting
    |--------------------------------------------------------------------------
    |
    | The filesystem to use
    |
    */
	'filesystem' => env('NOVA_REPORTS_FILESYSTEM', env('FILESYSTEM_DRIVER', 'local')),

    /**
     * The number of minutes for which exports must be kept on the filesystem
     */
    'keep_exports_for_minutes' => 60,

	/*
    |--------------------------------------------------------------------------
    | Route setting
    |--------------------------------------------------------------------------
    |
    | This value's is for enabling settings in the table view
    |
    */

	'routes' => [
		'prefix' => [
            'api' => "/nova-vendor/eightbitsnl/nova-reports",
            'web' => "/nova-vendor/eightbitsnl/nova-reports",
        ],
	],

	/*
    |--------------------------------------------------------------------------
    | Web view setting
    |--------------------------------------------------------------------------
    */

	'webview' => [
		'enabled' => false,
		'max_count' => 100
	],

	/*
    |--------------------------------------------------------------------------
    | Export class
    |--------------------------------------------------------------------------
    |
    | If you want to change the default export class you can swap it out
	| for you own class.
    |
    */

	'exporter' => \Eightbitsnl\NovaReports\Exports\Excel::class,

	/*
    |--------------------------------------------------------------------------
    | Index Query class
    |--------------------------------------------------------------------------
    |
    | If you want to change the default IndexQuery function you can create
	| a new class that accepts a NovaRequest class and a Builder class in
	| its __invoke method. The __invoke method must return a Builder class
    |
    */

	'index_query' => \Eightbitsnl\NovaReports\Actions\IndexQueryBuilder::class,



];

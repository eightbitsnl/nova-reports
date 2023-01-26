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

	/*
    |--------------------------------------------------------------------------
    | Web view setting
    |--------------------------------------------------------------------------
    |
    | This value's is for enabling settings in the table view
    |
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

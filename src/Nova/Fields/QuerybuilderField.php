<?php

namespace Eightbitsnl\NovaReports\Nova\Fields;

use Laravel\Nova\Fields\Field;

class QuerybuilderField extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
	public $component = 'querybuilder-field';
	
	public $showOnIndex = false;
	public $showOnDetail = false;
	public $showOnCreation = true;
	public $showOnUpdate = true;
}

<?php

namespace Eightbitsnl\NovaReports\Actions;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

class IndexQueryBuilder
{
  	public function __invoke(NovaRequest $request, Builder $query): Builder
  	{
    	return $query;
  	}
}

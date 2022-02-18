<html>
    <head>
        <title>{{ config('app.name') }} - {{ $title }}</title>

		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
		
        <link href="{{ asset('/vendor/nova-reports/css/webview.css') }}" rel="stylesheet">
        
    </head>
    <body>

		<h1 class="text-3xl font-bold mb-2">{{ $title }}</h1>

		@if( $items->count() == 0)

			<div class="text-center">
				<p class="text-gray-500">No items found</p>
			</div>

		@else

			<table class="table-auto w-full border-2">
				<thead class="text-xs text-left font-semibold uppercase text-gray-400 bg-gray-50 sticky top-0 border-2">
					<tr>
						@foreach ($items->first() as $key => $value)
							<th class="p-2 whitespace-nowrap">
								{!!  str_replace('.','<br/>',$key) !!}
							</th>
						@endforeach
					</tr>
				</thead>
				<tbody class="text-sm divide-y divide-gray-100">
					@foreach ($items as $item)
					<tr>
						@foreach ($item as $value)
							<td class="p-2 whitespace-nowrap">
								{{ $value }}
							</td>
						@endforeach
					</tr>
					@endforeach
				</tbody>
			</table>

		@endif

    </body>
</html>
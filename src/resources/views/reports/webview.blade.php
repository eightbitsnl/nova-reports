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
				<p class="text-neutral-500">No items found</p>
			</div>

		@else

			<table class="table-auto w-full border-2">
				<thead class="sticky top-0">
					<tr class="bg-gray-200 uppercase text-sm leading-normal">
						@foreach ($items->first() as $key => $value)
							<th class="p-2 text-left whitespace-nowrap">
								{!!  str_replace('.','<br/>',$key) !!}
							</th>
						@endforeach
					</tr>
				</thead>
				<tbody class="text-sm">
					@foreach ($items as $item)
					<tr class="border-b border-gray-200 hover:bg-gray-100 odd:bg-white even:bg-gray-50 border-collapse">
						@foreach ($item as $value)
							<td class="p-2 text-left align-top whitespace-nowrap border-x">
								{!!  str_replace("\n",'<br/>',$value) !!}
							</td>
						@endforeach
					</tr>
					@endforeach
				</tbody>
			</table>

		@endif

    </body>
</html>

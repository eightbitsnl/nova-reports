<h1>{{ $title }}</h1>

@foreach ($items as $item)

	@foreach ($item as $k => $v)
		<strong>{{ $k }}</strong>:
		{{ $v }}
		<br/>
	@endforeach

	<hr>

@endforeach


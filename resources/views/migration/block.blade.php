Schema::{{ $type === 'create' ? $type : 'table' }}({!! json_encode($table) !!}, function (Blueprint {{ '$table' }}) {
@foreach ($blocks as $block)
@if (!$loop->first)

@endif
			@include('laramore::migration.partials.commands', ['commands' => $block])
@endforeach
		});

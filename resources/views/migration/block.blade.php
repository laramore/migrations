Schema::{{ $type === 'create' ? $type : 'table' }}({!! json_encode($table) !!}, function (Blueprint {{ '$table' }}) {
@foreach ($blocks as $key => $block)
@if ($key === 'self_constraints')		});

		Schema::table({!! json_encode($table) !!}, function (Blueprint {{ '$table' }}) {
			@include('laramore::migration.partials.commands', ['commands' => $block])
@else
@if (!$loop->first)

@endif
			@include('laramore::migration.partials.commands', ['commands' => $block])
@endif
@endforeach
		});

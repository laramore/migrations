Schema::{{ $type === 'create' ? $type : 'table' }}({!! json_encode($table) !!}, function (Blueprint {{ $blueprintVar }}) {
@if (count($fields))
            @include('laramore::migration.partials.commands', ['commands' => $fields])
@endif
@if (count($fields) && count($contraints))

@endif
@if (count($contraints))
	        @include('laramore::migration.partials.commands', ['commands' => $contraints])
@endif
        });

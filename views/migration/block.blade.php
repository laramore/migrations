Schema::{{ $type === 'create' ? $type : 'table' }}({!! json_encode($table) !!}, function (Blueprint {{ $blueprintVar }}) {
@if (count($fields))
            @include('laramore::migration.partials.commands', ['commands' => $fields])
@endif
@if (count($fields) && count($constraints))

@endif
@if (count($constraints))
	        @include('laramore::migration.partials.commands', ['commands' => $constraints])
@endif
@if ((count($fields) + count($constraints)) && count($indexes))

@endif
@if (count($indexes))
	        @include('laramore::migration.partials.commands', ['commands' => $indexes])
@endif
        });

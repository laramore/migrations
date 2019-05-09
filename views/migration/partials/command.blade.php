{{ $blueprintVar }}@foreach ($command as $method => $args)->{{ $method }}({!! implode(', ', array_map(function ($arg) { return json_encode($arg); }, (array) $args)) !!})@endforeach;

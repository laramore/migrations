{!! $php !!}
/**
 * Generated with Laramore on {{ $date }}.
 *
 * @var  Illuminate\Database\Migrations\Migration
 * @model {{ $model }}
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class {{ $name }} extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create({!! json_encode($table) !!}, function (Blueprint {{ $blueprintVar }}) {
@if (count($fields))
            @include('laramore::migration.partials.commands', ['commands' => $fields])
@endif
@if (count($fields) && count($contraints))

@endif
@if (count($contraints))
            @include('laramore::migration.partials.commands', ['commands' => $contraints])
@endif
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists({!! json_encode($table) !!});
    }
}

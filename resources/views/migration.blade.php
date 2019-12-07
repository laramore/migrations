{!! $php !!}
/**
 * Generated with Laramore on {{ $date }}.
 *
 * @var   Illuminate\Database\Migrations\Migration
@if (isset($model))
 * @model {{ $model }}
@endif
 */

use Laramore\Facades\Schema;
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
@if ($type === 'delete')
        @include('laramore::migration.line', $up)
@else
        @include('laramore::migration.block', $up)
@endif
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
@if ($type === 'create')
        @include('laramore::migration.line', $down)
@else
        @include('laramore::migration.block', $down)
@endif
    }
}

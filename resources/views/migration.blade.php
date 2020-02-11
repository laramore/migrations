{!! '<', '?php' !!}
/**
 * Generated with Laramore on {{ $date }}.
 *
 * @var  Illuminate\Database\Migrations\Migration
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
		@if (isset($up['line']))@include('laramore::migration.line', $up)@elseif (isset($up['blocks']))@include('laramore::migration.block', $up)@endif
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		@if (isset($down['line']))@include('laramore::migration.line', $down)@elseif (isset($down['blocks']))@include('laramore::migration.block', $down)@endif
	}
}

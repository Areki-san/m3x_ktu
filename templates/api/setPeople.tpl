{*<form id=settings>
	<input type=hidden name=type value=fondktu>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Отдел</th>
				<th class='align-middle'>Фонд КТУ</th>
			</tr>
		</thead>
		<tbody>

	{foreach $resKtu as $row}
		<tr class=text-center>
			<td class='align-middle'>{$row.otdel}</td>
			<td><input type=number class='form-control text-center' name='{$row.otdel}' value={$row.ktu}></td>
		</tr>
	{/foreach}

		</tbody>
	</table>
</form>

{* ------------------------------------------------------ *}

<form id=settings>
	<input type=hidden name=type value=staff>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Ф.И.О.</th>
				<th class='align-middle'>Оклад</th>
				<th class='align-middle'>Ставка час</th>
				<th class='align-middle'>Ставка</th>
				<th class='align-middle'>Надбавка</th>
				<th class='align-middle'>Премия</th>
				<th class='align-middle'>Удержания/Штрафы</th>
				<th class='align-middle'>Аванс</th>
				<th class='align-middle'>Больничные</th>
				<th class='align-middle'>Отпускные</th>
				<th class='align-middle'>Долг</th>
			</tr>
		</thead>
		<tbody>

{foreach $resPeople as $row}
		<tr class=text-center>
			<td class='align-middle'>{$row.fio}</td>
			<td><input type=number class='form-control text-center' name='{$row.id}[oklad]' value={$row.oklad}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[stavka_chas]' value={$row.stavka_chas}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[stavka]' value={$row.stavka}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[nadbavka]' value={$row.nadbavka}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[premia]' value={$row.premia}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[uderjanie]' value={$row.uderjanie}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[avans]' value={$row.avans}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[bolnichnye]' value={$row.bolnichnye}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[otpusknye]' value={$row.otpusknye}></td>
			<td><input type=number class='form-control text-center' name='{$row.id}[dolg]' value={$row.dolg}></td>
		</tr>
{/foreach}

		</tbody>
	</table>
</form>
<form id=settings>
	<input type=hidden name=type value=ktu>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Тип работ</th>
				<th class='align-middle'>КТУ</th>
			</tr>
		</thead>
		<tbody>

{foreach $resKtu as $row}
		<tr class=text-center>
			<td class='align-middle'>{$row.type_of_work}</td>
			<td><input type=number class='form-control text-center' name='{$row.work_id}' value={$row.work_ktu}></td>
		</tr>
{/foreach}

		</tbody>
	</table>
</form>

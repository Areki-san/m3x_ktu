<form id="settings">
    <input type="hidden" name="type" value="fondktu">
    <table class="table table-bordered table-sm table-striped">
        <thead>
            <tr class="text-center">
                <th class="align-middle">Отдел</th>
                <th class="align-middle">Фонд КТУ</th>
            </tr>
        </thead>
        <tbody>
            {foreach $resKtu as $row}
                <tr class="text-center">
                    <td class="align-middle">{$row.otdel}</td>
                    <td>
                        <input type="number" class="form-control text-center" name="{$row.otdel}" value="{$row.ktu}">
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</form>
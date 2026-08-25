<form id="settings">
    <input type="hidden" name="type" value="chasy">
    <table class="table table-bordered table-sm table-striped">
        <thead>
            <tr class="text-center">
                <th class="align-middle">Часы</th>
                <th class="align-middle">Оплата</th>
            </tr>
        </thead>
        <tbody>
            {foreach $rowChasy as $row}
                <tr class="text-center">
                    <td class="align-middle">{$row.tip}</td>
                    <td>
                        <input type="number" step="0.1" class="form-control text-center" name="{$row.tip}" value="{$row.stavka}">
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</form>
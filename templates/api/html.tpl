{* === ФОРМА ДЛЯ СКАЧИВАНИЯ EXCEL === *}
<form id="download" action="download.php" method="post">
    <input type="hidden" name="otdel" value="{$podr}">
</form>

<div class="alert alert-secondary">
    {if $arr_date}Архив от: {$arr_date} | {/if}
    Зарплата: с {$dateFrom|default:''} по {$dateTo|default:''}
</div>

{* === ФОРМА ДЛЯ СОХРАНЕНИЯ ИЗМЕНЕНИЙ === *}
<form id="settings">
    <input type="hidden" name="type" value="archive">
    <input type="hidden" name="archive_date" value="{$arr_date}">
    <input type="hidden" name="city" value="{$podr}">

    {* Рисуем таблицу *}
    {foreach $employees as $division_name => $division_staff}
        <table class="table table-bordered table-sm table-striped table-hover table-caption-top">
            <caption class="alert alert-info">{$division_name}</caption>
            <thead>
                <tr class="text-center">
                    {foreach $headers as $table_header}
                        {if !($table_header|in_array:$stop)}
                            <th class="align-middle">{$table_header}</th>
                        {/if}
                    {/foreach}
                </tr>
            </thead>
            <tbody>
                {foreach $division_staff as $surname => $personal_info}
                    <tr class="text-center">
                        {foreach $headers as $array_head}
                            {if !($array_head|in_array:$stop)}
                                {if $array_head == "Заявки" && $personal_info['Заявки'] > 0}
                                    <td>
                                        <button type="button" class="btn btn-link" data-toggle="modal" data-target="#modal{$personal_info['id']}">
                                            {$personal_info['Заявки']}
                                        </button>
                                    </td>
                                {elseif $array_head == 'Аванс' || $array_head == 'Премия' || $array_head == 'Премия ' || $array_head == 'Доп.премия' || $array_head == 'Доп.премия ' || $array_head == 'Больничные' || $array_head == 'Больничные ' || $array_head == 'Отпускные' || $array_head == 'Отпускные '}
                                    {if $is_editable_archive}
                                        {assign var="db_field" value="avans"}
                                        {if $array_head == 'Премия' || $array_head == 'Премия ' || $array_head == 'Доп.премия' || $array_head == 'Доп.премия '}
                                            {assign var="db_field" value="premia"}
                                        {elseif $array_head == 'Больничные' || $array_head == 'Больничные '}
                                            {assign var="db_field" value="bolnichnye"}
                                        {elseif $array_head == 'Отпускные' || $array_head == 'Отпускные '}
                                            {assign var="db_field" value="otpusknye"}
                                        {/if}
                                        <td>
                                            <input type="number" class="form-control text-center" name="{$personal_info.id}[{$db_field}]" value="{$personal_info.$array_head|default:0}" style="min-width: 12ch;">
                                        </td>
                                    {else}
                                        <td>{$personal_info.$array_head|default:0}</td>
                                    {/if}
                                {else}
                                    <td>{$personal_info.$array_head}</td>
                                {/if}
                            {/if}
                        {/foreach}
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/foreach}

    {* Рисуем модальные окна с заявками *}
    {foreach $modal as $modal_info}
        <div class="modal" id="modal{$modal_info['id']}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Заявки подробно</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered">
                            <thead class="text-center">
                                <tr>
                                    <th>Тип заявки</th>
                                    <th>Количество</th>
                                    <th>КТУ</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $classifier as $tip_zayavki => $ktu}
                                    <tr>
                                        <td>{$tip_zayavki}</td>
                                        <td class="text-center">
                                            {if $tip_zayavki|array_key_exists:$modal_info}
                                                {$modal_info[$tip_zayavki]}
                                            {else}
                                                0
                                            {/if}
                                        </td>
                                        <td class="text-center">{$classifier[$tip_zayavki]}</td>
                                    </tr>
                                {/foreach}
                                <tr>
                                    <td class="font-weight-bold">ИТОГО</td>
                                    <td class="text-center">{$modal_info['Заявки']}</td>
                                    <td class="text-center">{$modal_info['КТУ Количество']}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    {/foreach}

    <div class="alert alert-info">
        <input type="button" id="send_tabulka" class="btn btn-primary mr-sm-2" value="send to EMail" onclick="requst_php('settings','mail.php');" />
        {if $is_editable_archive}
            <input type="button" id="save_archive" class="btn btn-primary mr-sm-2" value="Внести изменения" onclick="requst_Json('settings','handler.php');" />
        {/if}
    </div>
</form>
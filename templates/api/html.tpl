{* === ФОРМА ДЛЯ СКАЧИВАНИЯ EXCSEL === *}
<form id=download action=download.php method=post>
	<input type=hidden name=otdel value={$podr}>
</form>

<div class='alert alert-secondary'>
    {if ($arr_date)}Архив от: {$arr_date|default:''} | {/if}
    Зарплата: с {$dateFrom|default:''} по {$dateTo|default:''}
</div>
{* === ФОРМА ДЛЯ СОХРАНЕНИЯ АВАНСОВ === *}
<form id="settings">
    {* Скрытые поля: 'avans' для безопасного обновления handler.php только этого поля *}
    <input type="hidden" name="type" value="avans">
	{* === передаём город и дату для поиска файла архива === *}
    <input type="hidden" name="archive_date" value="{$arr_date}">
    <input type="hidden" name="city" value="{$podr}">

    {* Рисуем таблицу *}
    {foreach $employees as $division_name => $division_staff}
        <table class='table table-bordered table-sm table-striped table-hover'>
            <div class='alert alert-info'>{$division_name}</div>
            {* заголовок таблицы *}
            <thead><tr class=text-center>
            {foreach $headers as $table_header}
                {if !($table_header|in_array:$stop)}
                    <th class='align-middle'>{$table_header}</th>
                {/if}
            {/foreach}
            </tr></thead>
            
            {* тело таблицы *}
            <tbody>
            {foreach $division_staff as $surname => $personal_info}
                <tr class=text-center>
                {foreach $headers as $array_head}
                    {if !($array_head|in_array:$stop)}
                        {if $array_head == "Заявки" && $personal_info['Заявки'] > 0}
                            <td><button type='button' class='btn btn-link' data-toggle='modal' data-target='#modal{$personal_info['id']}'>{$personal_info['Заявки']}</button></td>
                        
                        {* === УСЛОВИЕ ДЛЯ КОЛОНКИ "АВАНС" === *}
                        {elseif $array_head == 'Аванс'}
                            {if $is_editable_avans}
                                {* До 10-го числа: поле для ввода *}
                                <td>
                                    <input type="number" 
                                           class="form-control text-center" 
                                           name="{$personal_info.id}[avans]" 
                                           value="{$personal_info.$array_head}"
                                           style="min-width: 12ch;">
                                </td>
                            {else}
                                {* После 10-го числа: только текст из БД *}
                                <td>{$personal_info.$array_head}</td>
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

{* Рисуем таблицу КТУ *}
{foreach $modal as $modal_info}
	<div class='modal' id='modal{$modal_info['id']}'>
		<div class='modal-dialog'>
			<div class='modal-content'>
				<div class='modal-header'>
					<h4 class='modal-title'>Заявки подробно</h4>
					<button type='button' class='close' data-dismiss='modal'>&times;</button>
				</div>
				<div class='modal-body'>
					<table class='table table-bordered'>
						<thead class=text-center>
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

								{if $tip_zayavki|array_key_exists:$modal_info}
									<td class=text-center>{$modal_info[$tip_zayavki]}</td>
								{else}
									<td class=text-center>0</td>
								{/if}

								<td class=text-center>{$classifier[$tip_zayavki]}</td>
							</tr>
					{/foreach}
							<tr>
								<td class='font-weight-bold'>ИТОГО</td>
								<td class=text-center>{$modal_info['Заявки']}</td>
								<td class=text-center>{$modal_info['КТУ Количество']}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-danger' data-dismiss='modal'>Close</button>
				</div>
			</div>
		</div>
	</div>
{/foreach}

<div class = 'alert alert-info'>
	<!-- <button class="btn btn-primary mr-sm-2" onclick="alert('Привет, StackOverflow!')">Нажмите на меня!</button> -->
	<!--<input type="button" id="send_tabulka" class="btn btn-primary mr-sm-2" value="send to TG" onclick="requst_php('settings','tg.php');" />-->
	<input type="button" id="send_tabulka" class="btn btn-primary mr-sm-2" value="send to EMail" onclick="requst_php('settings','mail.php');" />
{if $is_editable_avans}
	<input type="button" id="save_avans" class="btn btn-primary mr-sm-2" value="Сохранить авансы" onclick="requst_Json('settings','handler.php');" />
{/if}
</div>

{* подключаем шапку шаблона *}
{include file='header.tpl'}

<ul class="navbar-nav">
    <form class="form-inline" id="otdel_list">
        <input type="hidden" name="nastr" value="zarplata_from_archive">

        <li class="nav-item nav-link">
            <div class="form-group">
                <select class="form-control" name="city" id="archiveCity">
                    {foreach $arr_city as $item}
                        <option value="{$item}" {if isset($selected_city) && $item == $selected_city}selected{/if}>{$item}</option>
                    {/foreach}
                </select>
            </div>
        </li>

        <li class="nav-item nav-link">
            <div class="form-group">
                <select class="form-control" name="archive_date" id="archiveDate">
                    {if empty($arr_date)}
                        <option value="">Нет сохранённых расчётов</option>
                    {else}
                        {foreach $arr_date as $date}
                            <option value="{$date}">{$date}</option>
                        {/foreach}
                    {/if}
                </select>
            </div>
        </li>

        <li class="nav-item">
            <input type="button" id="btnArchiveShow" class="btn btn-primary me-2" value="Показать из архива" />
        </li>
        <li class="nav-item">
            <input type="button" id="btnDownloadExcel" class="btn btn-primary me-2" value="EXCEL" />
        </li>
    </form>
</ul>

</div>
</nav>
<!--end navbar-->

<div class="container-fluid pt-4">
    <div id="result_form">
        <div></div>
    </div>
</div>

</body>
</html>
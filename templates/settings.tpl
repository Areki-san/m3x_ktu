{include file='header.tpl'}

<ul class="navbar-nav">
    <li class="nav-item">
        <input type="button" id="btnUpdate" class="btn btn-primary" value="Обновить DB" onclick="test('settings','db_update.php','result_form');" />
    </li>
</ul>

</div>
</nav>

<div class="container-fluid pt-4">
    <div class="row">
        <div class="col-sm-2">
            <div class="btn-group" id="btnSave">
                <input type="button" id="btnSet" class="btn btn-primary" name="test" value="Настроить" />
                <input type="button" id="btnSave" class="btn btn-dark" value="Сохранить" onclick="requst_Json('settings','handler.php');" />
            </div>
            <form id="settings_form">
                <input type="hidden" name="nastr" value="settings">
                
                <fieldset>
                    <legend>ПОДРАЗДЕЛЕНИЯ</legend>
                    {foreach $arr_setings_division as $item}
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="radio" class="form-check-input" name="tip" value="{$item}">{$item}
                            </label>
                        </div>
                    {/foreach}
                </fieldset>

                <fieldset>
                    <legend>КТУ</legend>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input type="radio" class="form-check-input" name="tip" value="fondKTU">Фонд КТУ
                        </label>
                    </div>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input type="radio" class="form-check-input" name="tip" value="ktu">КТУ Работы
                        </label>
                    </div>
                    <div class="form-check">
                        <label class="form-check-label">
                            <input type="radio" class="form-check-input" name="tip" value="chasy">Часы
                        </label>
                    </div>
                </fieldset>
            </form>
        </div>
        <div class="col-sm-10" id="result_form"></div>
        <div class="col-sm-10" id="set_form"></div>
    </div>
</div>

</body>
</html>
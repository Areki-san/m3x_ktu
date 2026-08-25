{* Рисуем табель *}
{if empty($timesheet)}
    <div class="alert alert-warning">
        Нет данных для табеля.
    </div>
{else}
    <div class="alert alert-secondary">
        Табель почасовки сотрудников: с {$dateFrom|default:''} по {$dateTo|default:''}
    </div>

    {foreach $timesheet as $division_name => $division_staff}
        <div class="alert alert-info">{$division_name}</div>

        <table class="table table-bordered table-sm table-striped table-hover">
            <thead>
                <tr class="text-center">
                    <th class="align-middle" rowspan="2">Ф.И.О</th>
                    <th class="align-middle" rowspan="2">Оклад</th>
                    <th class="align-middle" colspan="4">Часы</th>
                </tr>
                <tr class="text-center">
                    <th class="align-middle">Обычные</th>
                    <th class="align-middle">Сверхурочные</th>
                    <th class="align-middle">Выходные</th>
                    <th class="align-middle">Всего</th>
                </tr>
            </thead>
            <tbody>
                {foreach $division_staff as $surname => $row}
                    <tr class="text-center">
                        <td>{$surname}</td>
                        <td>{$row['Оклад']|default:0}</td>
                        <td>{$row['Часы']|default:0}</td>
                        <td>{$row['Часы Сверхурочные']|default:0}</td>
                        <td>{$row['Часы Выходные']|default:0}</td>
                        <td>{$row['Часы Всего']|default:0}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/foreach}
{/if}
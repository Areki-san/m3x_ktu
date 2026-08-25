<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .fio { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .content { background: #f8f9fa; padding: 30px; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 12px 10px; border-bottom: 1px solid #dee2e6; }
        td.label { color: #666; width: 65%; }
        td.value { font-weight: bold; text-align: right; white-space: nowrap; }
        .total-row { background: #e8f4f8; font-size: 16px; }
        .total-row td { border-bottom: none; padding: 15px 10px; }
        .net-salary { background: #2c3e50; color: white; font-size: 20px; border-radius: 5px; margin-top: 20px; }
        .net-salary td { border: none; padding: 20px 10px; }
        .net-salary .value { color: #2ecc71; font-size: 24px; }
        .footer { text-align: center; padding: 20px; color: #95a5a6; font-size: 12px; }
        @media screen and (max-width: 480px) {
            .container { padding: 10px; }
            .content { padding: 15px; }
            .fio { font-size: 20px; }
            td { padding: 10px 5px; font-size: 14px; }
            .net-salary { font-size: 16px; }
            .net-salary td { padding: 15px 8px; }
            .net-salary .value { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="fio">{$fio}</div>
        </div>
        <div class="content">
            <table>
                {if $show_zayavki}
                    <tr><td class="label">Заявки:</td><td class="value">{$zayavki}</td></tr>
                {/if}
                {if $show_ktu_personal}
                    <tr><td class="label">% КТУ Личный:</td><td class="value">{$ktu_personal}</td></tr>
                {/if}
                {if $show_zp_ktu}
                    <tr><td class="label">ЗП КТУ:</td><td class="value">{$zp_ktu}</td></tr>
                {/if}
                <tr><td class="label">Часы:</td><td class="value">{$hours}</td></tr>
                <tr><td class="label">Часы Сверхурочные:</td><td class="value">{$overtime_hours}</td></tr>
                <tr><td class="label">Часы Выходные:</td><td class="value">{$weekend_hours}</td></tr>
                {if $show_rate_hours}
                    <tr><td class="label">Ставка час:</td><td class="value">{$rate_hour}</td></tr>
                    <tr><td class="label">ЗП Часы:</td><td class="value">{$salary_hours}</td></tr>
                {/if}
                {if $show_rate}
                    <tr><td class="label">Ставка:</td><td class="value">{$rate}</td></tr>
                {/if}
                {if $show_construction_works}
                    <tr><td class="label">Строительные работы:</td><td class="value">{$construction_works}</td></tr>
                {/if}
                <tr><td class="label">Надбавки:</td><td class="value">{$allowances}</td></tr>
                <tr><td class="label">Премия:</td><td class="value">{$bonus}</td></tr>
                <tr><td class="label">Удержания/Штраф:</td><td class="value">{$deductions}</td></tr>
                <tr class="total-row"><td class="label"><strong>Итого ЗП:</strong></td><td class="value"><strong>{$total}</strong></td></tr>
                <tr><td class="label">Аванс:</td><td class="value">{$advance}</td></tr>
            </table>
            <table class="net-salary">
                <tr><td class="label">На руки:</td><td class="value">{$net_salary}</td></tr>
            </table>
            <table>
                {if $show_vacation}
                    <tr class="total-row"><td class="label">+ Отпускные:</td><td class="value">{$vacation_pay}</td></tr>
                {/if}
                {if $show_sick}
                    <tr class="total-row"><td class="label">+ Больничные:</td><td class="value">{$sick_leave}</td></tr>
                {/if}
            </table>
        </div>
        <div class="footer">Это автоматическое уведомление. По всем вопросам обращайтесь в бухгалтерию.</div>
    </div>
</body>
</html>
{* подключаем шапку шаблона *}
{include file='header.tpl'}

	    <ul class="navbar-nav">
		<form class="form-inline" id="otdel_list">
		    <input type="hidden" name="nastr" value="zarplata">
		    <li class="nav-item nav-link">
			<div class="form-group">
			    <select class="form-control" name="city">
{foreach $arr_city as $item}
					<option value="{$item}">{$item}</option>
{/foreach}
			    </select>
			</div>
		    </li>
		    <li class="nav-item">
			<input type="date" name="from" id="dateFrom" class="form-control mr-sm-2 bg-dark text-white">
		    </li>
		    <li class="nav-item">
			<input type="date" name="to" id="dateTo" class="form-control mr-sm-2 bg-dark text-white">
		    </li>
		    <li class="nav-item">
			<input type="button" id="btnTimesheet" class="btn btn-primary mr-sm-2" value="Табель" />
			<input type="button" id="btnZarplata" class="btn btn-primary mr-sm-2" value="Рассчитать" />
			<input type=submit form=download id=btnDownload class='btn btn-warning' value=Загрузить />
		    </li>
		</form>
	    </ul>
	</div>
    </nav>
<!--end navbar-->
<div class="container-fluid pt-4">
    <div id="result_form">
    	<div>
    		
    	</div>
    </div>
</div>

</body>
</html>

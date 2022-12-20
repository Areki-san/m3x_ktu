/* Article FructCode.com */
$( document ).ready(function() {
    $("#btn").click( function() {
		sendAjaxForm('result_form', 'otdel_list', 'api.php');
		return false; 
	});
    $("#btnSet").click( function() {
        sendAjaxForm('result_form', 'settings_form', 'api.php');
        return false;
    });
});

function test(form,url){
    $.ajax({
        url:   url,
        type:   "POST",
        dataType:   "html",
        data: $("#"+form).serialize(),
        success: function(response) {
            //alert(response);
            $('#set_form').html(response);
        },
        error: function(response) {
            alert("Ошибка");
        }
    });
}

function down(form,url){
    $.ajax({
        url:   url,
        type:   "POST",
        dataType:   "html",
        data: $("#"+form).serialize(),
        success: function(response) {
            //alert(response);
            //$('#set_form').html(response);
        },
        error: function(response) {
            alert("Ошибка");
        }
    });
}

function sendAjaxForm(result_form, otdel_list, url) {
    $.ajax({
        url:     url, //url страницы (action_ajax_form.php)
        type:     "POST", //метод отправки
        dataType: "html", //формат данных
        data: $("#"+otdel_list).serialize(),  // Сеарилизуем объект
        success: function(response) { //Данные отправлены успешно
            $('#result_form').html(response);
    	},
    	error: function(response) { // Данные не отправлены
            $('#result_form').html('Ошибка. Данные не отправлены.');
    	}
 	});
    //console.log(arguments[0]);
}
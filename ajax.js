/* Article FructCode.com */
$( document ).ready(function() {
    // обработчик для #btnZarplata
    $("#btnZarplata").click( function() {
		$('#result_form').empty();
        $('<div class="text-center p-3"><img src="img/load.gif" alt="Загрузка..."></br> Выполняется...</div>').appendTo('#result_form');
        startLoadingAnimation();  // - запустим анимацию загрузки
        sendAjaxForm('result_form', 'otdel_list', 'api.php');
		return false; 
	});

    // обработчик для #btnTimesheet
    $("#btnTimesheet").click( function() {
        $('#result_form').empty();
        $('<div class="text-center p-3"><img src="img/load.gif" alt="Загрузка..."></br> Выполняется...</div>').appendTo('#result_form');
        startLoadingAnimation();
        sendTimesheetForm('result_form', 'otdel_list', 'api.php');
        return false;
    });

    // обработчик для #btnSet
    $("#btnSet").click( function() {
        sendAjaxForm('result_form', 'settings_form', 'api.php');
        return false;
    });

    // Обработчик кнопки "Показать из архива" на странице archive.php
    $(document).on('click', '#btnArchiveShow', function() {
        $('#result_form').empty();

        $('<div class="text-center p-3"><img src="img/load.gif" alt="Загрузка..."></br>Загрузка архива...</div>')
            .appendTo('#result_form');

        startLoadingAnimation();

        sendArchiveForm('result_form', 'otdel_list', 'api.php');

        return false;
    });

    // Обработчик кнопки "EXCEL" на странице архива
    $(document).on('click', '#btnDownloadExcel', function() {
        var $btn = $(this);
        var originalText = $btn.val();
        
        var city = $('#archiveCity').val();
        var archiveDate = $('#archiveDate').val();
        
        if (!city || !archiveDate) {
            alert('Выберите город и дату архива');
            return;
        }
        
        $btn.prop('disabled', true).val('Генерация...');
        
        // Сначала генерируем Excel через api.php
        $.ajax({
            url: 'api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                nastr: 'generate_excel',
                city: city,
                archive_date: archiveDate
            },
            success: function(response) {
                if (response.success) {
                    // После успешной генерации скачиваем файл через download.php
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'download.php';
                    form.style.display = 'none';
                    
                    var inputOtdel = document.createElement('input');
                    inputOtdel.type = 'hidden';
                    inputOtdel.name = 'otdel';
                    inputOtdel.value = city;
                    form.appendChild(inputOtdel);
                    
                    var inputArchive = document.createElement('input');
                    inputArchive.type = 'hidden';
                    inputArchive.name = 'archive';
                    inputArchive.value = '1';
                    form.appendChild(inputArchive);
                    
                    var inputDate = document.createElement('input');
                    inputDate.type = 'hidden';
                    inputDate.name = 'archive_date';
                    inputDate.value = archiveDate;
                    form.appendChild(inputDate);
                    
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                    
                    $btn.val(originalText).prop('disabled', false);
                } else {
                    alert('Ошибка: ' + (response.error || 'Не удалось создать файл'));
                    $btn.val(originalText).prop('disabled', false);
                }
            },
            error: function(xhr) {
                let msg = 'Ошибка сервера';
                try {
                    msg = JSON.parse(xhr.responseText).error || msg;
                } catch(e) {}
                alert(msg);
                $btn.val(originalText).prop('disabled', false);
            }
        });
    });

    // Обновление списка дат при смене города на странице архива
    $(document).on('change', '#archiveCity', function() {
        var city = $(this).val();
        var $dateSelect = $('#archiveDate');

        $dateSelect.prop('disabled', true);
        $dateSelect.empty();
        $dateSelect.append('<option value="">Загрузка...</option>');

        $.ajax({
            url: 'archive.php',
            type: 'GET',
            dataType: 'json',
            data: {
                get_dates: 1,
                city: city
            },
            success: function(response) {
                $dateSelect.empty();

                if (response && response.success && response.dates && response.dates.length > 0) {
                    $.each(response.dates, function(index, dateValue) {
                        $('<option>')
                            .val(dateValue)
                            .text(dateValue)
                            .appendTo($dateSelect);
                    });
                } else {
                    $('<option>')
                        .val('')
                        .text('Нет сохранённых расчётов')
                        .appendTo($dateSelect);
                }

                $dateSelect.prop('disabled', false);
            },
            error: function() {
                $dateSelect.empty();
                $dateSelect.append('<option value="">Ошибка получения дат</option>');
                $dateSelect.prop('disabled', false);
            }
        });
    });
});

function onAjaxSuccess(data) // - функция завершения запроса
{
  stopLoadingAnimation();
 
  // дальнейшая работа с полученными от сервера данными
  //...
}

function startLoadingAnimation() // - функция запуска анимации
{
  // найдем элемент с изображением загрузки и уберем невидимость:
  var imgObj = $("#loadImg");
  imgObj.show();
 
  // вычислим в какие координаты нужно поместить изображение загрузки,
  // чтобы оно оказалось в серидине страницы:
  //var centerY = $(window).scrollTop() + ($(window).height() + imgObj.height())/2;
  //var centerX = $(window).scrollLeft() + ($(window).width() + imgObj.width())/2;
 
  // поменяем координаты изображения на нужные:
  //imgObj.offset(top:centerY, left:centerX);
}
 
function stopLoadingAnimation() // - функция останавливающая анимацию
{
  $("#loadImg").hide();
}

function test(form, url, target){
    target = target || 'set_form';  // по умолчанию выводим в set_form

    // Показываем индикатор загрузки в целевом блоке
    $('#' + target).html('<div class="text-center p-3"><img src="img/load.gif" alt="Загрузка..."></br> Выполняется...</div>');
    

    $.ajax({
        url:   url,
        type:   "POST",
        dataType:   "html",
        data: $("#"+form).serialize(),
        success:  function(response) {
            $('#' + target).html(response);
        },
        error: function(response) {
            $('#' + target).html('<div class="alert alert-danger">Ошибка выполнения запроса</div>');
        }
    });
}

// функция — для handler.php (JSON)
function requst_Json(form, url) {
    $.ajax({
        url:      url,
        type:     "POST",
        dataType: "json",
        data:     $("#" + form).serialize(),
        success:  function(response) {
            if (response.success) {
                alert(response.message || 'Сохранено -ajax-');
                // ====== после сохранения авансов перезагружаем архив =======
                if ($('#btnArchiveShow').length > 0) {
                    $('#btnArchiveShow').trigger('click');
                }
                // ===========================================================
            } else {
                alert('Ошибка: ' + (response.error || 'неизвестная ошибка'));
            }
        },
        error: function(xhr) {
            let msg = 'Ошибка сервера';
            try {
                msg = JSON.parse(xhr.responseText).error || msg;
            } catch(e) {}
            alert(msg);
        }
    });
}

function requst_php(form,url){
    $.ajax({
        url:   url,
        type:   "POST",
        dataType:   "html",
        data: $("#"+form).serialize(),
        success: function(response) {
            alert("OK");
            alert(response);
            //$('#set_form').html(response);
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

function sendTimesheetForm(result_form, otdel_list, url) {
    var formData = $("#" + otdel_list).serializeArray();

    var changed = false;

    for (var i = 0; i < formData.length; i++) {
        if (formData[i].name === 'nastr') {
            formData[i].value = 'timesheet';
            changed = true;
        }
    }

    if (!changed) {
        formData.push({
            name: 'nastr',
            value: 'timesheet'
        });
    }

    $.ajax({
        url: url,
        type: "POST",
        dataType: "html",
        data: $.param(formData),
        success: function(response) {
            stopLoadingAnimation();
            $('#' + result_form).html(response);
        },
        error: function(response) {
            stopLoadingAnimation();
            $('#' + result_form).html('Ошибка. Данные не отправлены.');
        }
    });
}

function sendArchiveForm(result_form, otdel_list, url) {
    var formData = $("#" + otdel_list).serializeArray();

    var hasNastr = false;
    var hasDate = false;

    for (var i = 0; i < formData.length; i++) {
        if (formData[i].name === 'nastr') {
            formData[i].value = 'zarplata_from_archive';
            hasNastr = true;
        }

        if (formData[i].name === 'archive_date' && formData[i].value !== '') {
            hasDate = true;
        }
    }

    if (!hasNastr) {
        formData.push({
            name: 'nastr',
            value: 'zarplata_from_archive'
        });
    }

    if (!hasDate) {
        stopLoadingAnimation();
        $('#' + result_form).html('<div class="alert alert-warning">Выберите дату архива</div>');
        return;
    }

    $.ajax({
        url: url,
        type: "POST",
        dataType: "html",
        data: $.param(formData),
        success: function(response) {
            stopLoadingAnimation();
            $('#' + result_form).html(response);
        },
        error: function(response) {
            stopLoadingAnimation();
            $('#' + result_form).html('<div class="alert alert-danger">Ошибка. Данные не отправлены.</div>');
        }
    });
}
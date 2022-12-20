<?php
//header("Location: ".$_SERVER['HTTP_REFERER']);

//print_r($_POST);

//preg_replace('/\PL/u', '', $str)

function function_alert($message) {
    echo "<script>alert('$message');</script>";
}

$dblite = new SQLite3('ktu.db', SQLITE3_OPEN_READWRITE);

switch ($_POST['type']) {
	case 'staff':
		$statement = $dblite->prepare('UPDATE OR IGNORE employees SET stavka=:stavka, stavka_chas=:stavka_chas, nadbavka=:nadbavka WHERE id = :id');
        unset($_POST['type']);
        //echo '<pre>';
        //print_r($_POST);
        //echo '</pre>';
        foreach ($_POST as $id => $info) {
            $statement->bindValue(':id', $id);
            $statement->bindValue(':stavka', $info['stavka']);
            $statement->bindValue(':stavka_chas', $info['stavka_chas']);
            $statement->bindValue(':nadbavka', $info['nadbavka']);
			$statement->execute();
		}
		function_alert("Сохранено");
		break;
	case 'fondktu':
		$statement = $dblite->prepare('UPDATE OR IGNORE fond_ktu SET ktu=:ktu WHERE otdel = :otdel');
		foreach ($_POST as $key => $value) {
			$statement->bindValue(':otdel', $key);
			$statement->bindValue(':ktu', $value);
			$statement->execute();
		}
		function_alert("Сохранено");
		break;
	case 'ktu':
		$statement = $dblite->prepare('UPDATE OR IGNORE ktu SET work_ktu=:work_ktu WHERE work_id = :work_id');
		foreach ($_POST as $key => $value) {
			$statement->bindValue(':work_id', $key);
			$statement->bindValue(':work_ktu', $value);
			$statement->execute();
		}
		function_alert("Сохранено");
		break;

	case 'chasy':
		$statement = $dblite->prepare('UPDATE OR IGNORE chasy SET stavka=:stavka WHERE tip = :tip');
		foreach ($_POST as $key => $value) {
			$statement->bindValue(':tip', $key);
			$statement->bindValue(':stavka', $value);
			$statement->execute();
		}
		function_alert("Сохранено");
		break;
}

$dblite->close();
/*
$dblite = new SQLite3('ktu.db', SQLITE3_OPEN_READWRITE);
$statement = $dblite->prepare('UPDATE OR IGNORE employees SET stavka=:stavka, stavka_chas=:stavka_chas, nadbavka=:nadbavka WHERE id = :id');
foreach ($_POST as $id => $info) {
	$statement->bindValue(':id', $id);
	$statement->bindValue(':stavka', $info['stavka']);
	$statement->bindValue(':stavka_chas', $info['stavka_chas']);
	$statement->bindValue(':nadbavka', $info['nadbavka']);
	$statement->execute();
}

$dblite->close();*/

?>

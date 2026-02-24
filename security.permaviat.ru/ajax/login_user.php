<?php
	session_start();
	include("../settings/connect_datebase.php");
	
	// Защита от частых запросов (несколько запросов в секунду)
	if (!isset($_SESSION['last_request_time'])) {
		$_SESSION['last_request_time'] = time();
		$_SESSION['request_count'] = 1;
	} else {
		$current_time = time();
		if ($current_time == $_SESSION['last_request_time']) {
			$_SESSION['request_count']++;
			if ($_SESSION['request_count'] > 2) {
				http_response_code(429);
				die("Слишком много запросов. Подождите.");
			}
		} else {
			$_SESSION['last_request_time'] = $current_time;
			$_SESSION['request_count'] = 1;
		}
	}

	$login = $_POST['login'];
	$password = $_POST['password'];
	
	// Добавляем поля для защиты от брутфорса, если их нет
	$res = $mysqli->query("SHOW COLUMNS FROM `users` LIKE 'failed_attempts'");
	if($res && $res->num_rows == 0) {
		$mysqli->query("ALTER TABLE `users` ADD COLUMN `failed_attempts` INT DEFAULT 0");
		$mysqli->query("ALTER TABLE `users` ADD COLUMN `locked_until` DATETIME DEFAULT NULL");
	}
	
	// ищем пользователя
	$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='".$login."';");
	$user = $query_user->fetch_assoc();
	
	$id = -1;
	if ($user) {
		// Проверка на блокировку учетной записи
		if ($user['locked_until'] != null && strtotime($user['locked_until']) > time()) {
			echo md5(md5(-1));
			exit();
		}

		if ($user['password'] === $password) {
			// Успешная авторизация, сброс счетчика неудачных попыток
			$id = $user['id'];
			$_SESSION['user'] = $id;
			$mysqli->query("UPDATE `users` SET `failed_attempts` = 0, `locked_until` = NULL WHERE `id`=".$id);
		} else {
			// Неудачная попытка
			$attempts = $user['failed_attempts'] + 1;
			if ($attempts >= 5) {
				// Блокируем на 5 минут
				$locked_until = date("Y-m-d H:i:s", time() + 300);
				$mysqli->query("UPDATE `users` SET `failed_attempts` = ".$attempts.", `locked_until` = '".$locked_until."' WHERE `id`=".$user['id']);
			} else {
				$mysqli->query("UPDATE `users` SET `failed_attempts` = ".$attempts." WHERE `id`=".$user['id']);
			}
		}
	}
	
	echo md5(md5($id));
?>
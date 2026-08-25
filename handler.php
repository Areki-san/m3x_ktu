<?php
//header("Location: ".$_SERVER['HTTP_REFERER']);
//print_r($_POST);
//preg_replace('/\PL/u', '', $str)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ─── Валидация входных данных ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

if (empty($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не указан тип операции']);
    exit;
}

$type = $_POST['type'];
unset($_POST['type']); // ← Убираем 'type', чтобы не попал в цикл UPDATE

if (empty($_POST)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Нет данных для сохранения']);
    exit;
}

// ─── Подключение к БД ────────────────────────────────────────
try {
    $db = new SQLite3('ktu.db', SQLITE3_OPEN_READWRITE);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA synchronous=NORMAL');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
    exit;
}

/**
 * Очищает название города для безопасного имени файла.
 */
function archiveSanitizeCity(string $city): string {
    $city = trim($city);
    if ($city === '') return 'unknown';
    $city = preg_replace('/\s+/u', '_', $city);
    if (!is_string($city)) return 'unknown';
    $city = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $city);
    if (!is_string($city) || $city === '') return 'unknown';
    return $city;
}

// ─── Обработчики ─────────────────────────────────────────────
try {
    switch ($type) {

        // ── Сотрудники ────────────────────────────────────────
        case 'staff':
            $stmt = $db->prepare('
                UPDATE OR IGNORE employees SET
                	oklad       = :oklad,    
					stavka      = :stavka,
                    stavka_chas = :stavka_chas,
                    nadbavka    = :nadbavka,
                    premia      = :premia,
                    bolnichnye  = :bolnichnye,
                    otpusknye   = :otpusknye,
                    uderjanie   = :uderjanie,
                    avans       = :avans,
                    dolg        = :dolg
                WHERE id = :id
            ');

            if ($stmt === false) {
                throw new RuntimeException('Не удалось подготовить запрос: ' . $db->lastErrorMsg());
            }

            $db->exec('BEGIN TRANSACTION');

            $updated = 0;
            foreach ($_POST as $id => $info) {
                $id = (int) $id;
                if ($id <= 0 || !is_array($info)) {
                    continue; // пропускаем мусорные ключи
                }

                $stmt->bindValue(':id',          $id,                                    SQLITE3_INTEGER);
                $stmt->bindValue(':oklad',       (float)($info['oklad']       ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':stavka',      (float)($info['stavka']      ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':stavka_chas', (float)($info['stavka_chas'] ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':nadbavka',    (float)($info['nadbavka']    ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':premia',      (float)($info['premia']      ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':bolnichnye',  (float)($info['bolnichnye']  ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':otpusknye',   (float)($info['otpusknye']   ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':uderjanie',   (float)($info['uderjanie']   ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':avans',       (float)($info['avans']       ?? 0),     SQLITE3_FLOAT);
                $stmt->bindValue(':dolg',        (float)($info['dolg']        ?? 0),     SQLITE3_FLOAT);



                $stmt->execute();
                $stmt->reset();   // ← сбрасываем для повторного использования
                $updated++;
            }

            $db->exec('COMMIT');
            $stmt->close();
            break;

        // ── Фонд КТУ ─────────────────────────────────────────
        case 'fondktu':
            $stmt = $db->prepare('UPDATE OR IGNORE fond_ktu SET ktu = :ktu WHERE otdel = :otdel');
            if ($stmt === false) {
                throw new RuntimeException('Не удалось подготовить запрос: ' . $db->lastErrorMsg());
            }

            $db->exec('BEGIN TRANSACTION');

            foreach ($_POST as $otdel => $ktu) {
                $stmt->bindValue(':otdel', (string) $otdel, SQLITE3_TEXT);
                $stmt->bindValue(':ktu',   (int) $ktu,      SQLITE3_INTEGER);
                $stmt->execute();
                $stmt->reset();
            }

            $db->exec('COMMIT');
            $stmt->close();
            break;

        // ── КТУ по работам ───────────────────────────────────
        case 'setktu':
            $stmt = $db->prepare('UPDATE OR IGNORE ktu SET work_ktu = :work_ktu WHERE work_id = :work_id');
            if ($stmt === false) {
                throw new RuntimeException('Не удалось подготовить запрос: ' . $db->lastErrorMsg());
            }

            $db->exec('BEGIN TRANSACTION');

            foreach ($_POST as $workId => $ktu) {
                $stmt->bindValue(':work_id',  (string) $workId, SQLITE3_TEXT);
                $stmt->bindValue(':work_ktu', (float) $ktu,  SQLITE3_FLOAT);
                $stmt->execute();
                $stmt->reset();
            }

            $db->exec('COMMIT');
            $stmt->close();
            break;

        // ── Часы / ставки ────────────────────────────────────
        case 'chasy':
            $stmt = $db->prepare('UPDATE OR IGNORE chasy SET stavka = :stavka WHERE tip = :tip');
            if ($stmt === false) {
                throw new RuntimeException('Не удалось подготовить запрос: ' . $db->lastErrorMsg());
            }

            $db->exec('BEGIN TRANSACTION');

            foreach ($_POST as $tip => $stavka) {
                $stmt->bindValue(':tip',    (string) $tip,   SQLITE3_TEXT);
                $stmt->bindValue(':stavka', (float) $stavka, SQLITE3_FLOAT);
                $stmt->execute();
                $stmt->reset();
            }

            $db->exec('COMMIT');
            $stmt->close();
            break;

        // ── Обновление ТОЛЬКО полей заполняемых бухгалтерией (безопасно, не трогает другие поля) + перезапись JSON ──
        case 'archive':
            $stmt = $db->prepare('
                UPDATE employees SET
                premia = :premia,
                bolnichnye = :bolnichnye,
                otpusknye = :otpusknye,
                avans = :avans
                WHERE id = :id
            ');
            if ($stmt === false) {
                throw new RuntimeException('Не удалось подготовить запрос: ' . $db->lastErrorMsg());
            }

            // Извлекаем данные для обновления JSON-файла архива
            $podr   = $_POST['city']                ?? '';
            $archive_date = $_POST['archive_date']  ?? '';  // Дата из имени файла (YYYY-MM-DD)
            unset($_POST['city'], $_POST['archive_date']); // чтобы не мешали в цикле

            $jsonUpdates = []; // массив id => ['premia' => ..., 'bolnichnye' => ..., 'otpusknye' => ..., 'avans' => ...]
            
            $db->exec('BEGIN TRANSACTION');
            $updated = 0;
            
            foreach ($_POST as $id => $info) {
                $id = (int) $id;
                // Проверяем, что это корректный ID
                if ($id <= 0 || !is_array($info)) {
                    continue;
                }

                $premia     = isset($info['premia'])     ? (float)$info['premia']     : 0;
                $bolnichnye = isset($info['bolnichnye']) ? (float)$info['bolnichnye'] : 0;
                $otpusknye  = isset($info['otpusknye'])  ? (float)$info['otpusknye']  : 0;
                $avans      = isset($info['avans'])      ? (float)$info['avans']      : 0;

                
                $stmt->bindValue(':id',    $id,             SQLITE3_INTEGER);
                // В базе данных денежные поля имеют тип REAL, поэтому используем float
                $stmt->bindValue(':premia',     $premia,     SQLITE3_FLOAT);
                $stmt->bindValue(':bolnichnye', $bolnichnye, SQLITE3_FLOAT);
                $stmt->bindValue(':otpusknye',  $otpusknye,  SQLITE3_FLOAT);
                $stmt->bindValue(':avans',      $avans,      SQLITE3_FLOAT);
                $stmt->execute();
                $stmt->reset();
                $updated++;
                
                // Заполняем массив для обновления JSON
                $jsonUpdates[$id] = [
                    'premia'     => $premia,
                    'bolnichnye' => $bolnichnye,
                    'otpusknye'  => $otpusknye,
                    'avans'      => $avans
                ];
            }
            
            $db->exec('COMMIT');
            $stmt->close();
            // ─── Обновление исходного JSON-файла архива ────────────────
            if ($podr !== '' && $archive_date !== '' && !empty($jsonUpdates)) {
                if (!defined('ARCHIVE_DIR')) {
                    define('ARCHIVE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'archive');
                }
                
                $citySafe = archiveSanitizeCity($podr);
                // Формируем имя файла напрямую: YYYY-MM-DD_city.json
                // где YYYY-MM-DD — это archive_date (дата создания архива)
                $jsonPath = ARCHIVE_DIR . DIRECTORY_SEPARATOR . $archive_date . '_' . $citySafe . '.json';
                
                if (file_exists($jsonPath) && is_writable($jsonPath)) {
                    $jsonContent = file_get_contents($jsonPath);
                    $data = json_decode($jsonContent, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Подстраховка: ключи могут быть с пробелами в конце
                        $empKey = array_key_exists('employees ', $data) ? 'employees ' : (array_key_exists('employees', $data) ? 'employees' : null);
                        
                        if ($empKey !== null) {
                            $changed = false;
                            foreach ($data[$empKey] as $divName => $divStaff) {
                                foreach ($divStaff as $fio => $empData) {
                                    // Подстраховка от пробелов в концах ключей JSON
                                    $idKey         = array_key_exists('id ', $empData)        ? 'id '        : 'id';
                                    // ✅ Поддержка как нового 'Премия', так и старого 'Доп.премия' (с пробелом и без)
                                    $premiaKey = 'Премия'; // значение по умолчанию
                                    if (array_key_exists('Премия ', $empData)) {
                                        $premiaKey = 'Премия ';
                                    } elseif (array_key_exists('Премия', $empData)) {
                                        $premiaKey = 'Премия';
                                    } elseif (array_key_exists('Доп.премия ', $empData)) {
                                        $premiaKey = 'Доп.премия ';
                                    } elseif (array_key_exists('Доп.премия', $empData)) {
                                        $premiaKey = 'Доп.премия';
                                    }
                                    $bolnichnyeKey = array_key_exists('Больничные ', $empData)? 'Больничные ': 'Больничные';
                                    $otpusknyeKey  = array_key_exists('Отпускные ', $empData) ? 'Отпускные ' : 'Отпускные';
                                    $avansKey      = array_key_exists('Аванс ', $empData)     ? 'Аванс '     : 'Аванс';
                                    $itogoKey      = array_key_exists('Итого ЗП ', $empData)  ? 'Итого ЗП '  : 'Итого ЗП';
                                    $nrKey         = array_key_exists('На руки ', $empData)   ? 'На руки '   : 'На руки';
                                    $vsegoKey      = array_key_exists('Всего за мес. ', $empData) ? 'Всего за мес. ' : 'Всего за мес.';
                                    
                                    $empId = isset($empData[$idKey]) ? (int)$empData[$idKey] : 0;
                                    
                                    if (isset($jsonUpdates[$empId])) {
                                        $update = $jsonUpdates[$empId];

                                        // Обновляем введённые значения в JSON
                                        $data[$empKey][$divName][$fio][$premiaKey]     = $update['premia'];
                                        $data[$empKey][$divName][$fio][$bolnichnyeKey] = $update['bolnichnye'];
                                        $data[$empKey][$divName][$fio][$otpusknyeKey]  = $update['otpusknye'];
                                        $data[$empKey][$divName][$fio][$avansKey]      = $update['avans'];
                                        
                                        // Пересчёт: Итого ЗП =
                                        $stavka      = isset($empData['Ставка'])             ? (float)$empData['Ставка']             : 0;
                                        $zp_chasy    = isset($empData['ЗП Часы'])            ? (float)$empData['ЗП Часы']            : 0;
                                        $zp_ktu      = isset($empData['ЗП КТУ'])             ? (float)$empData['ЗП КТУ']             : 0;
                                        $stroitelnye = isset($empData['Строительные работы'])? (float)$empData['Строительные работы']: 0;
                                        $nadbavki    = isset($empData['Надбавки'])           ? (float)$empData['Надбавки']           : 0;
                                        $uderjanie   = isset($empData['Удержания'])          ? (float)$empData['Удержания']          : 0;
                                        $itogo = $stavka + $zp_chasy + $zp_ktu + $stroitelnye + $nadbavki + $update['premia'] - $uderjanie;
                                        
                                        // Пересчёт: На руки = Итого ЗП - Аванс
                                        $na_ruki = $itogo - $update['avans'];
                                        $data[$empKey][$divName][$fio][$nrKey] = $na_ruki;

                                        // 3. Всего за мес.
                                        $vsego = $na_ruki + $update['bolnichnye'] + $update['otpusknye'];
                                        $data[$empKey][$divName][$fio][$vsegoKey] = $vsego;
                                        
                                        
                                        $changed = true;
                                    }
                                }
                            }
                            if ($changed) {
                                $jsonOut = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                file_put_contents($jsonPath, $jsonOut);
                            }
                        }
                    }
                }
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Неизвестный тип: {$type}"]);
            $db->close();
            exit;
    }

    // ─── Успех ───────────────────────────────────────────────
    echo json_encode([
        'success' => true,
        'message' => 'Сохранено',
        'rows'    => $updated ?? null,
    ]);

} catch (Throwable $e) {
    // Откатываем транзакцию если была начата
    try { $db->exec('ROLLBACK'); } catch (Throwable $ignored) {}

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка выполнения: ' . $e->getMessage(),
    ]);
} finally {
    $db->close();
}

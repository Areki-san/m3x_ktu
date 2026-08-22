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
                    dop_premia  = :dop_premia,
                    otpusknye   = :otpusknye,
                    bolnichnye  = :bolnichnye,
                    uderjanie   = :uderjanie,
                    avans       = :avans
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

                $stmt->bindValue(':id',          $id,                          SQLITE3_INTEGER);
				$stmt->bindValue(':oklad',       (int)($info['oklad']       ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':stavka',      (int)($info['stavka']      ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':stavka_chas', (int)($info['stavka_chas'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':nadbavka',    (int)($info['nadbavka']    ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':dop_premia',  (int)($info['dop_premia']  ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':otpusknye',   (int)($info['otpusknye']   ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':bolnichnye',  (int)($info['bolnichnye']  ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':uderjanie',   (int)($info['uderjanie']   ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(':avans',       (int)($info['avans']       ?? 0), SQLITE3_INTEGER);

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
        case 'ktu':
            $stmt = $db->prepare('UPDATE OR IGNORE ktu SET work_ktu = :work_ktu WHERE work_id = :work_id');
            if ($stmt === false) {
                throw new RuntimeException('Не удалось подготовить запрос: ' . $db->lastErrorMsg());
            }

            $db->exec('BEGIN TRANSACTION');

            foreach ($_POST as $workId => $ktu) {
                $stmt->bindValue(':work_id',  (int) $workId, SQLITE3_INTEGER);
                $stmt->bindValue(':work_ktu', (int) $ktu,    SQLITE3_INTEGER);
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
                $stmt->bindValue(':stavka', (int) $stavka,   SQLITE3_INTEGER);
                $stmt->execute();
                $stmt->reset();
            }

            $db->exec('COMMIT');
            $stmt->close();
            break;

        // ── Обновление ТОЛЬКО аванса (безопасно, не трогает другие поля) + перезапись JSON ──
        case 'avans':
            $stmt = $db->prepare('
                UPDATE employees SET 
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

            $jsonUpdates = []; // массив id => новый аванс
            
            $db->exec('BEGIN TRANSACTION');
            $updated = 0;
            
            foreach ($_POST as $id => $info) {
                $id = (int) $id;
                // Проверяем, что это корректный ID и передан именно аванс
                if ($id <= 0 || !is_array($info) || !isset($info['avans'])) {
                    continue; 
                }
                
                $stmt->bindValue(':id',    $id,             SQLITE3_INTEGER);
                // В базе данных avans имеет тип REAL, поэтому используем float
                $stmt->bindValue(':avans', (float)$info['avans'], SQLITE3_FLOAT); 
                
                $stmt->execute();
                $stmt->reset();
                $updated++;

                // ВАЖНО: заполняем массив для обновления JSON
                $jsonUpdates[$id] = (float)$info['avans'];
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
                                    $idKey    = array_key_exists('id ', $empData) ? 'id ' : 'id';
                                    $avansKey = array_key_exists('Аванс ', $empData) ? 'Аванс ' : 'Аванс';
                                    $itogoKey = array_key_exists('Итого ', $empData) ? 'Итого ' : 'Итого';
                                    $nrKey    = array_key_exists('На руки ', $empData) ? 'На руки ' : 'На руки';
                                    
                                    $empId = isset($empData[$idKey]) ? (int)$empData[$idKey] : 0;
                                    
                                    if (isset($jsonUpdates[$empId])) {
                                        $newAvans = $jsonUpdates[$empId];
                                        $data[$empKey][$divName][$fio][$avansKey] = $newAvans;
                                        
                                        // Пересчёт: На руки = Итого - Аванс
                                        $itogo = isset($empData[$itogoKey]) ? (float)$empData[$itogoKey] : 0;
                                        $data[$empKey][$divName][$fio][$nrKey] = $itogo - $newAvans;
                                        
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

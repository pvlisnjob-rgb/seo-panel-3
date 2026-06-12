<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'get_data') {
        // Получаем проекты
        $stmt = $pdo->query("SELECT * FROM projects ORDER BY id DESC");
        $projects = $stmt->fetchAll();

        // Получаем задачи
        $stmt = $pdo->query("SELECT * FROM tasks ORDER BY id DESC");
        $tasks = $stmt->fetchAll();

        // Получаем настройки (GSC, XP)
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settingsRows = $stmt->fetchAll();
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        echo json_encode([
            'success' => true,
            'projects' => $projects,
            'tasks' => $tasks,
            'settings' => $settings
        ]);

    } elseif ($action === 'save_project') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $priority = $_POST['priority'] ?? '';
        $revenue = $_POST['revenue'] ?? 0;
        $expenses = $_POST['expenses'] ?? 0;
        $hours = $_POST['hours'] ?? 0;

        // Валидация обязательных полей
        if (empty($name)) {
            throw new Exception('Название проекта обязательно');
        }

        if (empty($priority)) {
            throw new Exception('Приоритет обязателен');
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE projects SET name=?, priority=?, revenue=?, expenses=?, hours=? WHERE id=?");
            $stmt->execute([$name, $priority, $revenue, $expenses, $hours, $id]);
            echo json_encode(['success' => true, 'message' => 'Проект успешно обновлен']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO projects (name, priority, revenue, expenses, hours) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $priority, $revenue, $expenses, $hours]);
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Проект успешно создан', 'project_id' => $newId]);
        }

    } elseif ($action === 'delete_project') {
        $id = $_POST['id'];
        if (empty($id)) {
            throw new Exception('ID проекта не указан');
        }
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'save_task') {
        $id = $_POST['id'] ?? null;
        $projectId = $_POST['project_id'] ?? $_POST['projectId'] ?? null;
        $title = trim($_POST['title'] ?? $_POST['desc'] ?? '');
        $category = $_POST['category'] ?? '';
        $time = $_POST['time_spent'] ?? $_POST['time'] ?? 0;
        $done = $_POST['is_done'] ?? $_POST['done'] ?? 0;

        // Валидация
        if (empty($projectId)) {
            throw new Exception('Проект не выбран');
        }
        if (empty($title)) {
            throw new Exception('Название задачи обязательно');
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE tasks SET project_id=?, title=?, category=?, time_spent=?, is_done=? WHERE id=?");
            $stmt->execute([$projectId, $title, $category, $time, $done, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tasks (project_id, title, category, time_spent, is_done, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$projectId, $title, $category, $time, $done]);

            // Начисляем XP за новую задачу
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = setting_value + 5 WHERE setting_key = 'user_xp'");
            $stmt->execute();
        }
        echo json_encode(['success' => true, 'message' => 'Задача успешно сохранена']);

    } elseif ($action === 'toggle_task') {
        $id = $_POST['id'];
        $isDone = $_POST['is_done'] ?? $_POST['isDone'] ?? 0;

        if (empty($id)) {
            throw new Exception('ID задачи не указан');
        }

        $stmt = $pdo->prepare("UPDATE tasks SET is_done=? WHERE id=?");
        $stmt->execute([$isDone, $id]);

        // Начисляем/снимаем XP
        $xpChange = $isDone ? 10 : -10;
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = setting_value + ? WHERE setting_key = 'user_xp'");
        $stmt->execute([$xpChange]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_task') {
        $id = $_POST['id'];
        if (empty($id)) {
            throw new Exception('ID задачи не указан');
        }
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'save_settings') {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';

        if (empty($key)) {
            throw new Exception('Ключ настройки не указан');
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'update_project_gsc') {
        $projectId = $_POST['project_id'] ?? null;
        $gscSiteUrl = $_POST['gsc_site_url'] ?? '';
        $gscClientId = $_POST['gsc_client_id'] ?? '';

        if (empty($projectId)) {
            throw new Exception('ID проекта не указан');
        }

        $stmt = $pdo->prepare("UPDATE projects SET gsc_site_url = ? WHERE id = ?");
        $stmt->execute([$gscSiteUrl, $projectId]);

        // Сохраняем Client ID если указан
        if (!empty($gscClientId)) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['gsc_client_id', $gscClientId, $gscClientId]);
        }

        echo json_encode(['success' => true]);

    } elseif ($action === 'get_gsc_data') {
        $siteUrl = $_POST['site_url'] ?? '';

        if (empty($siteUrl)) {
            throw new Exception('URL сайта не указан');
        }

        // Получаем токен из настроек
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gsc_token'");
        $stmt->execute();
        $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $token = $tokenRow['setting_value'] ?? '';

        if (empty($token)) {
            throw new Exception('GSC токен не найден. Сначала подключите Google аккаунт.');
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-90 days'));

        $reportBody = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['date'],
            'rowLimit' => 90
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/webmasters/v3/sites/" . urlencode($siteUrl) . "/searchAnalytics/query?access_token=" . $token);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reportBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            throw new Exception('Ошибка GSC API: ' . ($errorData['error']['message'] ?? 'Неизвестная ошибка'));
        }

        $data = json_decode($response, true);
        echo json_encode(['success' => true, 'rows' => $data['rows'] ?? []]);

    } else {
        throw new Exception('Неизвестное действие: ' . htmlspecialchars($action));
    }

} catch (PDOException $e) {
    // Логируем ошибку базы данных
    error_log("Database error in api.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
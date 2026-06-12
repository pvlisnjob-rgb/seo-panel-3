<?php
// project_view.php

// 1. ВКЛЮЧЕНИЕ ОТЛАДКИ (Удалите эти строки, когда все заработает)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ
// ЗАМЕНИТЕ 'config.php' НА ПУТЬ К ВАШЕМУ ФАЙЛУ С ПОДКЛЮЧЕНИЕМ К БД
// Если у вас нет отдельного config.php, раскомментируйте код ниже и впишите свои данные:

$host = 'localhost';
$db   = 'seopl;
$user = 'seopl';
$pass = 'Roma1234';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $opt);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}


// Если у вас есть файл подключения, используйте его:
//require_once 'config.php'; 

// Получаем ID проекта
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    die("Некорректный ID проекта.");
}

// 3. ПОЛУЧЕНИЕ ДАННЫХ ПРОЕКТА
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Проект не найден.");
}

// 4. ОБРАБОТКА AJAX ЗАПРОСОВ (Добавление/Удаление задач)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_task') {
        $title = $_POST['title'] ?? '';
        $desc = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? 'general';
        $plan_time = (int)($_POST['plan_time'] ?? 0);
        $fact_time = (int)($_POST['fact_time'] ?? 0);
        $cost = (float)($_POST['cost'] ?? 0);
        $expense = (float)($_POST['expense'] ?? 0);
        
        if (!empty($title)) {
            $sql = "INSERT INTO tasks (project_id, title, description, category, time_spent, cost, expenses, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            // Примечание: если в БД нет поля expenses в tasks, добавьте его или используйте cost как расход
            // Для примера предполагаем, что мы пишем в time_spent факт, а стоимость и расходы считаем отдельно
            
            // Адаптируем под вашу схему БД (если нет поля expenses в tasks, используем только cost)
            // Допустим, time_spent - это факт. План пока не храним отдельно в БД, если нет колонки.
            // Добавим заглушку, если колонки нет. Лучше обновить БД.
            
            try {
                $stmt = $pdo->prepare("INSERT INTO tasks (project_id, title, description, category, time_spent, cost, is_done) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$project_id, $title, $desc, $category, $fact_time, $cost]);
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Введите название задачи']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_task') {
        $task_id = (int)$_POST['task_id'];
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND project_id = ?");
        $stmt->execute([$task_id, $project_id]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($_POST['action'] === 'toggle_status') {
        $task_id = (int)$_POST['task_id'];
        $is_done = (int)$_POST['is_done'];
        $stmt = $pdo->prepare("UPDATE tasks SET is_done = ? WHERE id = ? AND project_id = ?");
        $stmt->execute([$is_done, $task_id, $project_id]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// 5. ПОЛУЧЕНИЕ СТАТИСТИКИ И ЗАДАЧ
// Задачи
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$project_id]);
$tasks = $stmt->fetchAll();

// Подсчет метрик
$total_tasks = count($tasks);
$done_tasks = 0;
$total_fact_time = 0;
$total_cost_client = 0; // Сколько платит клиент (доход)
$total_expenses = 0;    // Наши расходы (зарплаты, ссылки и т.д.)

// Если в БД есть отдельные поля для дохода и расхода в задачах, используйте их.
// Сейчас используем: cost = доход, expenses (если есть) или фиксированный % как расход.
// Для простоты: пусть cost - это цена для клиента. Расходы посчитаем как 0, если нет поля.

foreach ($tasks as $task) {
    if ($task['is_done'] == 1) {
        $done_tasks++;
    }
    $total_fact_time += (int)$task['time_spent'];
    $total_cost_client += (float)$task['cost'];
    // Если есть поле expenses в task, добавьте: $total_expenses += (float)$task['expenses'];
}

// Данные из проекта (общий бюджет/расходы)
$project_revenue = (float)($project['revenue'] ?? 0);
$project_expenses = (float)($project['expenses'] ?? 0);

// Итоговые цифры
$final_revenue = $project_revenue + $total_cost_client;
$final_expenses = $project_expenses; // + $total_expenses (если считаете расходы по задачам)
$profit = $final_revenue - $final_expenses;
$roi = ($final_expenses > 0) ? (($profit / $final_expenses) * 100) : 0;

// 6. ДАННЫЕ GSC (Заглушка, так как нужна реальная интеграция API)
// Здесь мы просто берем данные из таблицы gsc_data, если они есть
$stmt_gsc = $pdo->prepare("SELECT SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position 
                           FROM gsc_data WHERE project_id = ?");
$stmt_gsc->execute([$project_id]);
$gsc_stats = $stmt_gsc->fetch();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проект: <?php echo htmlspecialchars($project['name']); ?></title>
    <!-- Подключаем ваши стили. Замените путь на актуальный -->
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Базовые стили, если внешний файл не подгрузится */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f4f6f9; display: flex; }
        .sidebar { width: 250px; background: #2c3e50; color: #fff; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .sidebar a { color: #bdc3c7; text-decoration: none; display: block; padding: 10px 0; }
        .sidebar a:hover { color: #fff; }
        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
        .header { background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #7f8c8d; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .positive { color: #27ae60; }
        .negative { color: #c0392b; }
        
        .section { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #3498db; color: #fff; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-success { background: #27ae60; color: #fff; }
        
        /* Форма задачи */
        .task-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; margin-bottom: 5px; color: #555; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        
        /* Таблица задач */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #555; font-size: 13px; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; }
        .status-done { background: #d4edda; color: #155724; }
        .status-todo { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<!-- БОКОВОЕ МЕНЮ -->
<div class="sidebar">
    <h2>SEO Panel</h2>
    <a href="index.php"><i class="fas fa-home"></i> Главная</a>
    <a href="projects.php"><i class="fas fa-folder"></i> Проекты</a>
    <a href="tasks.php"><i class="fas fa-list"></i> Все задачи</a>
    <a href="analytics.php"><i class="fas fa-chart-line"></i> Аналитика</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Настройки</a>
</div>

<div class="main-content">
    <!-- ШАПКА -->
    <div class="header">
        <h1><?php echo htmlspecialchars($project['name']); ?></h1>
        <div>
            <a href="projects.php" class="btn btn-primary">Назад к списку</a>
            <?php if(!empty($project['url'])): ?>
                <a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank" class="btn btn-success"><i class="fas fa-external-link-alt"></i> Сайт</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ФИНАНСЫ И KPI -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Доход (Прогноз)</h3>
            <div class="value"><?php echo number_format($final_revenue, 2); ?> $</div>
        </div>
        <div class="stat-card">
            <h3>Расходы</h3>
            <div class="value negative"><?php echo number_format($final_expenses, 2); ?> $</div>
        </div>
        <div class="stat-card">
            <h3>Прибыль</h3>
            <div class="value <?php echo $profit >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo number_format($profit, 2); ?> $
            </div>
        </div>
        <div class="stat-card">
            <h3>ROI</h3>
            <div class="value <?php echo $roi >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo number_format($roi, 1); ?> %
            </div>
        </div>
        <div class="stat-card">
            <h3>Затрачено времени</h3>
            <div class="value"><?php echo round($total_fact_time / 60, 1); ?> ч.</div>
        </div>
        <div class="stat-card">
            <h3>GSC Клики (Всего)</h3>
            <div class="value"><?php echo $gsc_stats['clicks'] ?? 0; ?></div>
        </div>
    </div>

    <!-- GSC ИНТЕГРАЦИЯ (График или таблица) -->
    <div class="section">
        <h3><i class="fab fa-google"></i> Google Search Console</h3>
        <p>Данные по проекту: <?php echo htmlspecialchars($project['gsc_property'] ?? 'Не привязано'); ?></p>
        <div style="display: flex; gap: 20px; margin-top: 10px;">
            <div>Показы: <strong><?php echo $gsc_stats['impressions'] ?? 0; ?></strong></div>
            <div>CTR: <strong><?php echo $gsc_stats['clicks'] && $gsc_stats['impressions'] ? round(($gsc_stats['clicks']/$gsc_stats['impressions'])*100, 2) : 0; ?>%</strong></div>
            <div>Средняя позиция: <strong><?php echo number_format($gsc_stats['position'] ?? 0, 2); ?></strong></div>
        </div>
        <!-- Сюда можно добавить Canvas для графика Chart.js -->
    </div>

    <!-- УПРАВЛЕНИЕ ЗАДАЧАМИ -->
    <div class="section">
        <h3>Задачи проекта</h3>
        
        <!-- Форма добавления -->
        <form id="addTaskForm" class="task-form">
            <input type="hidden" name="action" value="add_task">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Название задачи</label>
                <input type="text" name="title" required placeholder="Что нужно сделать?">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Описание / Комментарий</label>
                <textarea name="description" rows="2" placeholder="Детали задачи..."></textarea>
            </div>
            <div class="form-group">
                <label>Категория</label>
                <select name="category">
                    <option value="content">Контент</option>
                    <option value="links">Ссылки</option>
                    <option value="tech">Техническое</option>
                    <option value="audit">Аудит</option>
                    <option value="other">Другое</option>
                </select>
            </div>
            <div class="form-group">
                <label>План время (мин)</label>
                <input type="number" name="plan_time" placeholder="0">
            </div>
            <div class="form-group">
                <label>Факт время (мин)</label>
                <input type="number" name="fact_time" placeholder="0">
            </div>
            <div class="form-group">
                <label>Стоимость для клиента ($)</label>
                <input type="number" step="0.01" name="cost" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Расходы ($)</label>
                <input type="number" step="0.01" name="expense" placeholder="0.00">
            </div>
            <div class="form-group" style="justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">Добавить задачу</button>
            </div>
        </form>

        <!-- Список задач -->
        <table>
            <thead>
                <tr>
                    <th>Статус</th>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>Время (факт)</th>
                    <th>Стоимость</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody id="tasksList">
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td>
                        <span class="status-badge <?php echo $task['is_done'] ? 'status-done' : 'status-todo'; ?>">
                            <?php echo $task['is_done'] ? 'Выполнено' : 'В работе'; ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($task['title']); ?></strong><br>
                        <small style="color:#777;"><?php echo htmlspecialchars($task['description']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($task['category']); ?></td>
                    <td><?php echo $task['time_spent']; ?> мин.</td>
                    <td><?php echo number_format($task['cost'], 2); ?> $</td>
                    <td>
                        <button class="btn btn-success" onclick="toggleStatus(<?php echo $task['id']; ?>, <?php echo $task['is_done'] ? 0 : 1; ?>)">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger" onclick="deleteTask(<?php echo $task['id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Обработка формы
    document.getElementById('addTaskForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('project_view.php?id=<?php echo $project_id; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                location.reload();
            } else {
                alert('Ошибка: ' + data.message);
            }
        });
    });

    // Удаление задачи
    function deleteTask(id) {
        if(confirm('Удалить задачу?')) {
            const formData = new FormData();
            formData.append('action', 'delete_task');
            formData.append('task_id', id);
            
            fetch('project_view.php?id=<?php echo $project_id; ?>', {
                method: 'POST',
                body: formData
            }).then(() => location.reload());
        }
    }

    // Переключение статуса
    function toggleStatus(id, status) {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('task_id', id);
        formData.append('is_done', status);
        
        fetch('project_view.php?id=<?php echo $project_id; ?>', {
            method: 'POST',
            body: formData
        }).then(() => location.reload());
    }
</script>

</body>
</html>
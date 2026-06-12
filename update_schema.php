<?php
/**
 * Скрипт обновления структуры базы данных
 * Запустите этот файл один раз через браузер: http://your-domain.com/update_schema.php
 */

require_once 'db.php';

echo "<h1>Обновление структуры базы данных SEO Panel</h1>";

try {
    // Обновляем таблицу projects - добавляем отсутствующие колонки
    $pdo->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS priority ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium'");
    echo "<p style='color: green;'>✓ Колонка 'priority' добавлена в таблицу 'projects'</p>";

    $pdo->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS revenue DECIMAL(10,2) DEFAULT 0");
    echo "<p style='color: green;'>✓ Колонка 'revenue' добавлена в таблицу 'projects'</p>";

    $pdo->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS expenses DECIMAL(10,2) DEFAULT 0");
    echo "<p style='color: green;'>✓ Колонка 'expenses' добавлена в таблицу 'projects'</p>";

    $pdo->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS hours INT DEFAULT 0");
    echo "<p style='color: green;'>✓ Колонка 'hours' добавлена в таблицу 'projects'</p>";

    // Обновляем таблицу tasks - добавляем отсутствующие колонки
    $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT ''");
    echo "<p style='color: green;'>✓ Колонка 'category' добавлена в таблицу 'tasks'</p>";

    $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS time_spent INT DEFAULT 0");
    echo "<p style='color: green;'>✓ Колонка 'time_spent' добавлена в таблицу 'tasks'</p>";

    $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS is_done TINYINT(1) DEFAULT 0");
    echo "<p style='color: green;'>✓ Колонка 'is_done' добавлена в таблицу 'tasks'</p>";

    // Проверяем и исправляем таблицу settings
    $columns = $pdo->query("SHOW COLUMNS FROM settings LIKE 'id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
        echo "<p style='color: green;'>✓ Колонка 'id' добавлена в таблицу 'settings'</p>";
    } else {
        echo "<p style='color: green;'>✓ Колонка 'id' уже существует в таблице 'settings'</p>";
    }

    // Финальная проверка
    echo "<hr>";
    echo "<h2>Финальная проверка:</h2>";

    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['id', 'name', 'priority', 'revenue', 'expenses', 'hours'];
    $missingColumns = array_diff($requiredColumns, $columns);
    if (!empty($missingColumns)) {
        echo "<p style='color: red;'>⚠ В таблице projects всё ещё отсутствуют: " . implode(', ', $missingColumns) . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Таблица 'projects' теперь имеет все необходимые колонки</p>";
    }

    $columns = $pdo->query("SHOW COLUMNS FROM tasks")->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['id', 'project_id', 'description', 'category', 'time_spent', 'is_done'];
    $missingColumns = array_diff($requiredColumns, $columns);
    if (!empty($missingColumns)) {
        echo "<p style='color: red;'>⚠ В таблице tasks всё ещё отсутствуют: " . implode(', ', $missingColumns) . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Таблица 'tasks' теперь имеет все необходимые колонки</p>";
    }

    echo "<hr>";
    echo "<h2 style='color: green;'>✓ Обновление завершено успешно!</h2>";
    echo "<p>Теперь вы можете перейти к <a href='seo-manager.php'>панели управления</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Ошибка базы данных:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Проверьте правильность настроек подключения в файле db.php</p>";
}
?>
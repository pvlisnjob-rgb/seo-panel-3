<?php
// setup_auth.php - Запустить ОДИН РАЗ для создания администратора
require_once 'db.php';

// НАСТРОЙКИ: Измените логин и пароль здесь перед запуском!
$admin_login = 'admin';
$admin_password = 'changeme123'; // ЗАМЕНИТЕ НА СВОЙ СЛОЖНЫЙ ПАРОЛЬ!

// Хэшируем пароль
$password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

try {
    // Создаем таблицу пользователей, если нет
    $stmt = $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Проверяем, есть ли уже пользователи
    $check = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    if ($check == 0) {
        // Если пользователей нет, создаем админа
        $stmt = $pdo->prepare("INSERT INTO users (login, password) VALUES (:login, :password)");
        $stmt->execute([
            'login' => $admin_login,
            'password' => $password_hash
        ]);
        echo "✓ Пользователь создан успешно!<br>";
        echo "Логин: <b>$admin_login</b><br>";
        echo "Пароль: <b>$admin_password</b><br>";
        echo "<hr>";
        echo "⚠️ <b>ВАЖНО:</b> Удалите этот файл (setup_auth.php) с сервера сразу после использования!";
    } else {
        echo "⚠️ Пользователи уже существуют в базе данных. Создание пропущено.<br>";
        echo "Если вы забыли пароль, очистите таблицу `users` в базе данных и запустите этот скрипт снова.";
    }

} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>
<?php
// auth.php - Подключать в начало каждого защищенного файла
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Функция для получения данных текущего пользователя
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'],
        'login' => $_SESSION['user_login']
    ];
}

// Функция для выхода
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
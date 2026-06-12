-- Добавление отсутствующих колонок в таблицу projects
ALTER TABLE projects
ADD COLUMN IF NOT EXISTS priority VARCHAR(20) DEFAULT 'medium',
ADD COLUMN IF NOT EXISTS revenue DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS expenses DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS hours INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS gsc_site_url VARCHAR(500) DEFAULT NULL;

-- Добавление отсутствующих колонок в таблицу tasks
ALTER TABLE tasks
ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'general',
ADD COLUMN IF NOT EXISTS time_spent INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_done TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS title VARCHAR(255) DEFAULT '';

-- Если колонка description существует, но нам нужна title, создаем title и копируем данные
-- (выполняется вручную при необходимости)
-- ALTER TABLE tasks ADD COLUMN title VARCHAR(255);
-- UPDATE tasks SET title = description WHERE title IS NULL;

-- Добавление первичного ключа в таблицу settings, если он отсутствует
-- Исправленная версия для избежания ошибки #1075
ALTER TABLE settings
ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- Если таблица settings уже имеет id с AUTO_INCREMENT но без PRIMARY KEY, используем:
-- ALTER TABLE settings MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY;
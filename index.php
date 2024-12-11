<?php
header('Content-Type: text/html; charset=utf-8');

// Настройки подключения к базе данных
$host = '81.31.247.100';
$db = 'RprTTCmZ'; // Замените на ваше имя базы данных
$user = 'DNnJIR'; // Замените на ваше имя пользователя
$pass = 'KmXlLZbaWKGlUjUT'; // Замените на ваш пароль

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Подключение к БД отсутствует. Ошибка:
    die(json_encode(["success" => false, "error" => "Could not connect to the database: " . $e->getMessage()]));
}

// Получение HTTP метода
$method = $_SERVER['REQUEST_METHOD'];

// Обработка маршрутов
switch ($method) {
    case 'POST':
        /*
         POST /create
            {
              "full_name": "some_name",
              "role": "some_role",
              "efficiency": some_efficiency
            }
         */

        // Создание нового пользователя
        $data = json_decode(file_get_contents("php://input"), true);

        // Валидация входных данных
        if (!isset($data['full_name']) || empty($data['full_name'])) {
            // Поле Full name обязательное для заполнения
            echo json_encode(["success" => false, "error" => "Full name is required"]);
            exit;
        }

        if (!isset($data['role']) || empty($data['role'])) {
            // Поле Role обязательное для заполнения
            echo json_encode(["success" => false, "error" => "Role is required"]);
            exit;
        }

        if (!isset($data['efficiency']) || !is_numeric($data['efficiency'])) {
            // Поле Efficiency должно быть числом
            echo json_encode(["success" => false, "error" => "Efficiency must be a number"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (full_name, role, efficiency) VALUES (?, ?, ?)");
        $stmt->execute([$data['full_name'], $data['role'], $data['efficiency']]);
        $id = $pdo->lastInsertId();

        echo json_encode(["success" => true, "result" => ["id" => $id]]);
    break;

    case 'GET':
        // Получение пользователей
        if (isset($_GET['id'])) {
            if (!is_numeric($_GET['id'])) {
                // Поле ID должно быть числом
                echo json_encode(["success" => false, "error" => "ID must be a number"]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $result = $user ? [$user] : [];
        } elseif (isset($_GET['full_name']) || isset($_GET['role']) || isset($_GET['efficiency'])) {
            // Фильтрация
            $fields = [];
            $values = [];

            $where = ' WHERE ';

            if (!empty($_GET['full_name'])) {
                $fields[] = '`full_name` = ?';
                $values[] = $_GET['full_name'];
            }

            if (!empty($_GET['role'])) {
                $fields[] = '`role` = ?';
                $values[] = $_GET['role'];
            }

            if (!empty($_GET['efficiency']) && is_numeric($_GET['efficiency'])) {
                $fields[] = '`efficiency` = ?';
                $values[] = $_GET['efficiency'];
            } elseif (!empty($_GET['efficiency'])) {
                // Поле Efficiency должно быть числом
                echo json_encode(["success" => false, "error" => "Efficiency must be a number"]);
                exit;
            }

            $where .= ("(" . implode(") AND (", $fields) . ")");

            $stmt = $pdo->prepare("SELECT * FROM users " . $where);
            $stmt->execute($values);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM users");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(["success" => true, "result" => ["users" => $result]]);
    break;

    case 'PATCH':
        /*
         PATCH /update/<user_id>
            {
              "full_name": "new_name",
              "role": "new_role"
            }
         */

        // Обновление пользователя
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            // Поле ID должно быть числом
            echo json_encode(["success" => false, "error" => "ID must be a number"]);
            exit;
        }

        $id = $_GET['id'];
        $fields = [];
        $values = [];

        if (isset($data['full_name']) && !empty($data['full_name'])) {
            $fields[] = "full_name = ?";
            $values[] = $data['full_name'];
        }

        if (isset($data['role']) && !empty($data['role'])) {
            $fields[] = "role = ?";
            $values[] = $data['role'];
        }

        if (isset($data['efficiency']) && is_numeric($data['efficiency'])) {
            $fields[] = "efficiency = ?";
            $values[] = $data['efficiency'];
        }

        if ($fields) {
            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?");
            $stmt->execute($values);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "result" => $user]);
        } else {
            // Для обновления отсутствуют входные данные
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
        }
    break;

    case 'DELETE':
        // Удаление пользователя
        if (isset($_GET['id'])) {
            if (!is_numeric($_GET['id'])) {
                // Поле ID должно быть числом
                echo json_encode(["success" => false, "error" => "ID must be a number"]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_GET['id']]);

                echo json_encode(["success" => true, "result" => $user]);
            } else {
                // Пользователь не найден
                echo json_encode(["success" => false, "error" => "User not found"]);
            }
        } else {
            // Удаление всех пользователей
            $stmt = $pdo->prepare("DELETE FROM users");
            $stmt->execute();

            echo json_encode(["success" => true]);
        }
    break;

    default:
        // Метод не доступен
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
    break;
}

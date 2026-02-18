<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$file = 'todos.json';

if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo file_get_contents($file);
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $todos = json_decode(file_get_contents($file), true);

    if (isset($input['task'])) {
        // ADD
        $newTodo = [
            'id' => uniqid(),
            'task' => $input['task'],
            'done' => false
        ];
        $todos[] = $newTodo;
        file_put_contents($file, json_encode($todos));
        echo json_encode(['status' => 'success']);

    } elseif (isset($input['id']) && isset($input['action'])) {
        $id = $input['id'];
        $action = $input['action'];

        if ($action === 'toggle') {
            // TOGGLE
            foreach ($todos as &$todo) {
                if ($todo['id'] === $id) {
                    $todo['done'] = !$todo['done'];
                    break;
                }
            }
        } elseif ($action === 'delete') {
            // DELETE
            $todos = array_filter($todos, function($todo) use ($id) {
                return $todo['id'] !== $id;
            });
            $todos = array_values($todos);
        }

        file_put_contents($file, json_encode($todos));
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid Data']);
    }
}
?>

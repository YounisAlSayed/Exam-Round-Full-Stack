<?php

// CLI-only browser fixture; it does not connect to the application database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$input = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
define('BASE_PATH', '');
$_SESSION = ['user' => ['id' => 10, 'role' => 'student', 'name' => 'Test Student']];
$exam = [
    'exam_id' => 1, 'title' => 'Exam Taking Verification',
    'start_date' => date('Y-m-d H:i:s', time() - 120),
    'end_date' => date('Y-m-d H:i:s', time() + ($input['remaining'] ?? 3600)),
];
$questions = [];
$choices = [];
foreach ([3, 1, 5, 2, 4] as $id) {
    $questions[] = ['id' => $id, 'question' => 'Question content ' . $id, 'question_mark' => 2];
    $choices[$id] = [
        ['id' => $id * 10 + 1, 'choice_text' => 'First answer'],
        ['id' => $id * 10 + 2, 'choice_text' => 'Second answer'],
    ];
}
$page = $input['page'] ?? 1;
$pageSize = $input['pageSize'] ?? 2;
$savedAnswers = $input['answers'] ?? [];
require __DIR__ . '/../backend/views/exams/start.phtml';

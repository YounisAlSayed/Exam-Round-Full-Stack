<?php

// Run with: php tests/question-copy-on-edit.php
// Connection-local temporary tables shadow application tables; real records are never changed.
require __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../backend/models/Questions.php';

if (getenv('QUESTION_TEST_DSN')) {
    $connection = new PDO(getenv('QUESTION_TEST_DSN'), getenv('QUESTION_TEST_USER') ?: 'root', getenv('QUESTION_TEST_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $instance = new ReflectionProperty(App\Utils\Database::class, 'instance');
    $instance->setAccessible(true);
    $instance->setValue(null, $connection);
} else {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../backend')->load();
}
$pdo = App\Utils\Database::getInstance();
$pdo->exec('CREATE TEMPORARY TABLE exams (id INT PRIMARY KEY, teacher_id INT NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE attempts (id INT PRIMARY KEY, exam_id INT NOT NULL, INDEX (exam_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE questions (id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL, question VARCHAR(255) NOT NULL, type VARCHAR(3) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE exam_questions (id INT AUTO_INCREMENT PRIMARY KEY, exam_id INT NOT NULL, question_id INT NOT NULL, question_mark DOUBLE NOT NULL, UNIQUE (exam_id, question_id), INDEX (question_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE choices (id INT AUTO_INCREMENT PRIMARY KEY, question_id INT NOT NULL, choice_text VARCHAR(255) NOT NULL, is_correct TINYINT NOT NULL, one_correct INT GENERATED ALWAYS AS (IF(is_correct, question_id, NULL)) STORED, UNIQUE (one_correct), INDEX (question_id)) ENGINE=InnoDB');

$model = new App\models\Questions();
function expect($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function snapshot(PDO $pdo): array
{
    $result = [];
    foreach (['questions', 'choices', 'exam_questions'] as $table) {
        $result[$table] = $pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll();
    }
    return $result;
}
function fixture(PDO $pdo, bool $shared = true): void
{
    foreach (['choices', 'exam_questions', 'questions', 'attempts', 'exams'] as $table) {
        $pdo->exec("DELETE FROM $table");
    }
    $pdo->exec('INSERT INTO exams VALUES (1, 10), (2, 20), (3, 10)');
    $pdo->exec("INSERT INTO questions (id, course_id, question, type) VALUES (1, 1, 'Original?', 'mc'), (2, 1, 'Unrelated?', 'mc')");
    $pdo->exec("INSERT INTO choices (id, question_id, choice_text, is_correct) VALUES (1, 1, 'A', 1), (2, 1, 'B', 0), (3, 2, 'Unrelated', 1)");
    $pdo->exec('INSERT INTO exam_questions (id, exam_id, question_id, question_mark) VALUES (1, 1, 1, 2), (3, 3, 2, 4)');
    if ($shared) {
        $pdo->exec('INSERT INTO exam_questions (id, exam_id, question_id, question_mark) VALUES (2, 2, 1, 5)');
    }
}
$editedChoices = [
    ['id' => 1, 'text' => 'Edited A', 'is_correct' => 0],
    ['id' => 2, 'text' => 'Edited B', 'is_correct' => 1],
];
$tfChoices = [
    ['id' => null, 'text' => 'True', 'is_correct' => 1],
    ['id' => null, 'text' => 'False', 'is_correct' => 0],
];

fixture($pdo);
$before = snapshot($pdo);
// Attempts in a different exam must not stop copying or affect its question.
$pdo->exec('INSERT INTO attempts VALUES (1, 2)');
$newId = $model->updateForExam(1, 1, 10, 'Edited?', 'mc', 7, $editedChoices);
expect($newId !== 1, 'A shared question must get a new ID.');
expect($pdo->query('SELECT * FROM questions WHERE id = 1')->fetch() === $before['questions'][0], 'Original question changed.');
expect($pdo->query('SELECT * FROM choices WHERE question_id = 1 ORDER BY id')->fetchAll() === array_slice($before['choices'], 0, 2), 'Original choices changed.');
expect((int) $pdo->query('SELECT question_id FROM exam_questions WHERE exam_id = 1')->fetchColumn() === $newId, 'Current exam was not relinked.');
expect((int) $pdo->query('SELECT question_id FROM exam_questions WHERE exam_id = 2')->fetchColumn() === 1, 'Other exam was relinked.');
expect((int) $pdo->query('SELECT question_mark FROM exam_questions WHERE exam_id = 2')->fetchColumn() === 5, 'Other exam mark changed.');
expect((int) $pdo->query('SELECT question_mark FROM exam_questions WHERE exam_id = 1')->fetchColumn() === 7, 'Current exam mark was not saved.');
$newChoices = $pdo->query('SELECT * FROM choices WHERE question_id = ' . $newId . ' ORDER BY id')->fetchAll();
expect(count($newChoices) === 2 && $newChoices[1]['choice_text'] === 'Edited B' && (int) $newChoices[1]['is_correct'] === 1, 'Copied choices are incorrect.');
echo "PASS: shared question copy preserves the other exam and choices\n";

fixture($pdo, false);
$sameId = $model->updateForExam(1, 1, 10, 'Edited?', 'mc', 7, $editedChoices);
expect($sameId === 1 && (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn() === 2, 'Unshared question was unnecessarily copied.');
expect($pdo->query('SELECT choice_text FROM choices WHERE id = 2')->fetchColumn() === 'Edited B', 'Unshared choice IDs were not preserved.');
echo "PASS: unshared question updates in place\n";

foreach ([true, false] as $shared) {
    fixture($pdo, $shared);
    $savedId = $model->updateForExam(1, 1, 10, 'True?', 't/f', 4, $tfChoices);
    expect($pdo->query('SELECT type FROM questions WHERE id = ' . $savedId)->fetchColumn() === 't/f', 'Type switch failed.');
    expect($pdo->query('SELECT choice_text FROM choices WHERE question_id = ' . $savedId . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === ['True', 'False'], 'Type switch has incorrect choices.');
}
echo "PASS: shared and unshared question type changes\n";

fixture($pdo, false);
$replacementChoices = [$editedChoices[1], ['id' => null, 'text' => 'New C', 'is_correct' => 0]];
$model->updateForExam(1, 1, 10, 'Replacement?', 'mc', 3, $replacementChoices);
expect((int) $pdo->query('SELECT COUNT(*) FROM choices WHERE id = 1')->fetchColumn() === 0, 'Removed choice still exists.');
expect((int) $pdo->query('SELECT COUNT(*) FROM choices WHERE question_id = 1')->fetchColumn() === 2, 'New choice missing.');
expect($pdo->query('SELECT choice_text FROM choices WHERE id = 3')->fetchColumn() === 'Unrelated', 'Unrelated choice changed.');
echo "PASS: choice removal and addition are scoped to the edited question\n";

foreach (['wrong_owner', 'not_attached', 'foreign_choice', 'duplicate_choice', 'attempts', 'database_failure_shared', 'database_failure_unshared'] as $case) {
    fixture($pdo, $case !== 'database_failure_unshared');
    $teacherId = $case === 'wrong_owner' ? 20 : 10;
    $examId = $case === 'not_attached' ? 3 : 1;
    $choices = $editedChoices;
    if ($case === 'foreign_choice') {
        $choices[0]['id'] = 3;
    }
    if ($case === 'duplicate_choice') {
        $choices[1]['id'] = 1;
    }
    if ($case === 'attempts') {
        $pdo->exec('INSERT INTO attempts VALUES (1, 1)');
    }
    if (str_starts_with($case, 'database_failure')) {
        // The database's unique correct-answer constraint fails after writes have begun.
        $choices[0]['is_correct'] = 1;
    }
    $before = snapshot($pdo);
    $failed = false;
    try {
        $model->updateForExam(1, $examId, $teacherId, 'Must not persist', 'mc', 7, $choices);
    } catch (DomainException | PDOException $error) {
        $failed = true;
    }
    expect($failed, 'Expected rejection: ' . $case);
    expect(!$pdo->inTransaction(), 'Transaction left open: ' . $case);
    expect(snapshot($pdo) === $before, 'Failed save changed records: ' . $case);
    echo 'PASS: ' . $case . " leaves all records unchanged\n";
}

echo "All copy-on-edit checks passed.\n";

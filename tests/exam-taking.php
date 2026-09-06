<?php

// QUESTION_TEST_DSN points at a test MySQL database. All fixtures use temporary tables.
require __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../backend/models/attempts.php';
require_once __DIR__ . '/../backend/models/exam_question.php';
require_once __DIR__ . '/../backend/models/Questions.php';
if (!getenv('QUESTION_TEST_DSN')) {
    throw new RuntimeException('Set QUESTION_TEST_DSN to a test MySQL database.');
}
$pdo = new PDO(getenv('QUESTION_TEST_DSN'), getenv('QUESTION_TEST_USER') ?: 'root', getenv('QUESTION_TEST_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$instance = new ReflectionProperty(App\Utils\Database::class, 'instance');
$instance->setAccessible(true);
$instance->setValue(null, $pdo);
$pdo->exec('CREATE TEMPORARY TABLE exams (id INT PRIMARY KEY, total_marks DOUBLE, end_date DATETIME) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE attempts (id INT PRIMARY KEY, exam_id INT, student_id INT, started_at DATETIME, submitted_at DATETIME NULL, exam_mark DOUBLE DEFAULT 0, UNIQUE (exam_id, student_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE questions (id INT PRIMARY KEY, question VARCHAR(255)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE choices (id INT PRIMARY KEY, question_id INT, choice_text VARCHAR(255), is_correct TINYINT) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE exam_questions (id INT PRIMARY KEY, exam_id INT, question_id INT, question_mark DOUBLE, UNIQUE (exam_id, question_id)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE student_answers (student_id INT, exam_id INT, question_id INT, selected_choice_id INT, UNIQUE (student_id, exam_id, question_id)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO exams VALUES (1, 100, '2099-01-01'), (2, 30, '2099-01-01'), (3, 100, '2099-01-01'), (4, 100, '2000-01-01')");
$pdo->exec("INSERT INTO attempts (id, exam_id, student_id, started_at) VALUES (1, 1, 10, NOW()), (2, 1, 20, NOW()), (3, 2, 10, NOW()), (4, 3, 10, NOW()), (5, 4, 10, NOW())");
$pdo->exec("INSERT INTO questions VALUES (1, 'One?'), (2, 'Two?'), (3, 'Three?'), (4, 'Outside exam?')");
$pdo->exec("INSERT INTO choices VALUES (11, 1, 'Correct', 1), (12, 1, 'Wrong', 0), (21, 2, 'Correct', 1), (22, 2, 'Wrong', 0), (31, 3, 'Correct', 1), (32, 3, 'Wrong', 0), (41, 4, 'Correct', 1)");
$pdo->exec('INSERT INTO exam_questions VALUES (1, 1, 1, 2), (2, 1, 2, 3), (3, 1, 3, 5), (4, 2, 1, 2), (5, 2, 2, 1), (6, 4, 1, 2)');
$attempts = new App\models\Attempts();
function check($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$result = $attempts->saveAnswers(1, 10, [1 => 11, 2 => 22], false);
check(!$result['submitted'], 'Save & Next must not submit.');
check($attempts->getSavedAnswers(1, 10) === [1 => 11, 2 => 22], 'Saved progress was lost.');
$attempts->saveAnswers(1, 10, [2 => 21], false);
check(count($attempts->getSavedAnswers(1, 10)) === 2, 'Revisiting a question duplicated the answer.');
$result = $attempts->saveAnswers(1, 10, [3 => 31], true);
check($result['mark'] === 100.0 && $result['submitted'], 'Final-page answers were not graded with prior pages.');
check((float) $attempts->findByExamAndStudent(1, 10)['exam_mark'] === 100.0, 'Calculated mark was not persisted.');
$result = $attempts->saveAnswers(1, 10, [1 => 12], true);
check($result['mark'] === 100.0 && $attempts->getSavedAnswers(1, 10)[1] === 11, 'Repeat submit changed a finished attempt.');
$result = $attempts->saveAnswers(1, 10, [], true);
check($result['mark'] === 100.0, 'Empty repeat submission reset the score.');
echo "PASS: save, revise, final-page grading, persisted score, duplicate submit\n";

$result = $attempts->saveAnswers(1, 20, [1 => 11, 2 => 22], true);
check($result['mark'] === 20.0, 'Wrong and unanswered questions must remain in the denominator.');
check($attempts->getSavedAnswers(1, 10)[2] === 21, 'Another student was affected.');
$result = $attempts->saveAnswers(2, 10, [1 => 11], true);
check($result['mark'] === 20.0, 'Exam total marks scaling is incorrect.');
$result = $attempts->saveAnswers(3, 10, [], true);
check($result['mark'] === 0.0, 'Empty exam should not divide by zero.');
$result = $attempts->saveAnswers(4, 10, [1 => 11], false);
check($result['submitted'] && $result['mark'] === 100.0, 'Expired Save & Next must finish and grade the current answers.');
echo "PASS: partial score, unanswered questions, student isolation, scaling, empty exam, expiry\n";

$pdo->exec('UPDATE attempts SET submitted_at = NULL, exam_mark = 0 WHERE id = 2');
$before = $attempts->getSavedAnswers(1, 20);
foreach ([[1 => 12, 2 => 11], [1 => 12, 4 => 41]] as $answers) {
    $rejected = false;
    try {
        $attempts->saveAnswers(1, 20, $answers, true);
    } catch (DomainException $error) {
        $rejected = true;
    }
    check($rejected && $before === $attempts->getSavedAnswers(1, 20), 'Invalid choices must roll back all answers.');
    check($attempts->findByExamAndStudent(1, 20)['submitted_at'] === null, 'Rejected submission completed the attempt.');
}
echo "PASS: choices and questions from outside the exam are rejected atomically\n";

$selection = (new App\models\Exam_question())->getStudentExamSelection(1, 20);
check(count($selection) === 6, 'Details must include choices for unanswered questions.');
check(count(array_filter($selection, fn($row) => (int) $row['is_selected'] === 1)) === 2, 'Details selection mapping is incorrect.');
$publicChoices = (new App\models\Questions())->getExamChoiceOptions(1);
check(count($publicChoices) === 3 && !str_contains(json_encode($publicChoices), 'is_correct'), 'Correct answers leaked into the taking payload.');
echo "PASS: result details include all questions; taking payload omits correct answers\n";

$questions = array_map(fn($id) => ['question_id' => $id], range(1, 12));
$attempt = $attempts->findByExamAndStudent(1, 10);
$order = App\models\Attempts::orderQuestions($questions, $attempt, true);
check($order === App\models\Attempts::orderQuestions(array_reverse($questions), $attempt, true), 'Order changes after reload.');
check($order !== App\models\Attempts::orderQuestions($questions, $attempts->findByExamAndStudent(1, 20), true), 'Student orders are identical for these fixtures.');
check(count($order) === count($questions) && count(array_unique(array_column($order, 'question_id'))) === count($questions), 'Randomization loses or duplicates questions.');
check(App\models\Attempts::orderQuestions(array_reverse($questions), $attempt, false) === $questions, 'Nonrandom order is unstable.');
echo "PASS: per-student random order is complete and stable after reload; disabled order is sequential\n";

<?php

namespace App\models;

use App\Utils\Database;
use PDO;

class Questions
{
    private PDO $pdo;
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    public function all(int $course_id)
    {
        $sql = "SELECT * FROM questions WHERE course_id=:course_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id]);
        return $statement->fetchAll();
    }

    public function getByID(int $id)
    {
        $sql = "SELECT q.*, q.type AS question_type FROM questions q WHERE q.id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        $res = $statement->fetch();

        if (!$res) {
            http_response_code(404);
            $_SESSION['error'] = "INternal Server Error (Question Not Found)";
            return null;
        }
        return $res;
    }

    public function getByIDForUpdate(int $id)
    {
        $sql = "SELECT q.id AS question_id, q.course_id, q.question AS question_text, q.type AS question_type FROM questions q WHERE q.id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        $res = $statement->fetch();

        if (!$res) {
            http_response_code(404);
            $_SESSION['error'] = "INternal Server Error (Question Not Found)";
            return null;
        }
        return $res;
    }

    public function getQuestionDetails(int $question_id, $exam_id)
    {
        if (!$question_id || !$exam_id) {
            http_response_code(400);
            $_SESSION['error'] = 'Question not found, question ID: ' . $question_id . ", Exam ID: " . $exam_id;
            return null;
        }
        $sql = "SELECT q.id AS question_id, q.course_id, q.question AS question_text, q.type as question_type, eq.question_mark 
                FROM questions q
                Inner JOIN exam_questions eq ON q.id = eq.question_id
                WHERE q.id=:question_id AND eq.exam_id=:exam_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['question_id' => $question_id, 'exam_id' => $exam_id]);
        return $statement->fetch();
    }
    public function getExamQuestions(int $exam_id)
    {
        $sql = "SELECT q.id AS question_id, q.course_id, q.question AS question_text, q.type as question_type, eq.question_mark FROM questions q JOIN exam_questions eq ON q.id = eq.question_id WHERE eq.exam_id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $exam_id]);
        return $statement->fetchAll();
    }

    public function getExamChoiceOptions(int $exam_id): array
    {
        $sql = 'SELECT c.id, c.question_id, c.choice_text 
        FROM choices c 
        INNER JOIN exam_questions eq ON eq.question_id = c.question_id 
        WHERE eq.exam_id = :exam_id 
        ORDER BY c.id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id]);
        $choices = [];
        foreach ($statement->fetchAll() as $choice) {
            $choices[$choice['question_id']][] = $choice;
        }
        return $choices;
    }

    public function getQuestionChoices(int $question_id)
    {
        $sql = "SELECT * FROM choices WHERE question_id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $question_id]);
        $res =  $statement->fetchAll();

        if (!$res) {
            http_response_code(404);
            $_SESSION['error'] = "No Choices Found for this Questions";
            return null;
        }
        return $res;
    }

    public function create(int $course_id, string $question, $type)
    {
        $sql = "INSERT INTO questions (course_id, question, type) VALUES (:course_id, :question, :type)";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id, 'question' => $question, 'type' => $type]);
        return (int) $this->pdo->lastInsertId();
    }

    public static function update($question_id, $question_text, $question_type)
    {
        if (!$question_id || !$question_text || !$question_type) {
            http_response_code(400);
            $_SESSION['error'] = "Missing required parameters for updating the question.";
            return false;
        }
        $pdo = Database::getInstance();
        $sql = "UPDATE questions SET question=:question_text, type=:question_type WHERE id=:question_id";
        $statement = $pdo->prepare($sql);
        return $statement->execute(['question_text' => $question_text, 'question_type' => $question_type, 'question_id' => $question_id]);
    }

    public function updateForExam(int $question_id, int $exam_id, int $teacher_id, string $text, string $type, int $mark, array $choices): int
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT teacher_id FROM exams WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $exam_id]);
            $exam = $statement->fetch();
            if (!$exam || (int) $exam['teacher_id'] !== $teacher_id) {
                throw new \DomainException('You can only edit questions in your own exams.');
            }

            $statement = $this->pdo->prepare('SELECT id FROM attempts WHERE exam_id = :id LIMIT 1 FOR UPDATE');
            $statement->execute(['id' => $exam_id]);
            if ($statement->fetch()) {
                throw new \DomainException('Questions cannot be edited after students have started the exam.');
            }

            $statement = $this->pdo->prepare('SELECT course_id, type FROM questions WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $question_id]);
            $question = $statement->fetch();
            if (!$question) {
                throw new \DomainException('Question not found.');
            }

            $statement = $this->pdo->prepare('SELECT exam_id FROM exam_questions WHERE question_id = :id FOR UPDATE');
            $statement->execute(['id' => $question_id]);
            $examIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            if (!in_array($exam_id, $examIds, true)) {
                throw new \DomainException('This question is no longer part of the exam. Please reopen the exam.');
            }

            $statement = $this->pdo->prepare('SELECT id FROM choices WHERE question_id = :id FOR UPDATE');
            $statement->execute(['id' => $question_id]);
            $existingIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            $submittedIds = [];
            foreach ($choices as $choice) {
                if ($choice['id'] !== null) {
                    if (!in_array($choice['id'], $existingIds, true) || in_array($choice['id'], $submittedIds, true)) {
                        throw new \DomainException('Invalid choice. Please reopen the question and try again.');
                    }
                    $submittedIds[] = $choice['id'];
                }
            }

            $copy = count($examIds) > 1;
            $savedId = $question_id;
            if ($copy) {
                $savedId = $this->create((int) $question['course_id'], $text, $type);
            } else {
                if (!self::update($question_id, $text, $type)) {
                    throw new \RuntimeException('Failed to update question.');
                }
                $statement = $this->pdo->prepare('UPDATE choices SET is_correct = 0 WHERE question_id = :id');
                $statement->execute(['id' => $question_id]);
                $delete = $this->pdo->prepare('DELETE FROM choices WHERE id = :id AND question_id = :question_id');
                foreach ($existingIds as $id) {
                    if ($question['type'] !== $type || !in_array($id, $submittedIds, true)) {
                        $delete->execute(['id' => $id, 'question_id' => $question_id]);
                    }
                }
            }

            $insert = $this->pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (:question_id, :text, :correct)');
            $update = $this->pdo->prepare('UPDATE choices SET choice_text = :text, is_correct = :correct WHERE id = :id AND question_id = :question_id');
            foreach ($choices as $choice) {
                $params = ['question_id' => $savedId, 'text' => $choice['text'], 'correct' => $choice['is_correct']];
                if (!$copy && $question['type'] === $type && $choice['id'] !== null) {
                    $params['id'] = $choice['id'];
                    $update->execute($params);
                } else {
                    $insert->execute($params);
                }
            }

            $statement = $this->pdo->prepare('UPDATE exam_questions SET question_id = :saved_id, question_mark = :mark WHERE exam_id = :exam_id AND question_id = :question_id');
            $statement->execute(['saved_id' => $savedId, 'mark' => $mark, 'exam_id' => $exam_id, 'question_id' => $question_id]);

            $this->pdo->commit();
            return $savedId;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function delete(int $question_id)
    {
        $sql = 'DELETE FROM questions WHERE id=:id';
        $statement = $this->pdo->prepare($sql);
        $res = $statement->execute(['id' => $question_id]);

        if (!$res) {
            http_response_code(500);
            $_SESSION['error'] = "Internal Server Error";
            return null;
        }
        return $res;
    }

    public function getExamQuestionSet($exam_id, $offset, $size = 2)
    {
        $sql = "SELECT q.id, q.course_id, q.question, eq.question_mark 
        FROM questions q 
        JOIN exam_questions eq ON q.id = eq.question_id 
        WHERE eq.exam_id=:id
        LIMIT :size
        OFFSET :offset";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $exam_id, 'size' => $size, 'offset' => $offset]);
        return $statement->fetchAll();
    }

    public function getExamQuestionCount($exam_id)
    {
        $sql = "SELECT COUNT(*) as total FROM exam_questions WHERE exam_id = :exam_id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['exam_id' => $exam_id]);
        $result = $statement->fetch();
        return $result['total'] ?? 0;
    }

    public function getCourseQuestions($course_id)
    {
        $sql = 'SELECT q.*, q.type AS question_type FROM questions q WHERE q.course_id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $course_id]);
        return $statement->fetchAll();
    }

    public function getQuestionsDraft($question_ids)
    {
        $sql = 'SELECT * FROM questions WHERE id IN (?)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$question_ids]);
        return $statement->fetchAll();
    }

    public function getAutoGenerated(int $course_id, int $limit)
    {
        $sql = 'SELECT q.id as question_id, q.question as question_text, q.type as question_type, eq.question_mark
                FROM questions q
                INNER JOIN exam_questions eq ON eq.question_id = q.id
                WHERE course_id = :course_id
                ORDER BY RAND()
                LIMIT :num;';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['course_id' => $course_id, 'num' => $limit]);
        return $statement->fetchAll();
    }

    public function getCourseQuestionsCount($course_id)
    {
        $sql = 'SELECT COUNT(*) FROM questions WHERE course_id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $course_id]);
        return $statement->fetch();
    }

    public function exists($question_id)
    {
        $sql = 'SELECT COUNT(*) FROM questions WHERE id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $question_id]);
        $res = $statement->fetch();

        if (!$res) {
            http_response_code(404);
            $_SESSION['error'] = "Question Not Found";
            return null;
        }
        return true;
    }
}

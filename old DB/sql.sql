CREATE DATABASE IF NOT EXISTS ITC;
USE ITC;

CREATE TABLE users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32) NOT NULL,
    role ENUM('student', 'teacher') NOT NULL,
    email VARCHAR(64) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE teacher_courses(
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    courses_id INT NOT NULL,

    UNIQUE (teacher_id, courses_id),

    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (courses_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE enrolment(
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,    
    courses_id INT NOT NULL,

    UNIQUE (student_id, courses_id),

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (courses_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE exams(
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    courses_id INT NOT NULL,
    status ENUM('not_ready', 'ready', 'in_progress', 'completed') NOT NULL DEFAULT 'not_ready',
    total_marks INT NOT NULL CHECK(total_marks > 0 AND total_marks <= 100),
    teacher_id INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    randomize_order BOOLEAN NOT NULL DEFAULT FALSE,

    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (courses_id) REFERENCES courses(id) ON DELETE CASCADE,
    CHECK (end_date > start_date)
);

CREATE TABLE questions(
    id INT AUTO_INCREMENT PRIMARY KEY,
    courses_id INT NOT NULL,
    question TEXT NOT NULL,
    question_mark REAL NOT NULL DEFAULT 1.0,

    FOREIGN KEY (courses_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE choices(
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text VARCHAR(255) NOT NULL,  
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    one_correct INT AS (IF (is_correct, question_id, null)) STORED,

    UNIQUE (id, question_id),
    UNIQUE (one_correct),

    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE exam_questions(
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,

    UNIQUE (exam_id, question_id),

    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE attempts(
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    started_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,

    UNIQUE (exam_id, student_id),

    FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE marks(
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    student_mark INT NOT NULL CHECK(student_mark >= 0 AND student_mark <= 100),

    UNIQUE (student_id, exam_id),

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

CREATE TABLE student_answers(
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_choice_id INT NOT NULL,

    UNIQUE (student_id, exam_id, question_id),

    FOREIGN KEY (exam_id, student_id) REFERENCES attempts(exam_id, student_id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id, question_id) REFERENCES exam_questions(exam_id, question_id) ON DELETE CASCADE,
    FOREIGN KEY (selected_choice_id, question_id) REFERENCES choices(id, question_id) ON DELETE CASCADE
);
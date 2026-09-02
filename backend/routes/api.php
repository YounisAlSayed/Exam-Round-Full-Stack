<?php

use App\Routes\Router;

//------------------------------ questions routers -----------------------------------
Router::post('/api/questions/create/{course_id}', ['QuestionController', 'addQuestion']);
Router::post('/api/questions/generate/courses/{course_id}/exams/{exam_id}', ['QuestionController', 'autoGenerate']);

Router::post('/api/questions/update/{question_id}', ['QuestionController', 'editQuestion']);

Router::delete('/api/questions/delete/{question_id}', ['QuestionController', 'delete']);

// =------------------------------ choices routers -----------------------------------
Router::delete('/api/choices/delete/{choice_id}', ['ChoicesController', 'deleteChoice']);
//-------------------------- user routers ----------------------------
Router::post('/api/users/login', ['UserController', 'login']);
Router::post('/api/users/signup', ['UserController', 'signup']);

Router::put('/api/users/{id}', ['UserController', 'edit']);

Router::delete('/api/user/{id}', ['UserController', 'delete']);

//--------------------- exams routes --------------------------
Router::get('/api/exams', ['ExamsController', 'getAll']);
Router::get('/api/exams/{id}', ['ExamsController', 'getById']);
Router::get('/api/exams/teacher/{id}', ['ExamsController', 'getTeacherExams']);
Router::get('/api/exams/random', ['ExamsController', 'generateRandom']);
Router::get('/api/exams/course/{id}', ['ExamsController', 'getNextCourseExam']);
Router::get('/api/exams/{id}/questions', ['ExamsController', 'getExamQuestions']);

Router::post('/api/exams/create/courses/{course_id}', ['ExamsController', 'create']);
Router::post('/api/exams/course/{id}', ['ExamsController', 'setNextCourseExam']);

Router::put('/api/exams/{id}', ['ExamsController', 'edit']);

Router::delete('/api/exams/{id}', ['ExamsController', 'delete']);

// when the user is taking the exam
Router::get('/api/exams/{id}/start/{page}', ['ExamsController', 'examStart']);
Router::post('/api/exams/{id}/submit', ['ExamsController', 'submitExam']);
Router::post('/api/exams/{id}/save/progress', ['ExamsController', 'saveProgress']);
//----------------- attempts routes ---------------------------
Router::get('/api/attempts/student/{id}', ['AttemptsController', 'getStudentAttempt']);

Router::post('/api/attempts/student/{id}', ['AttemptsController', 'updateStudentAttempt']);

Router::delete('/api/attempts/{id}', ['AttemptsController', 'deleteStudentAttempt']);

//------------------ courses routes -----------------------------
Router::get('/api/courses/{id}/students', ['CoursesController', 'getCourseStudents']);
Router::get('/api/courses/{id}/teachers', ['CoursesController', 'getCourseTeachers']);

Router::get('/api/courses/list', ['CoursesController', 'getAll']);

Router::post('/api/courses', ['CoursesController', 'add']);

Router::put('/api/courses/{id}', ['CoursesController', 'edit']);

Router::delete('/api/courses/{id}', ['CoursesController', 'delete']);

// ------------------------- enrollment routes -----------------------------
Router::get('/api/enrollment/students/{id}', ['EnrollmentController', 'getStudentEnrollments']);

Router::post('/api/enrolment/students/{id}', ['EnrollmentController', 'enrollStudent']);

Router::delete('/api/enrolment/{id}', ['EnrollmentController', 'deleteStudentEnrollment']);

// ------------------------------- marks routes -------------------------------
Router::get('/api/marks/student/{id}/average', ['MarksController', 'getStudentMarks']);
Router::get('/api/marks/course/{id}/average', ['MarksController', 'getCourseAverage']);
Router::get('/api/marks/student/{student_id}/course/{course_id}', ['MarksController', 'getStudentCourseMarks']);
Router::get('/api/marks/student/{student_id}/exam/{exam_id}', ['MarksController', 'getStudentExamMark']);

Router::post('/api/marks/student/{student_id}/exam/{exam_id}', ['MarksController', 'addStudentMark']);

Router::put('/api/marks/student/{student_id}/exam/{exam_id}', ['MarksController', 'edit']);

// ------------------------------- teacher_courses routes ----------------------------
Router::get('/api/teachers/courses', ['TeacherCoursesController', 'getTeachersCourses']);
Router::get('/api/teachers/{id}/courses', ['TeacherCoursesController', 'getTeacherCourses']);

Router::post('/api/teachers/{teacher_id}/courses/{course_id}', ['TeacherCoursesController', 'addTeacherCourse']);

Router::delete('/api/teachers/{teacher_id}/courses/{course_id}', ['TeacherCoursesController', 'deleteTeacherCourse']);

// ------------------------------- student_answers routes ----------------------------
Router::get('/api/students/{student_id}/exams/{exam_id}/answers', ['StudentsAnswersController', 'getStudentExamAnswers']);

Router::post('/api/students/{student_id}/exams/{exam_id}/question/{question_id}/answers', ['StudentsAnswersController', 'addStudentExamAnswers']);

Router::put('/api/students/{student_id}/exams/{exam_id}/questions/{question_id}/answers', ['StudentsAnswersController', 'edit']);

// --------------------------------------------------------------------------------------------------

Router::get('/api/dashboard', ['RedirectingController', 'dashboard']);
Router::get('/api/login', ['RedirectingController', 'login']);
Router::get('/api/signup', ['RedirectingController', 'signup']);
Router::get('/api/logout', ['RedirectingController', 'logout']);
Router::get('/api/profile', ['RedirectingController', 'profile']);
Router::get('/api/users/list', ['RedirectingController', 'usersList']);
Router::get('/api/questions/create/courses/{course_id}/view', ['RedirectingController', 'createQuestion']);
Router::post('/api/questions/create/courses/{course_id}/view', ['RedirectingController', 'createQuestion']);
Router::get('/api/exams/{id}/details/student', ['RedirectingController', 'studentExamDetails']);
Router::get('/api/exams/{id}/details/teacher', ['RedirectingController', 'teacherExamDetails']);
Router::get('/api/exams/{id}/start/{page}', ['RedirectingController', 'examStart']);
Router::get('/api/courses/teacher/{course_id}', ['RedirectingController', 'teacherCourse']);
Router::get('/api/exams/preview/{exam_id}', ['RedirectingController', 'examCreate']);
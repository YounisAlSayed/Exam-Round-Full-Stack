# Exam-Round-Full-Stack
Think of it like an online classroom testing system — a digital version of paper exams.

For teachers:

You create a "course" (like "Intro to Databases")
You write questions for that course and save them in a question bank
When it's time for a test, you pick which questions go into that exam, set a start/end time, and decide how many points it's worth
Once students take the exam, you can see everyone's scores and class averages, right in the system — no manual grading of multiple-choice questions needed

For students:

You see the courses you're enrolled in and any upcoming exams
When an exam opens, you answer each question by picking from a few choices — like a Scantron test, but on a screen
Once you submit, you're done — you can look back later to see your answers and your grade, but you can't change anything after submitting (just like handing in a paper test)

Under the hood, in plain terms:
The system is built like a filing cabinet with strict rules. Every person, course, question, exam, and grade lives in its own labeled folder (a table in a database), and folders are cross-referenced to each other — a question "belongs to" a course, an exam "belongs to" a teacher, a grade "belongs to" a specific student and a specific exam. The rules make sure nothing gets mismatched — you can't accidentally grade a student who was never enrolled, or attach an answer to a question that isn't even part of that exam.

When someone clicks a button — "log in," "submit exam," "see my grade" — the system checks who they are and what they're allowed to do, fetches the right information from that filing cabinet, and shows them a new page. Nothing happens instantly like an app on your phone; it's more like flipping to a new page in a binder each time you click something — simple, reliable, and easy to trace if something goes wrong.

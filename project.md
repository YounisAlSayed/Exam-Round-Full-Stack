## Project Overview

Your task is to build a full-stack web application for a Quiz/Testing Platform using **Vanilla PHP 8+**. You will not use full-stack frameworks like Laravel or CodeIgniter. Instead, you will build your own lightweight **Model-View-Controller (MVC)** architecture from scratch. This project will render HTML pages dynamically using a custom View layer. All output is handled by PHP view files rendered through the `ViewModel` class.

---

## Architecture & Concepts

### Request Lifecycle

```
HTTP Request
    ↓
router.php  (entry point for PHP built-in server)
    ↓
index.php   (loads .env, applies middleware, dispatches)
    ↓
Router::dispatch()  (matches URI + HTTP method → controller)
    ↓
Controller Method  (e.g. TestController::getAll)
    ↓
Model  (queries the database via PDO)
    ↓
ViewModel::render()  (renders a PHP view file with the data)
    ↓
HTTP Response (HTML page)
```

### The ViewModel Class

Instead of returning JSON or using a separate frontend, every controller action **must** return a `ViewModel`. The `ViewModel` takes a template name and a data array, then renders the matching PHP view file.

---

### class ViewModel

```
<?php

namespace App\Utils;

class ViewModel
{
    private $template;
    private $data = [];

    public function __construct($template, $data = [])
    {
        $this->template = $template;
        $this->data = $data;
    }

    public function render()
    {
        // Extract array keys as variables (e.g. ['tests' => $tests] becomes $tests)
        extract($this->data);

        $viewPath = __DIR__ . '/../views/' . $this->template . '.phtml';

        if (file_exists($viewPath)) {
            // Set header to HTML instead of JSON (which might be set globally)
            header('Content-Type: text/html; charset=UTF-8');
            require $viewPath;
        } else {
            echo "View not found: " . htmlspecialchars($this->template);
        }
    }
}
```

---

## Folder Structure to Implement

```
backend/
├── index.php                      # Entry point
├── router.php                     # PHP built-in server routing
├── .env                           # Environment variables
├── config/
│   └── database.php               # DB config using env vars
├── utils/
│   ├── Database.php               # Database singleton
│   └── ViewModel.php              # ViewModel renderer
│   ├── Cors.php                   # CORS headers middleware
├── routes/
│   ├── Router.php                 # Static router class
│   └── api.php                    # Route definitions
├── models/
│   ├── write your custom files of models here
├── controllers/
│   ├── write your custom files of controllers here
└── views/
    ├── questions/
    │   ├── index.phtml
    │   └── show.phtml
    ├── write your custom files of views here
```

---

### class Cors

```
<?php

namespace App\Utils;

class Cors
{
    public function handle()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return true;
    }
}
```

---

### `Search for how these should be implemented, then try to implement: Router class, api.php file, Database class`

---
### Database Layer (PDO Wrapper)
Do NOT use an ORM (like Eloquent or Doctrine).
Create a custom Database class using the Singleton Design Pattern to manage a single PDO connection per request lifecycle.
Implement reusable abstraction methods for common SQL operations: select(), selectOne(), insert(), update(), and delete().
Ensure all database interactions use Prepared Statements to prevent SQL injection vulnerabilities.

---
### sample of file api.php content

`Router::get('/api/questions', ['QuestionController', 'getAll']); `

---

## Environment Configuration

Create `backend/.env`:

`complete it`

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=quizez_db
DB_USER=root
DB_PASSWORD=

```

---

### Example `QuestionController.php`

| Method         | Route                        |
| -------------- | ---------------------------- |
| `getAll()`     | `GET /api/questions`         |
| `getById($id)` | `GET /api/questions/{id}`    |
| `createForm()` | `GET /api/questions/create`  |
| `create()`     | `POST /api/questions`        |
| `update($id)`  | `PUT /api/questions/{id}`    |
| `delete($id)`  | `DELETE /api/questions/{id}` |

### Handling Forms (Traditional Server-Side Workflow)

Since this project does not use a separate frontend (like React), you should implement a traditional server-side rendered (SSR) workflow for forms:

1. **Displaying the Form:**
   - Create a GET route (e.g., `GET /api/questions/create`).
   - The controller action returns a `ViewModel` that renders the HTML form (e.g., `views/questions/create.phtml`).
   - The form should use `<form method="POST" action="/api/questions">`.

2. **Submitting the Form:**
   - When the user submits, the browser sends standard `application/x-www-form-urlencoded` data via POST.
   - In your controller's POST action, read the data using the `$_POST` superglobal (do not use `json_decode(file_get_contents('php://input'))` for standard forms).

3. **Redirect on Success:**
   - Instead of returning a JSON response on success, use a PHP header redirect to send the user back to the list page (Post/Redirect/Get pattern).

---

## Key Concepts to Understand

| Concept                     | Description                                                                                                                                                                                                          |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Singleton**               | `Database::getInstance()` ensures only one DB connection exists per request.                                                                                                                                         |
| **ViewModel**               | Decouples data from presentation. Controller passes data; the view handles HTML. The `ViewModel` template path is **relative to** `backend/views/`. So `'tests/index'` resolves to `backend/views/tests/index.phtml` |
| **PDO Prepared Statements** | All SQL uses named parameters (`:param`) to prevent SQL injection.                                                                                                                                                   |

---

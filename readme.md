# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. We believe that creating an API should be an elegant and straightforward process, free from unnecessary complexity and boilerplate.

Our core philosophy is **Absolute Simplicity**. ACE strips away non-essential features to provide a clean, intuitive, and highly focused development experience. If you need to build a pure API with MySQL or SQLite, ACE is the fastest way to get it done.

## Key Features

- **Zero-Config Routing**: No route files. No route attributes. Just create your controller and methods following a simple convention, and your API endpoints are live.
- **Automatic Code Generation**: Create full CRUD API resources with a single command.
- **Attribute-Based API Docs**: Define your API documentation with simple PHP 8 attributes right above your controller methods.
- **Effortless JSON Responses**: Just return an array or object from your controller, and ACE will automatically convert it to a JSON response with the correct headers and status codes.
- **Fluent Query Builder**: A simple, powerful query builder that makes database interactions a breeze.
- **Simple Migrations**: A straightforward CLI-based migration system to manage your database schema.

## Automatic Routing Conventions

You never have to define a route. ACE follows these simple conventions:

| Controller Method | HTTP Verb | URI                         |
|-------------------|-----------|-----------------------------|
| `index()`         | `GET`     | `/api/products`             |
| `show(int $id)`   | `GET`     | `/api/products/{id}`        |
| `store()`         | `POST`    | `/api/products`             |
| `update(int $id)` | `PUT`     | `/api/products/{id}`        |
| `destroy(int $id)`| `DELETE`  | `/api/products/{id}`        |

*(Example shown for a `ProductController`)*

## Quick Start: Your First API in 90 Seconds

Let's create a complete CRUD API for "posts".

### 1. Generate the API Resource

Open your terminal and run the single `make:api` command:

```bash
php ace.php make:api Post
```

This command automatically creates:
- A **migration file** to create the `posts` table.
- A `Post` **model**.
- A `PostController` with all conventional CRUD methods and API documentation attributes.

### 2. Customize Your Migration

Open the newly created migration file in `database/migrations/` and add the columns you need. For example, add `title` and `body` columns.

```php
// ... inside the up() method's CREATE TABLE SQL ...
`title` VARCHAR(255) NOT NULL,
`body` TEXT NOT NULL,
// ...
```

### 3. Run the Migration

Execute the migration to create the table in your database.

```bash
php ace.php migrate
```

### 4. Generate and View API Docs

Now, generate the API documentation from your new controller.

```bash
php ace.php docs:generate
```

Open your browser and navigate to `/api/docs`. You will see a complete, interactive Swagger UI documentation for your new Posts API.

**That's it! You now have a fully documented, functional CRUD API without ever defining a single route.**

## The `ace` Console Tool

All framework tasks are handled by the `ace` console tool.

- `php ace.php make:api [Name]`
  - Scaffolds a new API resource. `[Name]` should be the singular, PascalCase name of your resource (e.g., `Product`, `BlogPost`).

- `php ace.php migrate`
  - Runs any pending database migrations.

- `php ace.php docs:generate`
  - Generates/updates the `openapi.json` file from your controller attributes.

---
*Built with simplicity by ED.*
# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. We believe that creating an API should be an elegant and straightforward process, free from unnecessary complexity and boilerplate.

Our core philosophy is **Absolute Simplicity**. ACE strips away non-essential features to provide a clean, intuitive, and highly focused development experience. If you need to build a pure API with MySQL or SQLite, ACE is the fastest way to get it done.

## Key Features

- **Zero-Configuration Feel**: Sensible defaults and auto-discovery let you focus on code, not configuration.
- **Automatic Code Generation**: Create full CRUD API resources with a single command.
- **Attribute-Based Routing & Docs**: Define your API routes and documentation together, right in your controller, using PHP 8 attributes.
- **Effortless JSON Responses**: Just return an array or object from your controller, and ACE will automatically convert it to a JSON response with the correct headers and status codes.
- **Fluent Query Builder**: A simple, powerful query builder that makes database interactions a breeze.
- **Simple Migrations**: A straightforward CLI-based migration system to manage your database schema.

## Quick Start: Your First API in 90 Seconds

Let's create a complete CRUD API for "posts".

### 1. Generate the API Resource

Open your terminal and run the single `make:api` command:

```bash
php ace.php make:api Post
```

This one command automatically creates:
- A **migration file** to create the `posts` table in `database/migrations/`.
- A `Post` **model** in `app/Models/`.
- A `PostController` with all CRUD methods and API documentation attributes in `app/Http/Controllers/`.

### 2. Customize Your Migration

Open the newly created migration file in `database/migrations/` and add the columns you need for your posts table. For example, add `title` and `body` columns.

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

Open your browser and navigate to `/api/docs`. You will see a complete, interactive Swagger UI documentation for your new Posts API, including endpoints for creating, reading, updating, and deleting posts.

**That's it! You now have a fully documented, functional CRUD API.**

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
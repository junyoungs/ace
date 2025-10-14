# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. We believe that creating an API should be an elegant and straightforward process, free from unnecessary complexity and boilerplate.

Our core philosophy is **Absolute Simplicity**. ACE strips away non-essential features to provide a clean, intuitive, and highly focused development experience. If you need to build a pure API with MySQL or SQLite, ACE is the fastest way to get it done.

## Key Features

- **Zero-Config Routing**: Just name your controller methods with a `get`, `post`, `put`, or `delete` prefix, and your API endpoints are live instantly.
- **Automatic Code Generation**: A powerful CLI (`./ace`) to create full CRUD API resources with a single command.
- **Attribute-Based API Docs**: Define your API documentation with simple PHP 8 attributes right above your controller methods.
- **Effortless JSON Responses**: The framework automatically converts controller return values into JSON responses with correct status codes.
- **Explicit & Safe SQL**: Write clear, explicit SQL queries that are easy to debug and tune, all safely executed with prepared statements.
- **High-Performance Ready**: Built to run on high-performance application servers like RoadRunner, with support for PSR-7 standards.

## Advanced Features (Optional)

ACE also provides powerful features for building scalable, real-time applications. These are optional and can be used when needed.

- **High-Performance Caching**: A Redis-based cache layer to dramatically speed up your application.
  ```php
  $cache = new \CORE\Cache();
  $cache->set('user:1', $user, 3600);
  $user = $cache->get('user:1');
  ```

- **Real-time Events (Pub/Sub)**: Broadcast events to other parts of your system (like a WebSocket server) using Redis Pub/Sub.
  ```php
  \CORE\Event::publish('user.registered', ['email' => $userEmail]);
  ```

- **Distributed Locking**: Prevent race conditions in a distributed environment, ensuring that critical operations are performed by only one process at a time.
  ```php
  $lock = new \CORE\LockProvider();
  $lock->withLock('process-payment:order:123', function () {
      // Safely process the payment.
  }, 10); // Lock for 10 seconds
  ```

## Quick Start: Your First API in 90 Seconds

### 1. Initial Setup
Make the `ace` command-line tool executable. You only need to do this once.
```bash
chmod +x ace.php
```

### 2. Generate the API Resource
Use the `make:api` command to create all the necessary files for a "Post" resource.
```bash
./ace make:api Post
```

### 3. Customize and Run Migration
Edit the new migration file in `database/migrations/` to add your columns, then run it.
```bash
./ace migrate
```

### 4. Start the Development Server
Run the high-performance development server.
```bash
./ace serve
```
Your API is now running on `http://localhost:8080`.

### 5. Generate and View API Docs
In a separate terminal, generate the API documentation.
```bash
./ace docs:generate
```
Open your browser and navigate to `http://localhost:8080/api/docs` to see your fully documented, interactive API.

## Development with the `ace` Console Tool
- `./ace make:api [Name]`: Scaffolds a new API resource (Model, Controller, Migration).
- `./ace migrate`: Runs any pending database migrations.
- `./ace docs:generate`: Generates/updates the API documentation.
- `./ace serve`: Starts the RoadRunner development server.

## Production Deployment with Docker

ACE is designed to be deployed as a high-performance service using Docker and RoadRunner.

### 1. Build the Docker Image
A sample `Dockerfile` is included in the project root. Build your production image with:
```bash
docker build -t my-ace-api .
```

### 2. Prepare `.env` and `.roadrunner.yaml`
Create your production `.env` file with your database credentials and other settings. Also, ensure your `.roadrunner.yaml` is configured for your production environment (e.g., increase the number of workers).

### 3. Run the Container
Run your application container, passing in your environment file.
```bash
docker run -p 80:8080 --env-file ./.env my-ace-api
```
Your high-performance API service is now live.

---
*Built with simplicity by ED.*
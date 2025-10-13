# Custom PHP Framework

This is a lightweight, custom-built PHP framework designed for building web applications. It follows a Model-View-Controller (MVC) like architecture and provides a basic structure for routing, database interaction, and application logic.

This framework is currently undergoing modernization to serve as a viable alternative to more complex frameworks like Laravel for specific use cases.

## Author

*   **ED**

## Recent Improvements

The framework has been recently refactored to incorporate modern PHP development practices and enhance security. Key improvements include:

### 1. Enhanced Security
- **Prepared Statements:** The database layer has been updated to use `mysqli` prepared statements for all queries, providing robust protection against SQL injection attacks. The old, insecure `execute()` method has been deprecated.

### 2. Modernized Architecture
- **Dependency Injection (DI):** The core `Control` class now utilizes constructor dependency injection. This decouples controllers from the global state, making them more modular, easier to test, and simpler to maintain.
- **PSR-1 Compliance:** Class and file names have been updated to follow PSR-1 standards (e.g., `ClassName` and `ClassName.php`), improving code consistency and interoperability.

### 3. Improved Error Handling
- **Exception-based Flow:** Critical errors, such as a missing controller or class, now throw exceptions instead of being silently logged. A global exception handler provides detailed debug information in development environments and generic, safe error pages in production.

## Future Development
This framework is actively being improved with the goal of adding more advanced features, including:
- An advanced routing system (named routes, groups, HTTP method-based routing).
- A database migration system.
- Automatic API documentation generation.
- An expressive query builder.

---
*This document was last updated on 2025-10-13.*
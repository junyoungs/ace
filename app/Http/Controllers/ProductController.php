<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Attributes\Summary;
use APP\Attributes\Param;
use APP\Attributes\Response;
use APP\Models\Product;

class ProductController extends \APP\Control
{
    #[Summary('List all products')]
    #[Response(200, 'A list of products.', exampleJson: '[{"id": 1, "name": "Laptop", "price": "1200.50"}]')]
    public function getIndex(): array
    {
        return Product::select("SELECT * FROM products ORDER BY id DESC");
    }

    #[Summary('Get a single product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product.')]
    #[Response(200, 'The requested product.', exampleJson: '{"id": 1, "name": "Laptop", "price": "1200.50"}')]
    #[Response(404, 'Product not found.', exampleJson: '{"error": "Product not found"}')]
    public function getShow(int $id): ?array
    {
        $product = Product::select("SELECT * FROM products WHERE id = ?", [$id]);
        if (empty($product)) {
            http_response_code(404);
            return ['error' => 'Product not found'];
        }
        return $product[0];
    }

    #[Summary('Create a new product')]
    #[Param('name', 'string', 'body', true, 'Name of the product.')]
    #[Param('price', 'number', 'body', true, 'Price of the product.')]
    #[Response(201, 'Product created successfully.', exampleJson: '{"id": 2, "name": "Mouse", "price": "25.00"}')]
    public function postStore(): array
    {
        // In a real app, you would get data from the request body.
        $data = ['name' => 'Mouse', 'price' => 25.00, 'description' => 'A wireless mouse.'];

        Product::statement(
            "INSERT INTO products (name, price, description) VALUES (?, ?, ?)",
            [$data['name'], $data['price'], $data['description']]
        );

        // A more complete implementation would fetch the last inserted ID and return the full object.
        return $data;
    }

    #[Summary('Update a product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product to update.')]
    #[Response(200, 'Product updated successfully.', exampleJson: '{"message": "Product updated"}')]
    #[Response(404, 'Product not found.', exampleJson: '{"error": "Product not found"}')]
    public function putUpdate(int $id): array
    {
        // In a real app, you would get data from the request body.
        $data = ['price' => 29.99];

        $affectedRows = Product::statement(
            "UPDATE products SET price = ? WHERE id = ?",
            [$data['price'], $id]
        );

        if ($affectedRows === 0) {
            http_response_code(404);
            return ['error' => 'Product not found or no changes made'];
        }
        return ['message' => 'Product updated'];
    }

    #[Summary('Delete a product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product to delete.')]
    #[Response(204, 'Product deleted successfully.')]
    #[Response(404, 'Product not found.', exampleJson: '{"error": "Product not found"}')]
    public function deleteDestroy(int $id): ?array
    {
        $affectedRows = Product::statement("DELETE FROM products WHERE id = ?", [$id]);
        if ($affectedRows === 0) {
            http_response_code(404);
            return ['error' => 'Product not found'];
        }
        return null; // Will be converted to a 204 No Content response.
    }
}
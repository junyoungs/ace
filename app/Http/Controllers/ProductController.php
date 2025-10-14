<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Attributes\Route;
use APP\Attributes\Summary;
use APP\Attributes\Param;
use APP\Attributes\Response;
use APP\Models\Product;

class ProductController extends \APP\Control
{
    #[Route('/api/products', method: 'GET')]
    #[Summary('List all products')]
    #[Response(200, 'A list of products.', exampleJson: '[{"id": 1, "name": "Laptop", "price": "1200.50"}]')]
    public function index(): array
    {
        return Product::get();
    }

    #[Route('/api/products/{id}', method: 'GET')]
    #[Summary('Get a single product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product.')]
    #[Response(200, 'The requested product.', exampleJson: '{"id": 1, "name": "Laptop", "price": "1200.50"}')]
    #[Response(404, 'Product not found.', exampleJson: '{"error": "Product not found"}')]
    public function show(int $id): ?array
    {
        $product = Product::find($id);
        if (!$product) {
            http_response_code(404);
            return ['error' => 'Product not found'];
        }
        return $product;
    }

    #[Route('/api/products', method: 'POST')]
    #[Summary('Create a new product')]
    #[Param('name', 'string', 'body', true, 'Name of the product.')]
    #[Param('price', 'number', 'body', true, 'Price of the product.')]
    #[Response(201, 'Product created successfully.', exampleJson: '{"id": 2, "name": "Mouse", "price": "25.00"}')]
    public function store(): array
    {
        // In a real app, you would get data from the request body.
        $data = ['name' => 'Mouse', 'price' => 25.00];
        Product::insert($data);
        // A more complete implementation would fetch and return the created product.
        return $data;
    }

    #[Route('/api/products/{id}', method: 'PUT')]
    #[Summary('Update a product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product to update.')]
    #[Response(200, 'Product updated successfully.', exampleJson: '{"message": "Product updated"}')]
    #[Response(404, 'Product not found.', exampleJson: '{"error": "Product not found"}')]
    public function update(int $id): array
    {
        $data = ['price' => 29.99];
        $affectedRows = Product::where('id', '=', $id)->update($data);

        if ($affectedRows === 0) {
            http_response_code(404);
            return ['error' => 'Product not found or no changes made'];
        }
        return ['message' => 'Product updated'];
    }

    #[Route('/api/products/{id}', method: 'DELETE')]
    #[Summary('Delete a product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product to delete.')]
    #[Response(204, 'Product deleted successfully.')]
    #[Response(404, 'Product not found.', exampleJson: '{"error": "Product not found"}')]
    public function destroy(int $id): ?array
    {
        $affectedRows = Product::where('id', '=', $id)->delete();
        if ($affectedRows === 0) {
            http_response_code(404);
            return ['error' => 'Product not found'];
        }
        return null; // Will be converted to a 204 No Content response.
    }
}
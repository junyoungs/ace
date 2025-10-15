<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Attributes\Summary;
use APP\Attributes\Param;
use APP\Attributes\Response;
use APP\Services\ProductService;

class ProductController extends \ACE\Http\Control
{
    public function __construct(
        private ProductService $productService
    ) {}

    #[Summary('List all products')]
    #[Response(200, 'A list of products.')]
    public function getIndex(): array
    {
        return $this->productService->getAllProducts();
    }

    #[Summary('Create a new product')]
    #[Response(201, 'The created product.')]
    public function postStore(): array
    {
        // In a real app, you would get data from the request.
        // $data = $this->request->getParsedBody();
        $data = ['name' => 'New Product from Service', 'price' => 123.45];
        $this->productService->createProduct($data);
        return $data;
    }

    #[Summary('Get a single product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product.')]
    #[Response(200, 'The requested product.')]
    #[Response(404, 'Product not found.')]
    public function getShow(int $id): ?array
    {
        $product = $this->productService->findProductById($id);
        if (!$product) {
            http_response_code(404);
            return ['error' => 'Product not found'];
        }
        return $product;
    }

    #[Summary('Update a product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product to update.')]
    #[Response(200, 'Product updated successfully.')]
    #[Response(404, 'Product not found.')]
    public function putUpdate(int $id): array
    {
        // $data = $this->request->getParsedBody();
        $data = ['name' => 'Updated Product'];
        $affectedRows = $this->productService->updateProduct($id, $data);

        if ($affectedRows === 0) {
            http_response_code(404);
            return ['error' => 'Product not found or no changes made'];
        }
        return ['message' => 'Product updated'];
    }

    #[Summary('Delete a product')]
    #[Param('id', 'integer', 'path', true, 'The ID of the product to delete.')]
    #[Response(204, 'Product deleted successfully.')]
    #[Response(404, 'Product not found.')]
    public function deleteDestroy(int $id): ?array
    {
        $affectedRows = $this->productService->deleteProduct($id);
        if ($affectedRows === 0) {
            http_response_code(404);
            return ['error' => 'Product not found'];
        }
        return null;
    }
}
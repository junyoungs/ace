<?php declare(strict_types=1);

namespace APP\Services;

use APP\Models\Product;

class ProductService
{
    public function getAllProducts(): array
    {
        return Product::getAll();
    }

    public function findProductById(int $id): ?array
    {
        return Product::find($id);
    }

    public function createProduct(array $data): int
    {
        // In a real application, you would add validation logic here.
        return Product::create($data);
    }

    public function updateProduct(int $id, array $data): int
    {
        return Product::update($id, $data);
    }

    public function deleteProduct(int $id): int
    {
        return Product::delete($id);
    }
}
<?php declare(strict_types=1);

namespace APP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Response
{
    public function __construct(
        public int $statusCode,
        public string $description,
        public string $contentType = 'application/json',
        public ?string $exampleJson = null
    ) {}
}
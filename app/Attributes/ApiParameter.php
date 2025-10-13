<?php declare(strict_types=1);

namespace APP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Param
{
    public function __construct(
        public string $name,
        public string $type,
        public string $in = 'query',
        public bool $required = true,
        public string $description = ''
    ) {}
}
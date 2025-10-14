<?php declare(strict_types=1);

namespace APP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Summary
{
    public function __construct(
        public string $summary
    ) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class Description
{
    public function __construct(
        public string $description
    ) {}
}
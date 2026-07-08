<?php

declare(strict_types=1);

namespace App\Core\Settings;

final readonly class UpdateSettingInput
{
    public function __construct(
        public string $key,
        public mixed $value,
    ) {}
}

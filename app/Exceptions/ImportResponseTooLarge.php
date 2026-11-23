<?php

namespace App\Exceptions;

use RuntimeException;

class ImportResponseTooLarge extends RuntimeException
{
    public function __construct(string $message = 'Trang nguồn vượt quá giới hạn 2 MB.')
    {
        parent::__construct($message);
    }
}

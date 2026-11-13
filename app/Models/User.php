<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function all(): array
    {
        return \DB::getall('users');
    }
}

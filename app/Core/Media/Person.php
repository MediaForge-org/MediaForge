<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $kind
 */
class Person extends Model
{
    use HasUlids;

    protected $table = 'people';

    protected $guarded = ['id'];
}

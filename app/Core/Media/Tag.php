<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $namespace
 */
class Tag extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'tags';

    protected $guarded = ['id'];
}

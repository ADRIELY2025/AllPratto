<?php

declare(strict_types=1);

namespace App\Controller;

use app\trait\DatabaseValueNormalizer;
use app\trait\Response;
use app\trait\Template;

abstract class Base
{
    use Template, Response, DatabaseValueNormalizer;
}

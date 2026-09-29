<?php

declare(strict_types=1);

namespace Tests\Fixtures\Action;

use Hit\Attribute\Get;
use Hit\Attribute\Post;

#[Get('multi-get')]
#[Post('multi-post')]
class MultiRouteAction {}

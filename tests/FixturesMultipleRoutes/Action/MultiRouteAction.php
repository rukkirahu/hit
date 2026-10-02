<?php

declare(strict_types=1);

namespace Tests\FixturesMultipleRoutes\Action;

use Hit\Attribute\Get;
use Hit\Attribute\Post;

#[Get('first')]
#[Post('second')]
class MultiRouteAction {}

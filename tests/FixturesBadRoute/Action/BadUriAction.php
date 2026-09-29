<?php

declare(strict_types=1);

namespace Tests\FixturesBadRoute\Action;

use Hit\Attribute\Get;

#[Get('/leading-slash')]
class BadUriAction {}

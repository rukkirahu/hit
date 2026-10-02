<?php

declare(strict_types=1);

namespace Tests\Fixtures\Action;

use Hit\Attribute\Acl;
use Hit\Attribute\Get;

#[Get('sample', acl: Acl::USER)]
class SampleGetAction {}

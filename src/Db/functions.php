<?php

declare(strict_types=1);

namespace Hit\Db;

use Hit\Domain\Domain;

function query(Domain $domain, ?Dto $dto = null): Query {
	return new Query($domain, $dto);
}

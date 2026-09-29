<?php

declare(strict_types=1);

namespace Hit\View;

class ContentType {
	const ANY = '*/*';
	const HTML = 'text/html';
	const JSON = 'application/json';
	const FORMDATA = 'application/x-www-form-urlencoded';
	const API_JSON = 'application/vnd.api+json';
	const REACT_JSON = 'application/vnd.react+json';
	const ALL_JSON = [self::JSON, self::API_JSON, self::REACT_JSON];
}

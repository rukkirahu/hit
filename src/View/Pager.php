<?php

declare(strict_types=1);

namespace Hit\View;

use InvalidArgumentException;

class Pager {
	public readonly int $next;
	public readonly int $prev;
	/** @var array<int> */
	public readonly array $pages;

	public function __construct(
		public readonly int $page,
		public readonly int $count,
	) {
		if ($page < 1 || $count < 1 || $page > $count) {
			throw new InvalidArgumentException("page {$page} out of range for count {$count}");
		}

		$this->pages = self::window($page, $count);
		$this->prev = $page > 1 ? $page - 1 : 0;
		$this->next = $page < $count ? $page + 1 : 0;
	}

	/**
	 * Window of up to 7 page numbers around the current page; 0 marks an
	 * ellipsis gap between the window and a pinned first/last page.
	 *
	 * @return array<int>
	 */
	private static function window(int $page, int $count): array {
		if ($count === 1) {
			return [];
		}

		/** @var list<int> $pages */
		$pages = range(1, $count);
		if (count($pages) <= 7) {
			return $pages;
		}

		$pages = array_slice($pages, max(min($count - 7, $page - 4), 0), 7);
		if ($pages[0] !== 1) {
			$pages[0] = 1;
			$pages[1] = 0;
		}
		if ($pages[6] !== $count) {
			$pages[6] = $count;
			$pages[5] = 0;
		}

		return $pages;
	}
}

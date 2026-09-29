<?php

declare(strict_types=1);

namespace Hit\View\Component;

use Hit\Lingo\Lingo;
use Hit\View\Pager;

final class Table {
	public static function pager(Pager $pager, bool $swap = true): string {
		ob_start();
		?>
<div id="pager" <?= $swap ? ' hx-swap-oob="true"' : '' ?>>
	<?php if ($pager->pages): ?>
		<input id="pg-page" type="hidden" name="p" class="th-filter" value="<?= $pager->page ?>" />
		<?php if ($pager->prev): ?>
			<a tabindex="0" id="pg-prev" _="on click set #pg-page.value to <?= $pager->prev ?> then trigger pager">
				<?= Lingo::get('pager.prev') ?>
			</a>
		<?php else: ?>
			<a class="disabled" id="pg-prev"><?= Lingo::get('pager.prev') ?></a>
		<?php endif ?>
		<span _="on click if event.target.text then set #pg-page.value to event.target.text then trigger pager">
			<?php foreach ($pager->pages as $page): ?>
			<?= match (true) {
				$page === $pager->page => "<a href=\"#\" class=\"current\">{$page}</a>\n",
				(bool) $page => "<a href=\"#{$page}\">{$page}</a>\n",
				true => '<span>…</span>',
			} ?>
			<?php endforeach ?>
		</span>
		<?php if ($pager->next): ?>
			<a tabindex="0" id="pg-next" _="on click set #pg-page.value to <?= $pager->next ?> then trigger pager">
				<?= Lingo::get('pager.next') ?>
			</a>
		<?php else: ?>
			<a class="disabled" id="pg-next"><?= Lingo::get('pager.next') ?></a>
		<?php endif ?>
	<?php endif ?>
</div>
<?php

		return ob_get_clean() ?: '';
	}

	public static function order(string $name, string $title, string $value): string {
		$desc = $value === "{$title}.desc" ? 'checked' : '';
		$asc = $value === "{$title}.asc" ? 'checked' : '';
		$name = Html::h($name);
		$title = Html::h($title);

		return <<<HTML
			<div class="order">
				<input type="radio" class="desc" name="{$name}" value="{$title}.desc" {$desc} />
				<input type="radio" class="asc" name="{$name}" value="{$title}.asc" {$asc} />
			</div>
			HTML;
	}
}

# View

Base view class + helpers for HTML output, HTMX, and pagers.

## Base view

Extend `Hit\View\View` and implement `render()`:

```php
use Hit\View\View;

class UserView extends View {
    public function render(): void {
        ?><h1>Hello</h1><?php
    }
}

$view = new UserView();
echo $view;               // captured, minified HTML output
```

`__toString()` captures `render()` output, runs it through `minify()`, and cleans the output buffer even when an exception occurs.

## Components

| Class | Purpose |
| --- | --- |
| `Html` | URL helpers, link/button tags, and route-aware ACL checks. Call `Html::init($baseurl, $isRouteAllowed)` first. |
| `Form` | Form helpers, flags, and error attributes. |
| `Table` | Pagination and HTMX-aware table markup. |
| `Pager` | Windowed page-number calculation. |

## Pager

```php
use Hit\View\Pager;

$pager = new Pager(page: 3, count: 10);
// $pager->next, $pager->prev, $pager->pages
```

## ContentType

`View::ContentType` is `HTML` by default. `minify()` only runs when rendering HTML; other content types are returned unchanged.

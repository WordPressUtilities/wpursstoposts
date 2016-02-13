# WPU RSS to posts

Easily import RSS into posts

Add a feed
---

```php
add_filter('wpursstoposts_feeds', 'basic_wpursstoposts_feeds', 10, 1);
function basic_wpursstoposts_feeds($feeds) {
    $feeds[] = 'http://darklg.me/feed/';
    return $feeds;
}
```


Todo
---

* [ ] Add sources with an admin page.
* [ ] Set crontab to avoid background checks.
* [ ] Import images & enclosures.
* [ ] More hooks.


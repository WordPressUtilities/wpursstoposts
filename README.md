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

Hooks
---

* wpursstoposts_maxitems : (int) Numbers of items parsed (default: 15).
* wpursstoposts_importimg : (bool) Import post images or not. (default: true).
* wpursstoposts_posttype : (string) Post type id used to store imported items (default: rss).
* wpursstoposts_posttype_info : (array) Args for default post type.
* wpursstoposts_feeds : (array) URLs of feeds to parse (default: empty).


Todo
---

* [*] Set crontab to avoid background checks.
* [*] Import images.
* [*] More hooks.
* [ ] Create taxonomy from feed url.
* [ ] Add sources from an admin page.
* [ ] Settings from an admin page.
* [ ] Create post thumbnail with first image.


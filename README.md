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
* wpursstoposts_posttype : (string) Post type slug used to store imported items (default: rssitems).
* wpursstoposts_posttype_info : (array) Args for default post type.
* wpursstoposts_taxonomy : (string) Taxonomy slug used to store feeds (default: rssfeeds).
* wpursstoposts_taxonomy_info : (array) Args for default taxonomy.
* wpursstoposts_feeds : (array) URLs of feeds to parse (default: empty).


Todo
---

* [x] Set crontab to avoid background checks.
* [x] Import images.
* [x] More hooks.
* [x] Create taxonomy from feed url.
* [ ] Use WPU Post types & Taxos if available.
* [ ] Add sources from an admin page.
* [ ] Settings from an admin page.
* [ ] Create post thumbnail with first image.
* [ ] Enhance import perfs for multiples feeds.


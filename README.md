# Install 

`composer require jufist/wp-core`

Composer.json

```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/oliverhuynh/jufist-wp-core",
      "options": {
                "symlink": false
            }
        }
    ],
    "minimum-stability": "dev"
```



## Usage

```php

// Autoload composer classses...
require "vendor/autoload.php";
use Jufist\WpCore;

class YourClass extends WpCore {
  // Your custom methods and inits
  function InitPlugin() {
    parent::InitPlugin();
    // Your custom init
  }
}

$yourclass = YourClass::GetInstance();
$yourclass->InitPlugin();


```
```composer
composer require "jufist/wp-core @dev"
```

# For AloneCore

Put these to 3rdparty PHP

```
wp_head();
wp_footer();
```

# For webpack/gulp
prototype --setup --webpack

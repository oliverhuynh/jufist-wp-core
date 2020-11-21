# Install 

`composer require jufist/wp-core`

Composer.json
```
"repositories": [
    {
        "type": "path",
        "url": "/my-projects/jufist-wp-core",
	"options": {
            "symlink": false
        }
    }
],
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


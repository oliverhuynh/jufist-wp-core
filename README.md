# Install 

`composer require jufist/wp-core`


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


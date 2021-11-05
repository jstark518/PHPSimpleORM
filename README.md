# PHPSimpleORM
SimpleORM is an object-relational mapper for PHP & MySQL (using mysqli).
This project is currently under development and should not be used for production.

```php
use SimpleORM\DB_Config;
use SimpleORM\Model;

include_once("vendor/autoload.php");

class Users extends Model {

}

DB_Config::getConfig()->host = "localhost";
DB_Config::getConfig()->user = "root";
DB_Config::getConfig()->password = "";
DB_Config::getConfig()->db = "db";
DB_Config::getConfig()->onError = function($query, $e) {
    echo "$query failed:<br>";
    echo $e->error;
};

$users = Users::All();
foreach($users as $user) {
    echo $user->name;
}
```

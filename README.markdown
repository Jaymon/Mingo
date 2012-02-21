# What is Mingo?

Mingo is an easy to use database abstraction layer that uses the db as a schema-less document storage engine based on [how Friendfeed uses MySql](http://bret.appspot.com/entry/how-friendfeed-uses-mysql).

Currently, by default, it can use  MySQL, SQLite, or PostgreSQL databases as the backend. I've updated the code quite a bit so I've disabled the Mongo, Lucene, and Postgres Hstore interfaces until I can refactor and update them. 

You can add any other databases easily by extending `MingoInterface` and implementing all the required methods.

# Using Mingo

Using Mingo is as easy as creating a new class that extends MingoOrm. For our example we'll create a User class:

    class User extends MingoOrm {

      /*
       *  every child that extends MingoOrm needs to implement this method
       */
      protected function populateTable(MingoTable $table){
        
        // we don't have to define all the fields, but it helps with indexes
        
        // the username is a string of 0, 32 characters
        $field = new MingoField('username',MingoField::TYPE_STR,array(0,32));
        
        // the password is a hash of 32 characters
        $field = new MingoField('password',MingoField::TYPE_STR,32);
        
        // now set an index, the index name is "un_and_pw" and it uses the username, and password fields
        $table->setIndex('un_and_pw',array('username','password'));
      
      }//method

    }//class

that's all the setup you really need to do, pretty much everything else is handled by Mingo, including table and index creation.

Let's see our new `User` object in action, first we need to connect to our interface:

    // SQLite...
    $config = new MingoConfig();
    $config->setName('/path/to/db.sqlite'); //db name
    
    $db = new MingoSQLiteInterface();

    // mySQL and PostgreSQL...
    $config = new MingoConfig();
    $config->setName('db'); //db name
    $config->setHost('localhost'); // db host
    $config->setUsername('username');
    $config->setPassword('****');
    
    $db = new MingoMySQLInterface(); // for Mysql
    $db = new MingoPostgreSQLInterface(); // for Postgres
    
    $config->setDebug(true); // better debugging/logging for test, set to false for production code
    
    $db->connect($config);

Now, we can create a `User` and save it into the db:

    $user = new User();
    $user->setDb($db);
    
    $user->setUsername('tester');
    $user->setPassword(md5('1234'));
    $user->set();

and that's that, the `User` is now saved (notice we didn't have to do anything crazy like create a table. Mingo handles all that for us). To make sure, let's load him up into a new `user` instance:

    $query = new MingoQuery('User',$db);
    $user_iterator = $query->get();

    foreach($user_iterator as $user){
     echo $user->getUsername(),' - ',$user->getPassword(),PHP_EOL;
    }//foreach

this is just a basic example but it shows you how easy Mingo is to use. each class extended from `MingoOrm` has many built in magic functions to make doing things easy. You just saw the `set*()` and `get*()` magic functions, but you also have `has*()` magic functions to make sure a field exists and is non-empty (eg, `$user->hasPassword()` to see if the password field exists and is non-empty). The `exists*()` to just see if a field exists (it could be empty). The `kill*()` functions to remove a field. The `bump*()` functions to increment the field by count (eg, `$user->bumpView(1)`). And `is*()` functions to see if a field contains a value (eg, `$user->isUsername('tester')`). You can also reach into arrays with all the methods:

    $user = new User();
    $user->setField('attributes',array('foo' => 1,'bar' => 2));
    $user->getField(array('attributes','foo')); // 1
    $user->isField(array('attributes','foo'),1); // true


To query, you can use a `MingoQuery` class instance to make getting results from the db painless. For example, to get all users with certain usernames, you could:

    $query = new MingoQuery('User',$db);
    $user_iterator = $query->inUsername('tester','john','paul','homer','bart')->get();
    echo 'loaded this many users: ',count($user_iterator),PHP_EOL;

And, if you wanted to sort them in alphabetical order, you would just query this:

    $user_iterator = $query->inUsername('tester','john','paul','homer','bart')->ascUsername()->get();

# Installing Mingo

I actually have only been using Mingo on php >=5.2.4 (including php >=5.3), but I think it might work with any php >=5.0. If you are using the SQL driver, you must have `PDO` (which I think is built-in with php >=5.0), if you are using Mongo, you must have installed the Mongo php extension.

If yo have `PHPUnit` installed then you should be able to run the unit tests found in the `test/phpunit` folder (though you may have to edit the connection variables in any of the interface tests to use your versions of whatever db you want to use).

The code should run on both Windows and Linux.

# Using Mingo to develop

Mingo tries to recover if a given table isn't found by trying to create the table on the fly and creating any new indexes. Usually, it will only have to recover from not having a table once, this makes it really easy to deploy new `MingoOrm` classes since tables will be automatically generated the first time Mingo can't find them (kind of like an automatic install script). This allows you to push to production and not have to worry about running an install script or anything for many cases and gives you the flexibility of creating an install script only when you need to do special things.

# Using Mingo with Symfony 1.4

1. Move `MingoPlugin` (from `Mingo/Symfony`) into your Symfony application's plugin directory (so the final path would be something like: `Your/Symfony/Application/plugins/MingoPlugin`).

2. Copy the rest of `Mingo/` into the `Your/Symfony/Application/plugins/MingoPlugin/lib/extlib` directory. So the final folder structure (files not shown, but they should be there also) should look something like:

        plugins/
          MingoPlugin/
            config/
            lib/
              database/
              task/
              extlib/
                interfaces/
                cli/

If you don't plan on using Mingo with Symfony 1.4, you can safely delete the MingoPlugin folder.

# Other

## Mingo in the press:

[Techcrunch Jan, 12, 2010](http://www.techcrunch.com/2010/01/12/plancast-facebook-events/)
"Plancast has started using a 'No SQL' solution for some of their data. More tech-savvy readers may recognize that is also a solution FriendFeed is using on their backend, as Facebook's Bret Taylor wrote about at length [here](http://bret.appspot.com/entry/how-friendfeed-uses-mysql). Plancast has [open-sourced](http://github.com/Jaymon/Mingo) their version of this."

## Mingo in action

[Plancast.com](http://plancast.com).

## License

[The MIT License](http://www.opensource.org/licenses/mit-license.php)


# What is Mingo?

Mingo is an easy to use database abstraction layer that uses the db as a schema-less document storage engine based on [how Friendfeed uses MySql](http://bret.appspot.com/entry/how-friendfeed-uses-mysql).

Currently, it can use either Mysql/sqlite, or Mongo Db as its backend. But other databases can easily be added by extending `MingoInterface` and implementing all the required methods.

# Using Mingo

Using Mingo is as easy as creating a new class that extends MingoOrm. For our example we'll create a User class:

    class User extends MingoOrm {

      /*
       *  every child that extends MingoOrm needs to implement this method
       */
      protected function populateTable(MingoTable $table){
        
        // set up the this object's table schema...
        $table->setIndex('username','password');
      
      }//method

    }//class

that's all the setup you really need to do, pretty much everything else is handled by Mingo.

Let's see our new `User` object in action, first we need to connect to our interface:

    // sqlite...
    $db = new MingoSQLiteInterface();
    $db->setName('/path/to/db.sqlite'); //db name

    // mysql...
    $db = new MingoMySQLInterface();
    $db->setName('db'); //db name
    $db->setHost('localhost'); // db host
    $db->setUsername('username');
    $db->setPassword('****');

    // mongo db...
    $db = new MingoMongoInterface();
    $db->setName('db'); //db name
    $db->setHost('localhost:27017'); // db host
    $db->setUsername('');
    $db->setPassword('');

    $db->setDebug(true); // better debugging/logging for test, set to false for production code
    $db->connect();

Now, we can create a `User` and save it into the db:

    $user = new User();
    $user->setDb($db);
    
    $user->setUsername('tester');
    $user->setPassword('1234');
    $user->set();

and that's that, the `User` is now saved. To make sure, let's load him up into a new `user` instance:

    $user_load = new User();
    $user_load->setDb($db);
    
    $user_load->load();
    foreach($user_load as $u){
     echo $u->getUsername(),' - ',$u->getPassword(),PHP_EOL;
    }//foreach

this is just a basic example, but it shows you the ease of use of Mingo. each class extended from `MingoOrm` has many built in magic functions to make doing things easy. You just saw the set* and get* magic functions, but you also have has* magic functions to make sure a field exists and is non-empty (eg, `$user->hasPassword()` to see if the password field exists and is non-empty). The exists* to just see if a field exists (it could be empty). The kill* functions to remove a field. The bump* functions to increment the field by count (eg, `$user->bumpView(1)`). And is* functions to see if a field contains a value (eg, `$user->isUsername('tester')`). You can also reach into arrays with all the methods:

    $user = new User();
    $user->setField('attributes',array('foo' => 1,'bar' => 2));
    $user->getField(array('attributes','foo')); // 1
    $user->isField(array('attributes','foo'),1); // true


On top of that, the `load()` function can take a `MingoCriteria` instance to make loading from the db painless. For example, to load all users with certain usernames, you could:

    $c = new MingoCriteria();
    $c->inUsername('tester','john','paul','homer','bart');
    $loaded = $user->load($c);
    echo 'loaded this many users: ',$loaded,PHP_EOL;

And, if you wanted to sort them in alphabetical order, you would just add this line to the criteria before calling `load()`:

    $c->ascUsername();

# One Class to rule them all

Now, most ORMs and abstraction layers have a peer or table class (atleast the ones that I've used) that takes care of db loads and sets, and then they have another class that is the actual ORM for the db's table. Mingo combines the two, this will take some getting used to at first but is actually pretty cool when you start using it. When a `MingoOrm` instance is handling more than one row, then any method calls, like setUsername(), set(), or kill() will affect all the rows that the instance represents (use `isMulti()` to know if the instance is representing more than one row). Let's take a look at an example using the `User` class from above.

### Let's add some users into a SQLite database and then load them up

    $db = new MingoSQLiteInterface();
    $db->setName(sys_get_temp_dir().'userdb.sqlite');

    $u1 = new User();
    $u1->setDb($db);
    
    $u1->setUsername('foo');
    $u1->setPassword('1234');
    $u1->set();
    
    $u2 = new User();
    $u2->setDb($db);
    
    $u2->setUsername('bar');
    $u2->setPassword('4321');
    $u2->set();
    
    // now let's look at how load() and loadOne() differ...
    
    $user = new User();
    $user->setDb($db);
    
    // use loadOne() to have your $user instance represent one row...
    $c = new MingoCriteria();
    $c->isUsername('foo');
    $user->loadOne($c);
    
    echo $user->getUsername(); // 'foo'
    echo $user->isMulti() ? 'TRUE' : 'FALSE'; // 'FALSE'
    
    $user->setUsername('che');
    echo $user->getUsername(); // 'che'

    // use load() to have your $user instance represent multiple rows...
    $c = new MingoCriteria();
    $c->inUsername('foo','bar');
    
    $user->load($c);
    
    echo $user->getUsername(); // array('foo','bar')
    echo $user->isMulti() ? 'TRUE' : 'FALSE'; // 'TRUE'

    $user->setUsername('che');
    echo $user->getUsername(); // array('che','che')
    
    // load() always makes the $user instance act like it represents multiple rows
    // even when there is only one row loaded...
    $c = new MingoCriteria();
    $c->isUsername('foo');
    
    $user->load($c);
    
    echo $user->getUsername(); // array('foo')
    echo $user->isMulti() ? 'TRUE' : 'FALSE'; // 'TRUE'

    $user->setUsername('che');
    echo $user->getUsername(); // array('che')

# Installing Mingo

I actually have only been using Mingo on php >=5.2.4 (including 5.3), but I think it might work with any php >=5.0. If you are using the SQL driver, you must have `PDO` (which I think is built-in with php >=5.0), if you are using Mongo, you must have installed the Mongo php extension.

If yo have `PHPUnit` installed then you should be able to run the unit tests found in the `test/phpunit` folder (though you may have to edit the connection variables in any of the interface tests to use your versions of whatever db you want to use).

The code should run on both Windows and Linux.

# Using Mingo to develop

Mingo tries to recover if a given table isn't found by trying to create the table on the fly and creating any new indexes. Usually, it will only have to recover from not having a table once, this makes it really easy to deploy new `MingoOrm` classes since tables will be automatically generated the first time Mingo can't find them (kind of like an automatic install script). This allows you to push to production and not have to worry about running an install script or anything for many cases and gives you the flexibility of creating an install script only when you need to do special things.

# Using Mingo with Symfony 1.4

- Move `MingoPlugin` (from `Mingo/Symfony`) into your Symfony application's plugin directory (so the final path would be something like: `Your/Symfony/Application/plugins/MingoPlugin`).

- Copy the rest of `Mingo/` into the `Your/Symfony/Application/plugins/MingoPlugin/lib/` directory. I like to put it into `Your/Symfony/Application/plugins/MingoPlugin/lib/extlib` to make it easier to update in the future. So the final folder structure should look something like:

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


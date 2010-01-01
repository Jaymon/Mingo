h1. Mingo

An easy to use database abstraction layer that uses the db as a dumb key/value storage engine similar to what is described here:

http://bret.appspot.com/entry/how-friendfeed-uses-mysql

Currently, it can use either Mysql/sqlite, or Mongo Db as its backend, but other databases can easily be added by extending mingo_db_interface_class and implementing all the required methods (see mingo_db_sql_class or mingo_db_mongo_class for how to do that).

h2. Using Mingo

using it is as easy as creating a new class that extends mingo_orm. For our example we'll create a user class:

<code>
class user extends mingo_orm {

  function __construct(){
  
    // the parent constructor is run first to initiate schema...
    parent::__construct();
    
    // set up the this object's table schema...
    $this->schema->setIndex('username','password');
  
  }//method

}//class
</code>

that's all the setup you really need to do, pretty much everything else is handled by mingo.

Let's see our new user object in action, first we need to connect to our db:

<code>
// sqlite...
$type = mingo_db::TYPE_SQLITE;
$db_name = 'model.sqlite';
$host = '';

// mysql...
$type = mingo_db::TYPE_MYSQL;
$db_name = 'model';
$host = 'localhost';
$username = '';
$password = '';

// mongo db...
$type = mingo_db::TYPE_MONGO;
$db_name = 'model';
$host = 'localhost:27017';
$username = '';
$password = '';
</code>

Now, let's do the actual connecting:

<code>
// activate singleton...
$db = mingo_db::getInstance();
$db->setDebug(true); // activate mingo agile mode
$db->connect($type,$db_name,$host,$username,$password);
</code>

Now that we're connected let's create a user and save it into the db:

<code>
$user = new user();
$user->setUsername('tester');
$user->setPassword('1234');
$user->set();
</code>

and that's that, the user is now saved. To make sure, let's load him up into a new user instance:

<code>
$user_load = new user();
$user_load->load();
foreach($user_load as $u){
 echo $u->getUsername(),'<br />',$u->getPassword(),'<br />';
}//foreach
</code>

this is just a basic example, but it shows you the initial power of Mingo. each class extended from <code>mingo_orm</code> has many built in functions to make doing things easy, you just saw the set* and get* functions, but you also have has* functions to make sure a field exists and is non-empty (eg, <code>$user->hasPassword()</code> to see if the password field exists and is non-empty). The exists* to just see if a field exists (it could be empty). The kill* functions to remove a field. The bump* functions to increment the field by count (eg, <code>$user->bumpView($count)</code> where $count=1). And is* functions to see if a field contains a value (eg, <code>$user->isUsername('tester')</code>).

On top of that, the load() function takes a <code>mingo_criteria</code> instance to make loading from the db painless. For example, to load all users with certain usernames, you could:

<code>
$c = new mingo_criteria
$c->inUsername('tester','john','paul','homer','bart');
$loaded = $user->load($c);
echo 'loaded this many users: ',$loaded,'<br />';
</code>

and, if you wanted to sort them in alphabetical order, you would just add this line to the criteria before calling load():

<code>
$c->sortUsername(mingo_criteria::ASC);
</code>

Now, most ORMs and abstraction layers have a peer or table class (atleast the ones that I've used) that takes care of db loads and sets, and then they have another class that is the actual ORM for the db's table. I decided to combine the two and just have the one class, this will take some getting used to at first but is actually pretty cool when you start using it. And if you wanted to create a Peer class, you could:

<code>
class user_peer {
  static function getByUsername($list){
  
    $c = new mingo_criteria();
    $c->inUsername($list);
    $c->sortUsername(mingo_criteria::ASC);
    $user = new user();
    $user->load($c);
    return $user->get();
  
  }//method

}//class
</code>

That will produce the traditional array list of user instances instead of having the one user instance handle all the loaded rows. When a user instance is handling more than one row, then any method calls, like setUsername(), set(), or kill() will affect all the rows that instance represents.

the code is pretty well documented, so if you want to see what other magic functions <code>mingo_map</code> and <code>mingo_criteria</code> have, just check the docblocks and code for each class's __call() method.

h2. Installing Mingo

I actually have only been using Mingo on php >=5.3, but I think it might work with any php >=5.0. If you are using the SQL drivers, you must have <code>PDO</code>, if you are using Mongo, you must have installed the Mongo php extension. 

Since Mingo was originally designed for Mongo that was the first part of the codebase I wrote, but I haven't actaully tested it in quite a while since I switched to the SQL development, so the Mongo code might actually be a little outdated, when I have some time, I'll update it and make sure it works like it should, but until then, you're on your own.

When debug is on Mingo is actually quite agile, meaning it will make sure tables exist and check indexes with every db call. This slows down the overall code because mingo is making extra db calls to make sure stuff exists, but allows you to make schema changes on the fly (like adding a new index you didn't know you needed until just now) without worrying about doing a db migration or anything.

The one problem with this approach is that you won't want debug on when in a production environment because you don't want your live server making unecessary queries when the tables or the schema are unlikely to change for long periods of time. So you will probably need to keep a "production" install script handy that does something like this:

<code>
$user = new user();
$user->install();
// repeat above code for every other ORM class you have created
</code>

This isn't the best approach in the world, but it allows you to make on the fly changes while you're developing and when you do run the install script on a fresh db you will get only the latest greatest tables and indexes installed.
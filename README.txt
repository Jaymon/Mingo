to start using this, make sure you have Mongo installed and running on your system, and the Mongo php extensions installed.

using it is as easy as creating a new class that extends mingo_map. For our example we'll create a user class:

class user extends mingo_map {}//class

that's really everything you need to do for a basic example, so now, in your code, you can just create a new user instance:

$db_name = 'model';
$host = 'localhost:27017';
// activate singleton...
$db = mingo_db::getInstance();
$db->connect($db_name,$host);

$user = new user();

mingo_map will automatically create the table (user, named after the class that extends mingo_map) and get an instance of the mingo_db singleton, so you don't have to worry about it. Now, let's work with our new user instance:

$user->setUsername('tester');
$user->setPassword('1234');

and, let's go ahead and save our new user into mongo:

$user->set();

and that's that, the user is now saved. To make sure, let's load him up into a new user instance:

$user_load = new user();
$user_load->load();
foreach($user_load as $u){
 echo $u->getUsername(),'<br />',$u->getPassword(),'<br />';
}//foreach

this is just a basic example, but it shows you the initial power of mingo. each class extended from mingo_map has many built in functions to make doing things easy, you see the set* and get* functions, but you also have has* functions to make sure a field exists and is none empty (eg, $user->hasPassword() to see if the password field exists and is non-empty). The exists* to just see if a field exists (it could be empty). The kill* functions to remove a field. The bump* functions to increment the field by count (eg, $user->bumpViews($count) where $count=1). And is* functions to see if a field contains a value (eg, $user->isUsername('tester')).

On top of that, the load() function takes a mingo_criteria to make loading from the mongo db painless. For example, to load all users with certain usernames, you could:

$c = new mingo_criteria
$c->inUsername('tester','john','paul','homer','bart');
$loaded = $user->load($c);
echo 'loaded: ',$loaded,'<br />';

and, if you wanted to sort them in alphabetical order, you would just add this line to the criteria before calling load():

$c->sortUsername(mingo_criteria::ASC);

Now, most ORMs and abstraction layers have a peer or table class (atleast the ones that I've used) that takes care of db loads, and then they have another class that is the actual ORM for the db's table. I decided to combine the two and just have the one class, this will take some getting used to at first, but it is actually pretty cool when you start using it. And if you wanted to create a Peer class you could:

class user_peer {
  static function getByUsername($list){
  
    $c = new mingo_criteria();
    $c->inUsername($list);
    $c->sortUsername(mingo_criteria::ASC);
    $user = new user();
    $user->load($c);
    return $user->get();
  
  }

}

that will produce the traditional array list of user instances instead of having the one $user instance handle all the loaded rows. When a user instance is handling more than one row any calls, like setUsername(), set(), or kill() will affect all the rows that instance represents.

the code is pretty well documented, so if you want to see what other magic functions mingo_map and mingo_criteria have, just check the docblocks and code for the __call() methods.

plans for the future:
1 - I still need to create the mingo_schema object that will allow you to create tables that are a set size, see: http://www.mongodb.org/display/DOCS/Capped+Collections and also set indexes and easily set the auto-increment field that mingo_db has built-in.
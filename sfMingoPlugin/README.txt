in your databases.yml file you can use the following params under the "mingo" name:

mingo:
    class:        sfMingoDatabase # the classname you want to use to hook into symfony
    param:
      mingo_orm:
        interface:  mingo_db_mysql # the classname you want to use as the interface
        name:       DATABASE_NAME
        host:       DATABASE_HOST
        username:   USERNAME
        password:   PASSWORD

The "mingo_orm" namespace is basically the default, but you can actually have different mingo orm classes use different databases. So, if you created a Foo class that should use mongo, but everything else should use sqlite, you would do something like:

mingo:
    class:        sfMingoDatabase
    param:
      Foo:
        interface:  mingo_db_mongo
        name:       DATABASE_NAME
        host:       localhost:27017     
      mingo_orm:
        interface:  mingo_db_sqlite
        dbname:     DATABASE_NAME
        
With the above config, any Foo instances would use mongo, and everything else would use sqlite.
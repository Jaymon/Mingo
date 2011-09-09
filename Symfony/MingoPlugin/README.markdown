in your databases.yml file you can use the following params under the "mingo" namespace:

    mingo:
        class:            MingoDatabase # the classname you want to use to hook into symfony
        param:
          servers:
            MingoOrm:
              interface:  MingoMySQLInterface # the classname you want to use as the interface
              name:       DATABASE_NAME
              host:       DATABASE_HOST
              username:   USERNAME
              password:   PASSWORD

The `MingoOrm` namespace is basically the default, but you can actually have different Mingo orm classes use different databases/servers. So, if you created a Foo class that should use mongo, but everything else should use sqlite, you would do something like:

    mingo:
        class:            MingoDatabase
        param:
          servers:
            Foo:
              interface:  MingoMongoInterface
              name:       DATABASE_NAME
              host:       localhost:27017     
            MingoOrm:
              interface:  MingoSQLiteInterface
              name:       DATABASE_NAME
        
With the above config, any Foo instances (or children that inherit from Foo) would use mongo, and everything else would use sqlite.
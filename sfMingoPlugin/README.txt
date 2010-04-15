in your databases.yml file you can use the following params under the "mingo" name:

mingo:
    class:        sfMingoDatabase # the callname you want to use to hook into symfony
    param:
      interface:  mingo_db_mysql # the classname you want to use as the interface
      dbname:     DATABASE_NAME
      host:       DATABASE_HOST
      username:   USERNAME
      password:   PASSWORD
      
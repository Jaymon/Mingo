in your databases.yml file you can use the following params under the "mingo" name:

mingo:
    param:
      type:       2 # check mingo_db::TYPE_* constants for the value to put here
      dbname:     DATABASE_NAME
      host:       DATABASE_HOST
      username:   USERNAME
      password:   PASSWORD
      
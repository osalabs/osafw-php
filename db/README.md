Create database:

```
CREATE DATABASE demodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE demodb;
CREATE USER 'user'@'localhost' IDENTIFIED BY 'PASSWORD';
GRANT ALL PRIVILEGES ON demodb.* TO 'user'@'localhost';
```

- apply fwdatabase.sql
- apply lookups.sql
- apply database.sql (your app specific tables)

Other files:
- demo.sql - use this as a template for new tables
- roles.sql - for role-based access, if used in project
- views.sql - for views, if used in project
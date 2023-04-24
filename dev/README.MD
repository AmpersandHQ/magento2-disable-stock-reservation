**Do not run these tests in production, they delete from and insert into your database as well as generate an integration token.**

To run these tests locally you will need to get a copy of the repo
```
git clone https://github.com/AmpersandHQ/magento2-disable-stock-reservation/
cd magento2-disable-stock-reservation
```

Boot the docker test instance for testing against (run `./vendor/bin/mtest-make` to see supported versions)
```
CURRENT_EXTENSION="." FULL_INSTALL=1 vendor/bin/mtest-make 2-4-5
```

Configure magento test settings
```
composer docker-configure-magento
```

Run the tests
```
composer docker-run-codeception
```


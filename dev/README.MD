**Do not run these tests in production, they delete from and insert into your database as well as generate an integration token.**

To run these tests locally you will need to get a copy of the repo
```
git clone https://github.com/AmpersandHQ/magento2-disable-stock-reservation/
cd magento2-disable-stock-reservation
```

Boot the docker test instance for testing against (run `./vendor/bin/mtest-make` to see supported versions)
```
TEST_GROUP=2-4-5 composer docker-install-magento
```

Configure magento test settings
```
composer docker-configure-magento
```

Run the tests
```
composer docker-run-unit-tests                           # unit tests
composer docker-run-integration-tests                    # integration tests
TEST_GROUP=2-4-5 composer docker-run-codeception         # codeception rest api tests
```


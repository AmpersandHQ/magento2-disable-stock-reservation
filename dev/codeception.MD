**Do not run these tests in production, they delete from and insert into your database as well as generate an integration token.**

To run these tests locally you will need to 

1. Install a vanilla magento instance locally
2. Install this module
3. `magerun2 config:store:set checkout/options/guest_checkout 1` 
4. `magerun2 config:store:set payment/checkmo/active 1`
5. `magerun2 integration:create disablestockres example@example.com https://example.com --access-token="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"`
6. `magerun2 cache:flush`
7. Run with the appropriate environment vars set, 
    
    ```
    URL="https://magento-disablestockres.localhost/" MYSQL_USER="root" MYSQL_HOST="127.0.0.1" MYSQL_DB="databasedisablestockres" MYSQL_PORT="3306" ./dev/run-codeception.sh
    ```

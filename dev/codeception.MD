**Do not run these tests in production, they delete from and insert into your database as well as generate an integration token.**

To run these tests locally you will need to 

1. Install a vanilla magento instance locally
2. Install this module, run `setup:upgrade`
3. `magerun2 config:store:set checkout/options/guest_checkout 1` 
4. `magerun2 config:store:set payment/checkmo/active 1`
5. `magerun2 config:store:set oauth/consumer/enable_integration_as_bearer 1`
6. `magerun2 integration:create disablestockres example@example.com https://example.com --access-token="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"`
7. `magerun2 cache:flush`
8. Run with the appropriate environment vars set, 
    
    ```
    URL="https://magento-community-245.ampdev.co/" MYSQL_USER="root" MYSQL_HOST="127.0.0.1" MYSQL_DB="community2.4.5" MYSQL_PORT="24010" ./dev/run-codeception.sh
    ```

webtestcase for symfony : 

scenario 1 happy path = register + login + mfa 
scenario 2 = register + login + mfa + resend email
scenario 3 = register + login + mfa + resend email too early
scenario 4 = register + login + mfa + code too late


php bin/phpunit Tests\Security\TwoFactorEmailFlowTest.php --filter testScenario1_happyPath_register_login_mfa


symfony console d:d:c --env=test 
symfony console d:m:m --env=test 
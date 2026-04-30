@echo off
setlocal

set "ROOT_DIR=%~dp0.."
php "%ROOT_DIR%\www\php\tests\run-local-phpunit.php" %*
set "EXIT_CODE=%ERRORLEVEL%"
exit /b %EXIT_CODE%

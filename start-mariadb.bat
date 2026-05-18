@echo off
cd /d "%~dp0"
"%~dp0tools\mariadb\mariadb-11.4.10-winx64\bin\mysqld.exe" --defaults-file="%~dp0tools\mariadb-data\my.ini" --port=3307 --console

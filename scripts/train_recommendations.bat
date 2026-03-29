@echo off
setlocal
php "%~dp0..\library\export_recommendation_data.php"
if errorlevel 1 exit /b %errorlevel%
python "%~dp0train_recommendations.py"
exit /b %errorlevel%

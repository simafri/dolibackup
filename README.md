# Dolibackup

Dolibackup is a dolibarr module for making external backups of the application

## Installation

Put the dolibackup folder on your dolibarr public directory or on the custom directory. Consequently, the module "Dolibackup" will appear on Home > Setup > Modules/Application and you'll need to activate it.

## Usage

Upon activation of the module, configure it by clicking the Setup icon. Fill all the information including the credentials of the server to store the backups. Once all the informations filled, save the settings. A cron task should have been added on Home > Admin Tools > Scheduled jobs called "DOLIBACKUP", you can check to make sure it has been well added and is active.
This module works by using scheduled tasks so make sure cron jobs are functionning in your system.
A backup will be sent to the remote FTP server each time the scheduled task is executed. The system will delete the oldest backup once there is more than 15 backups on the FTP server.

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License
[GLP](https://www.gnu.org/licenses/gpl-3.0.en.html)

More info : https://dolibackup.com
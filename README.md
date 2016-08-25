# publicScripts

To get scripts working:
(1) In your local environment, either (a) set publicScripts/docroot as your web server document root, or (b) move everything inside
the docroot folder into your web server document root, and everything else in this repo just outside of the document root.
(2) Update the editable portion of classes/DatabaseConnect.php to properly connect to whatever database you'll be using
(3) Update the editable portion of init.php so that the constant paths properly reflect what's in your environment

To get the csv importer working:
(1) In your server terminal, run the mysqlMigrations.php script in the scripts folder
(2) Once ALL other steps are successfully completed, go to www.yoursite.com/importer/importer.php to test the script
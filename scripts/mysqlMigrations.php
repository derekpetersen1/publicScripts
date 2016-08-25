<?

require_once("../init.php");

$con = DatabaseConnect::getConnection();

$sql = "CREATE TABLE `AutoMatch` (
  `AutoMatchID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `DateCreated` datetime NOT NULL,
  PRIMARY KEY (`AutoMatchID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$con->exec($sql);

$sql = "CREATE TABLE `AutoMatchTemplate` (
  `AutoMatchTemplateID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `AutoMatchID` int(11) unsigned NOT NULL,
  `CsvField` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `TableField` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`AutoMatchTemplateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$con->exec($sql);

$sql = "CREATE TABLE `User` (
  `UserID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `LastName` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `Address` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `City` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `State` varchar(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `Zip` int(10) NOT NULL,
  `Score` float NOT NULL,
  `DateCreated` datetime NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$con->exec($sql);
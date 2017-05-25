# Tweakers.net VraagAanbod scraper
Tweakers provides a RSS feed for all VA ads. this script allowes you to only look at a specific category.<br>
this script will poll and parse all Ads on the 1 page and check it with its previous state.<br>
if a new Ad has been found, it will notify you via Pushover.<br>
you will also be able to add keywords to the BOLO list. so a different priority can be assigned for specific items that you are looking for in this sub category.

![iOSPushoverExample](https://img.seosepa.net/php-tweakers-va-scraper2.png)

feel free to implement / change anything to suite your needs.<br>
this script will come without any garantuee of working.

**Keep our fellow IT crowd @ tweakers in mind when setting a poll rate, we do not want to cause any extra load or harm to there site.**

Requirements and Usage
==============

This script has been tested on Ubuntu 16.04 using PHP 7.0

1. clone this repo or download the check.php
2. use your favorite editor to open the file and edit private (config) variable in the beginning of the class
3. set up a cronjob


```
*/5 * * * * php /home/seosepa/check.php >> /home/seosepa/tweakers.log
```

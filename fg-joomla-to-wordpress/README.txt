=== FG Joomla to WordPress ===
Contributors: Kerfred
Plugin Uri: https://wordpress.org/plugins/fg-joomla-to-wordpress/
Tags: joomla, mambo, elxis, import, migration
Requires at least: 4.5
Tested up to: 6.6.1
Stable tag: 4.29.1
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=fred%2egilles%40free%2efr&lc=FR&item_name=fg-joomla-to-wordpress&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted

A plugin to migrate categories, posts, tags, images and other medias from Joomla to WordPress

== Description ==

This plugin migrates sections, categories, posts, images, medias and tags from Joomla to WordPress.

It has been tested with **Joomla versions 1.5 through 4.0** and the latest version of WordPress on huge databases. It is compatible with multisite installations.

Major features include:

* migrates Joomla sections as categories
* migrates categories as sub-categories
* migrates Joomla posts (published, unpublished and archived)
* migrates Joomla web links
* uploads all the posts media in WP uploads directories (as an option)
* uploads external media (as an option)
* modifies the post content to keep the media links
* resizes images according to the sizes defined in WP
* defines the featured image to be the first post image
* keeps the alt image attribute
* keeps the image caption
* modifies the internal links
* migrates meta keywords as tags
* migrates page breaks
* can import Joomla articles as posts or pages

No need to subscribe to an external web site.

= Premium version =

The **Premium version** includes these extra features:

* migrates authors and other users with their passwords
* migrates the navigation menus
* SEO: migrates the meta description and the meta keywords
* SEO: keeps the Joomla articles IDs or redirects Joomla URLs to the new WordPress URLs
* compatible with **Joomla 1.0** and **Mambo 4.5 and 4.6** (process {mosimages} and {mospagebreak})
* migrates Joomla 1.0 static articles as pages
* migrates Joomla 2.5+ featured images
* migrates Joomla 2.5+ post links
* migrates Joomla 3.1+ tags
* migrates Joomla 3.7+ custom fields
* migrates Mambo data
* migrates Elxis data (Joomla 1.0 fork)
* ability to run the import automatically from the cron

The Premium version can be purchased on: [https://www.fredericgilles.net/fg-joomla-to-wordpress/](https://www.fredericgilles.net/fg-joomla-to-wordpress/)

= Add-ons =

The Premium version allows the use of add-ons that enhance functionality:

* K2
* EasyBlog
* Flexicontent
* Zoo
* Kunena forum
* sh404sef
* JoomSEF
* OpenSEF
* WP-PostViews (keep Joomla hits)
* JComments
* JomComment
* Komento
* JDownloads
* Joomlatags
* Attachments
* Rokbox
* RokGallery
* JoomGallery
* PhocaDownload
* PhocaGallery
* Joom!Fish translations to WPML
* JEvents events
* Contact Manager
* Docman
* Virtuemart
* JReviews
* Mosets Tree
* User Groups
* WPML
* Simple Image Gallery & Simple Image Gallery Pro
* RSGallery
* Community Builder
* RSBlog
* AllVideos
* HikaShop
* Acymailing

These modules can be purchased on: [https://www.fredericgilles.net/fg-joomla-to-wordpress/add-ons/](https://www.fredericgilles.net/fg-joomla-to-wordpress/add-ons/)

== Installation ==

1.  Install the plugin in the Admin => Plugins menu => Add New => Upload => Select the zip file => Install Now
2.  Activate the plugin in the Admin => Plugins menu
3.  Run the importer in Tools > Import > Joomla (FG)
4.  Configure the plugin settings. You can find the Joomla database parameters in the Joomla file configuration.php<br />
    Hostname = $host<br />
    Port     = 3306 (standard MySQL port)<br />
    Database = $db<br />
    Username = $user<br />
    Password = $password<br />
    Joomla Table Prefix = $dbprefix

== Frequently Asked Questions ==

= I get the message: "[fg-joomla-to-wordpress] Couldn't connect to the Joomla database. Please check your parameters. And be sure the WordPress server can access the Joomla database. SQLSTATE[28000] [1045] Access denied for user 'xxx'@'localhost' (using password: YES)" =

* First verify your login and password to the Joomla database.
If Joomla and WordPress are not installed on the same host:
* If you use CPanel on the Joomla server, a solution is to allow a remote MySQL connection.
 - go into the Cpanel of the Joomla server
 - go down to Database section and click "Remote MySQL"
 - There you can add an access host (WordPress host). Enter the access host as the SOME-WEBSITE-DOMAIN-OR-IP-ADDRESS and click add host.
* Another solution is to copy the Joomla database on the WordPress database:
 - export the Joomla database to a SQL file (with phpMyAdmin for example)
 - import this SQL file on the same database as WordPress
 - run the migration by using WordPress database credentials (host, user, password, database) instead of the Joomla ones in the plugin settings.

= I get this error when testing the connection: "SQLSTATE[HY000] [2002] Connection refused" or "SQLSTATE[HY000] [2002] No such file or directory" =

* This error happens when the host is set like localhost:/tmp/mysql5d.sock
Instead, you must set the host to be localhost;unix_socket=/tmp/mysql5d.sock

= The migration stops and I get the message: "Fatal error: Allowed memory size of XXXXXX bytes exhausted" or I get the message: “Internal server error" =

* First, deactivate all the WordPress plugins except the ones used for the migration
* You can run the migration again. It will continue where it stopped.
* You can add: `define('WP_MEMORY_LIMIT', '512M');` in your wp-config.php file to increase the memory allowed by WordPress
* You can also increase the memory limit in php.ini if you have write access to this file (ie: memory_limit = 1G). See the <a href="https://premium.wpmudev.org/blog/increase-memory-limit/" target="_blank">increase memory limit procedure</a>.

= I get a blank screen and the import seems to be stopped =

* Same as above

= The media are not imported =

* Check the URL field that you filled in the plugin settings. It must be your Joomla home page URL and must start with http://

= The media are not imported and I get the error message: "Warning: copy() [function.copy]: URL file-access is disabled in the server configuration" =

* The PHP directive "Allow URL fopen" must be turned on in php.ini to copy the medias. If your remote host doesn't allow this directive, you will have to do the migration on localhost.

= Nothing is imported at all =

* Check your Joomla version. The Joomla 1.0 database has got a different structure from the other versions of Joomla. Importing Joomla 1.0 database is a Premium feature.

= All the posts are not migrated. Why ? =

* The posts put in trash are not migrated. But unpublished and archived posts are migrated as drafts.
* Some users reported that the Zend Framework causes an incomplete import. So, if all the data is not migrated, consider deactivating the Zend Framework during the migration.

= I get the message: "Fatal error: Class 'PDO' not found" =

* PDO and PDO_MySQL libraries are needed. You must enable them in php.ini on the WordPress host.<br />
Or on Ubuntu:<br />
sudo php5enmod pdo<br />
sudo service apache2 reload

= I get this error: PHP Fatal error: Undefined class constant 'MYSQL_ATTR_INIT_COMMAND' =

* You have to enable PDO_MySQL in php.ini on the WordPress host. That means uncomment the line extension=pdo_mysql.so in php.ini

= Does the migration process modify the Joomla site it migrates from? =

* No, it only reads the Joomla database.

= I get this error: Erreur !: SQLSTATE[HY000] [1193] Unknown system variable 'NAMES' =

* It comes from MySQL 4.0. It will work if you move your database to MySQL 5.0 before running the migration.

= I get this error "Parse error: syntax error, unexpected T_PAAMAYIM_NEKUDOTAYIM" =

* You must use at least PHP 5.3 on your WordPress site.

= I get this error: SQLSTATE[HY000] [2054] The server requested authentication method unknown to the client =

* It is a compatibility issue with your version of MySQL.<br />
You can read this post to fix it: http://forumsarchive.laravel.io/viewtopic.php?id=8667

= None image get transferred into the WordPress uploads folder. I'm using Xampp on Windows. =

* Xampp puts the htdocs in the applications folder which is write protected. You need to move the htdocs to a writeable folder.

= How to import content from one section as posts and another section as pages? =

* You can use the Convert Post Types plugin after the migration.

= Do I need to keep the plugin activated after the migration? =

* No, you can deactivate or even uninstall the plugin after the migration (for the free version only).

= Is there a log file to show the information from the import? =
* Yes since version 1.45.0. First you must put these lines in wp-config.php:<br />
define('WP_DEBUG', true);<br />
define('WP_DEBUG_LOG', true);<br />
And the messages will be logged to wp-content/debug.log.

= How does the plugin handle Weblinks? =

* The plugin imports the Joomla web links to WordPress links managed by the Link Manager plugin: https://wordpress.org/plugins/link-manager/

= My screen hangs because of a lot of errors in the log window =
* You can stop the log auto-refresh by unselecting the log auto-refresh checkbox


Don't hesitate to let a comment on the [forum](https://wordpress.org/support/plugin/fg-joomla-to-wordpress) or to report bugs if you found some.

== Screenshots ==

1. Parameters screen

== Demo ==

https://www.youtube.com/watch?v=bXOQ70s6YS8

== Translations ==
* English (default)
* Esperanto (eo)
* French (fr_FR)
* Spanish (es_ES)
* Italian (it_IT)
* German (de_DE)
* Polish (pl_PL)
* Bulgarian (bg_BG)
* Brazilian (pt_BR)
* Greek (el_EL)
* other can be translated

== Changelog ==

= 4.29.1 =
* Fixed: Rollback of last fix that prevents the relationship between the article and its category

= 4.29.0 =
* Fixed: The term meta key could be incorrect if the imported term is not a regular category
* Tested with WordPress 6.6.1

= 4.28.0 =
* New: Add the hook "fgj2wp_process_content"
* Tested with WordPress 6.6

= 4.27.0 =
* Tested with WordPress 6.5.3

= 4.26.0 =
* Fixed: Files whose filename is longer than 255 characters were not imported
* Fixed: Images were not imported by File System method

= 4.25.1 =
* Tested with WordPress 6.5.2

= 4.25.0 =
* New: Import the PDF files contained in the "iframe" and "object" links

= 4.24.0 =
* New language: Esperanto
* Fixed: Translations missing
* Tested with WordPress 6.5

= 4.23.0 =
* New: Run the plugin during the hook "plugins_loaded"
* Tweak: Replace rand() by wp_rand()
* Tweak: Replace file_get_contents() by wp_remote_get()
* Tweak: Replace file_get_contents() + json_decode() by wp_json_file_decode()
* Tweak: Replace json_encode() by wp_json_encode()
* Tweak: Remove the deprecated argument of get_terms() and wp_count_terms()

= 4.22.0 =
* Fixed: Unsafe SQL calls

= 4.21.0 =
* Fixed: Rename the log file with a random name to avoid a Sensitive Data Exposure

= 4.20.2 =
* Fixed: Error:SQLSTATE[42S22]: Column not found: 1054 Unknown column 'p.featured' in 'field list'

= 4.20.0 =
* New: Import the featured articles as sticky posts

= 4.19.1 =
* Tested with WordPress 6.4.3

= 4.19.0 =
* New: Add the hook "fgj2wp_import_media_filename"
* New: Add the hook "fgj2wp_process_content_media_links_new_link"

= 4.17.1 =
* Fixed: First image was not imported as the featured image

= 4.17.0 =
* New: Don't import the images in duplicate when they have the same filename but a different title
* Fixed: Plugin log can be deleted with a CSRF
* Fixed: Found 3 elements with non-unique id #fgj2wp_nonce
* Tested with WordPress 6.4.2

= 4.15.0 =
* New: Sort the files downloaded by FTP or by the file system
* Tested with WordPress 6.4.1

= 4.13.0 =
* Fixed: Categories with duplicate names and with a parent with a greater ID were not imported
* Tested with WordPress 6.4

= 4.12.0 =
* New: Import the images description

= 4.11.0 =
* Tested with WordPress 6.3.1

= 4.10.2 =
* Fixed: Warning about the Internationalization add-on even if it is installed and active

= 4.10.1 =
* Fixed: Fatal error: Uncaught TypeError: Cannot access offset of type string on string

= 4.10.0 =
* New: Add the functions get_wp_post_ids_from_meta and get_wp_term_ids_from_meta
* Tested with WordPress 6.3

= 4.9.2 =
* Fixed: FTP connection failed with password containing special characters
* Fixed: Fatal error: Uncaught TypeError: preg_match(): Argument #2 ($subject) must be of type string, array given
* Tested with WordPress 6.2.2

= 4.8.1 =
* Tested with WordPress 6.2

= 4.8.0 =
* New: Compatibility with PHP 8.2
* New: Check if Docman 2.x is used on Joomla

= 4.7.1 =
* Update from Premium version

= 4.6.1 =
* Fixed: The option "Import the media with duplicate names" didn't work anymore (regression from 4.2.0). So wrong images were imported.

= 4.5.1 =
* Fixed: Images containing a backslash were not imported

= 4.4.1 =
* Fixed: Images containing the same alt and src, and with alt before src were not displayed in the post

= 4.4.0 =
* Tested with WordPress 6.1.1

= 4.3.0 =
* Tweak: Add the function get_post_meta_like()

= 4.2.1 =
* Tested with WordPress 6.1

= 4.2.0 =
* Tweak: Shorten the filenames if the option "Import the media with duplicate names" is selected
* Tested with WordPress 6.0.3

= 4.0.0 =
* Tested with WordPress 6.0.2

...

= 3.0.0 =
* New: Run the import in AJAX
* New: Add a progress bar
* New: Add a logger frame to see the logs in real time
* New: Ability to stop the import
* New: Compatible with PHP 7

...

= 2.0.0 =
* Restructure the whole code using the BoilerPlate foundation
* FAQ updated

...

= 1.0.0 =
* Initial version: Import Joomla 1.5 sections, categories, posts and images

== Upgrade Notice ==

= 4.29.1 =
Fixed: Rollback of last fix that prevents the relationship between the article and its category

= 4.29.0 =
Fixed: The term meta key could be incorrect if the imported term is not a regular category
Tested with WordPress 6.6.1

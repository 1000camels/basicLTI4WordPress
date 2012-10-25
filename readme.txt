Version 0.1


LTI for Wordpress

You can find the last version of the plugin in subversion https://learningapps.svn.sourceforge.net/svnroot/learningapps/LTI4Wordpress/trunk This is a fork a LTI plugin developed by Chuck Severance http://code.google.com/p/basiclti4wordpress/.
If you have the same email from LMS loged user and existing user in wordpress you will have this error This email address is already registered.
This plugin is tested with Moodle 1.9.x, 2.0, 2.1 and 2.2 * as a Consumer and Wordpress 2.9.x, 3.0.x, 3.1.x, 3.2.x and 3.2.x
Moodle 2.2 doesn't validate if you enable Accept grades from the too because there is a bug encoding {

This plugin is developed to allow anybody to add new blogTypes. BlogType allows to customize differents plugins, names, roles, themes for each kind type of blogs, then you can define your own types to customize each blog. As a default the plugin has two blogTypes defined: defaultType and exampleType, to execute exampleType you have to indicate a custom parameter named: blogType=exampleType, if you don't indicate launches the defaultType.

The aim of this document is to show how to develop and customize new blogTypes for LTI for wordpress. The plugin get the basic data and is based on interface named “blogType”, each blogType has the implementation and allows to customize each blog. For example, if you want to have two sites under your main blog with differents themes you can create your own blogTypes.


Installation

1. Download Moodle http://wordpress.org/download/
2. Add a Multisite http://codex.wordpress.org/Create_A_Network
3. Download plugin http://sourceforge.net/projects/learningapps/files/Files/Basic%20LTI%20Integrations/Wordpress/wordpressLTIPlugin.zip/download
4. Uncompress to wp-content/mu-plugins
5. Log as superAdmin and go to Network Dashboard -> Settings -> LTI Consumers Keys. Here you can manage the authorized consumers keys
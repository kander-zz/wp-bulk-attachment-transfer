Bulk Attachment Transfer (BAT)
==============================

Import attachments from another WordPress blog using a WXR file.

What is this?
-------------
The official Wordpress Importer is not very efficient at importing large amounts of attachments. This plugin loads all
attachments stored in a blog's WXR file and imports them into your blog. It then finds all hardcoded urls to
the old image location in your posts, post's meta information and Wordpress Options, and replaces them.
This includes post_meta fields which contain serialized data.

This importer builds upon the Attachment Importer by Toasted Lime.

Installation
------------
1. Install & activate using the Wordpress Plugin Manager.
3. Go to Tools -> Import -> Bulk Attachment Transfer to run.

Usage
-----
0. As a prerequisite, import your WXR file using the WordPress importer, but do not select the option to Download and Import Attachments. I have found that an import file up to 15MB big will work as long as you don't import attachments.
1. Navigate to the Attachment Importer screen.
2. Select your WXR export file.
3. Select the user you would like to be the owner of the downloaded images. Default: current user.
4. Optionally, select the amount of threads you want to handle uploads simultaneously.
4. Sit back and let the importer run. The process can take as little as 10 seconds for 10 images, or about two hours for 2000 images. These times depend on the server that hosts your WordPress site.
5. If you receive any errors during the process, try running the file again after it finishes.  The plugin is programmed to ignore files that match the following criteria:
   * Same name AND
   * Same file name AND
   * Same upload date AND
   * Same file size

How it works
------------
This plugin uses [FileReader](https://developer.mozilla.org/en-US/docs/Web/API/FileReader) and to parse the XML file 
in the browser. It then uses a Thread Pool to start a group of workers, which will then make requests to the plugin's
server-side code to perform individual uploads and replacements. Note that replacements happen per-upload, so you should
never end up in the situation where your files are transferred but your posts do not match the new file locations.

Credits
-------
- This plugin builds upon the Attachment Importer, by Toasted Lime
- Thanks go to Andy Wermke (https://github.com/andywer) for providing the Javascript Threadpool library
- Many thanks go to my employer Clansman (http://www.clansman.nl) for allowing me to release this as a plugin.

License
-------
Bulk Attachment Transfer - A plugin for WordPress to transfer attachments from another blog using a WXR file.
Based on Attachment Imporer by Toasted Lime (GPL)

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

The license and copyright applies to all resources bundled with this plugin, except as noted below:

Portions of this plugin use code from:

* [WordPress Importer](http://wordpress.org/extend/plugins/wordpress-importer/) which is distributed under the terms of the GNU GPL v2, Copyright (C) 2013 wordpressdotorg.
* [jQuery UI Smoothness Theme](http://jqueryui.com/themeroller/) which is distributed under the terms of MIT License, Copyright (C) 2014 jQuery Foundation and other contributors.
* [Javascript Threadpool](https://github.com/andywer/threadpool-js/blob/master/) which is distributed under the terms of MIT License, Copyright (c) 2013 Andy Wermke and other contributors
=== Very Simple Knowledge Base ===
Contributors: Guido07111975
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=donation%40guidovanderleest%2enl
Version: 2.8
License: GNU General Public License v3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.7
Tested up to: 4.5
Stable tag: trunk
Tags: simple, responsive, knowledge, base, bulletin, board, faq, wiki, portal, post, category


This is a very simple plugin to create a knowledgebase. Use a shortcode to display your categories and posts in 3 or 4 columns on a page.


== DESCRIPTION ==
This is a very simple plugin to create a responsive Knowledge Base, Bulletin Board, FAQ, Wiki or Link Portal. 

It uses the default WordPress categories and posts. 

Add a shortcode to display them:

* For 3 columns: `[knowledgebase-three]`
* For 4 columns: `[knowledgebase]`

In mobile screens 2 columns.

Shortcode accepts attributes too. You can find more info about this at the Installation section.

= Question? =
Please take a look at the Installation and FAQ section.

= Translation =
Not included but plugin supports WordPress language packs.

More [translations](https://translate.wordpress.org/projects/wp-plugins/very-simple-knowledge-base) are very welcome!

= Credits =
Without the WordPress codex and help from the WordPress community I was not able to develop this plugin, so: thank you!

And I would like to thank the users of 'PHP hulp' for helping me creating bugfree code.

Enjoy!


== INSTALLATION == 
After installation create a page and add a shortcode to display your categories and posts:

* For 3 columns: `[knowledgebase-three]`
* For 4 columns: `[knowledgebase]`

In mobile screens 2 columns.

= Default settings categories = 
* Ascending order (A-Z)
* empty categories are hidden
* Parent and subcategories are listed the same way

= Default settings posts = 
* Descending order (by date)
* all posts are displayed

= Shortcode attributes =
* To include certain categories: `[knowledgebase include=1,3,5]`
* To exclude certain categories: `[knowledgebase exclude=8,10,12]`
* To display empty categories too: `[knowledgebase hide_empty=0]`
* To set amount of posts for each category: `[knowledgebase posts_per_page=5]`
* To display posts in ascending order: `[knowledgebase order=asc]`
* To display posts by title: `[knowledgebase orderby=title]`
* To display posts in random order: `[knowledgebase orderby=rand]`
* Multiple attributes: `[knowledgebase include=1,3,5 hide_empty=0 orderby=rand]`

= Link Portal =
To display a list of website links you can install the [Page Links To](https://wordpress.org/plugins/page-links-to) plugin.

While creating a post you can add the URL (website) of your choice.

When you click the post link in frontend it will redirect you to this URL (so the post will not open).


== Frequently Asked Questions ==
= How are categories listed? =
You can find more info about this at the Installation section.

= How are posts listed? =
You can find more info about this at the Installation section.

= Does is display properly in all browsers? = 
The knowledgebase might not display 100% in IE8 and older because I have used css selector 'nth-of-type'.

= How can I make a donation? =
You like my plugin and you're willing to make a donation? Nice! There's a PayPal donate link on the WordPress plugin page and my website.

= Other question or comment? =
Please open a topic in plugin forum.


== Changelog ==
= Version 2.8 =
* changed file names
* updated readme file

= Version 2.7 =
* relocated file vskb_style to folder css
* updated readme file

= Version 2.6 =
* added a PayPal donate link
* updated readme file

= Version 2.5 =
* updated file readme

= Version 2.4 =
* added extra shortcode attributes, more info about this at the Installation section
* updated file readme

= Version 2.3 =
* added fix to remove border bottom from links in Twenty Sixteen

= Version 2.2 =
* modified the shortcode again: it supports several category and post attributes now, more info about this at the Installation section

= Version 2.1 =
* modified the shortcode: it supports several category attributes now, more info about this at the Installation section

= Version 2.0 =
* removed translations: plugin now support WordPress language packs

= Version 1.9 =
* changed text domain for the wordpress.org translation system

= Version 1.8 =
* removed files three_columns_subcats and four_columns_subcats again
* updated files three_columns and four_columns: will list sub categories now as well

= Version 1.7 =
* added 2 files to list sub categories too: three_columns_subcats and four_columns_subcats
* updated files three_columns and four_columns
* updated file readme

= Version 1.6 =
* updated language files

= Version 1.5 =
* relocated shortcode from file vskb to files three_columns and four_columns
* files four_columns and vskb_style: changed div vskb into vskb-four
* hide subcategory name in list (post name will be displayed under parent category name)

= Version 1.4 =
* reordered file vskb_style
* added fix to remove border bottom from links in Twenty Fifteen

= Version 1.3 =
* now also shortcode for 3 columns: [knowledgebase-three]
* adjusted file vskb.php and file vskb_style.css
* added file three_columns.php and file four_columns.php
* updated language files

= Version 1.2 =
* adjusted the shortcode
* removed background color and link color from stylesheet

= Version 1.1 =
* forgot pot file and Dutch translation files in version 1.0
* small css adjustments

= Version 1.0 =
* first stable release


== Screenshots == 
1. Very Simple Knowledge Base (Twenty Sixteen theme).
2. Very Simple Knowledge Base (dashboard).
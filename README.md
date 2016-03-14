# About #

wikkiv is a simple PHP wiki web application, with support for multiple users, non-public pages and file uploads.

## Installation ##

- Copy wikkiv.php, eyesonly.php, markdown.php, htaccess and the files folder to your server.
- Set up a database and edit eyesonnly.php with the credentials, and set $INIT_ENABLED to TRUE.
- (Apache only) Set up the htaccess file for your domain, then rename it to ".htaccess".
- Go to the wiki home page with ?init=1, e.g., "http://example.com/?init=1".
- Log in with username admin, password admin to verify it's working, then change the admin password.
- Edit your eyesonly.php again, changing $INIT_ENABLED back to FALSE.

If you're using nginx, rename wikkiv.php to index.php and you'll want a server config like this (this is for Ubuntu 14.04):

```
server {
    listen 80;
    server_name www.example.com;

    root /home/wiki/www/;
    index index.php;

    client_max_body_size 20m;
    location / {
        try_files $uri $uri/ /index.php?q=$uri&$args;
        proxy_read_timeout 300;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php5-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_read_timeout 300s;
    }
}
```

Optional, but recommended: move eyesonly.php outside your www root and alter wikkiv.php to change the `require_once` path.

## Page Formatting ##

Pages are formatted using [Markdown](http://daringfireball.net/projects/markdown/) and links to pages within the wiki are composed using double curly braces:

- `{{Web Server}}` links to a page titled Web Server, and the link appears as Web Server.
- `{{Web Server}{webserver}}` links to a page named webserver, and the link appears as Web Server.

Markdown style links to external sites are supported too, e.g., `[example.com](http://www.example.com/)`.

## Images ##

You can upload images to a page using the "File manager" link that appears when you're signed in and have permission to edit a page. Once the file has been uploaded, link to it with `{{! filename.jpg}}`. You can make images float on the left or right hand side of the page using, e.g., `{{! left filename.jpg}}`, and you can give images a description, e.g., `{{A cat with humerous text}{! filename.jpg}}`.

## Sections ##

You can subdivide your wiki in to sections by prefixing page names with the section name followed by an underscore, e.g.:

- `{{Source Code_}}` links to the index page of the Source Code section, which'll have a URL like `http://example.com/source-code/`.
- `{{Source Code_Super Project 1}}` links to the Super Project 1 page of the Source Code section, which'll have a URL like `http://example.com/source-code/super-project-1.html`.

You can link back to the default section by specifying an empty section name, e.g., `{{_}}` would link you back to the wiki index.

## Users ##

Use the "User admin" link to add or remove users, change passwords or make users admins (or not).

## Permissions ##

When you edit a page you can choose which users can see, edit or administer a page. By default (all the permissions fields are left empty), anyone can view a page but only registered users can edit a page, but you could restrict viewing/editing as follows:

        Allow these users to view this page: myself another
        Prevent these users from viewing this page: world
        Allow these users to edit this page: myself another
        Prevent these users editing this page:
        Allow these users to edit this page's permissions:
        
All pages inherit their permissions from the index page of their section. The `world` user represents all users except admins (including logged in users, so "Prevent these users: world" will restrict anyone not in the "Allow these users" box. Separate multiple usernames with spaces.

If you muck up the permissions of a page, an admin user can always fix it.

A PHP class to resize images on-the-fly. Stores a server-side cache of resized image to speed up process. Pretty handy for use in HTML where resizing images manually is a pain, and you need a nice quick way of getting a high-res image scaled down and displaying on the front-end

For some examples of usage, please check out examples.html. The code must be running on a web server (either local or remote) for this to work.

All the configuration options (some experimental) can be found, and configured, in config.ini. All these options can be over-ridden by applying them within the URL string appended to thumbs.php (see the code of examples.html to learn more)

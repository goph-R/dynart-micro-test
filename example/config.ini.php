;<?php /*

; Logging
; -------

; The logging level, can be: debug, info, warning, error
log.level = "error"

; Path for logging
log.dir = "./logs"

; Application
; -----------

; Default is "prod" but we should override while we develop an application,
; or use an index.dev.php with a config.dev.ini.php (recommended)
app.environment = "dev"

; The base URL of the application (for example: https://domain.com/app)
app.base_url = "http://localhost/dynart-micro"

; The root path of the application (for example: /var/www/domain.com)
app.root_path = "."

; Folder of the error pages
; The folder should contain 401.html, 403.html, 404.html, 500.html and so on for the different errors codes
; The HTML itself can have a <!-- content --> placeholder for the content (if any)
app.error_pages_folder = "~/static/errors"

; Router
; ------

; Is the server uses rewrite for routing?
; Check the example .htaccess file for Apache server config
router.use_rewrite = false

; The index file (usually index.php)
router.index_file = "index.php"

; The query parameter that will be used for routing
router.route_parameter = "route"

; View
; ----

; The default folder for the views
view.default_folder = "~/vendor/dynart/micro/views"

; Translation
; -----------

; All translations locale that the application has
translation.all = "hu, en"

; The default locale if the browser didn't set it in the Accept-Language
; or the accepted language doesn't present in the `translation.all`
translation.default = "hu"

;*/
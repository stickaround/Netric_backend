<?php
    // Built files are hashed for cache breaking so use the manifest to get the absolute name and path
    $manifest = ['netric.js'=>'/mobile/js/netric.js', 'netric.css'=>'/mobile/css/netric.css'];

    if (file_exists("webpack-manifest.json")) {
        $manifest = json_decode(file_get_contents("webpack-manifest.json"), true);
    } else {
        throw new Exception("Netric webapp not installed");
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <!--
        Customize this policy to fit your own app's needs. For more guidance, see:
            https://github.com/apache/cordova-plugin-whitelist/blob/master/README.md#content-security-policy
        Some notes:
            * gap: is required only on iOS (when using UIWebView) and is needed for JS->native communication
            * https://ssl.gstatic.com is required only on Android and is needed for TalkBack to function properly
            * Disables use of inline scripts in order to mitigate risk of XSS vulnerabilities. To change this:
                * Enable inline JS: add 'unsafe-inline' to default-src
        -->
        <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' data: gap: https://ssl.gstatic.com 'unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self' http://*.netric.com https://*.netric.com http://netric.myaereus.com; media-src *; font-src *">
        <meta name="format-detection" content="telephone=no">
        <meta name="msapplication-tap-highlight" content="no">
        <meta name="viewport" content="user-scalable=no, initial-scale=1, maximum-scale=1, minimum-scale=1, width=device-width">
        <link rel="stylesheet" id='netric-css-base' href="<?php print($manifest['netric.css']); ?>" />
        <title>Netric</title>
        <script type="text/javascript" src="<?php print($manifest['netric.js']); ?>"></script>
		<script>
            function startApplication() {
                netric.Application.load(function(app){
                    app.run(document.getElementById("netric-app"));
                }, "https://aereus.netric.com", "/mobile");
            }
        </script>
    </head>
    <body onload="startApplication();">
        <div id='netric-app'></div>
    </body>
</html>

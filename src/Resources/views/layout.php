<!DOCTYPE html>
<html>
    <head>
        <title>Framework</title>
        <meta charset="utf-8" />
        <link rel="stylesheet" href="<?= $this->asset('style.css'); ?>" type="text/css"/>
    </head>
    <body>
        <?php echo $content; ?>
        <script src="<?php echo $this->asset('main.js')?>"></script>
    </body>
</html>
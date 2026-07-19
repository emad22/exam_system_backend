<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Basic reset used for PDF rendering */
        body { font-family: 'DejaVu Sans', sans-serif; margin:0; padding:0; }
        .page { width:1123px; height:794px; margin:0 auto; position:relative; }
        .content { position:relative; z-index:1; box-sizing:border-box; width:100%; height:100%; }
        /* Ensure tables and other elements don't break across pages */
        table { border-collapse: collapse; }
        img { max-width:100%; }
        /* You can override/add styles using the template's HTML */
    </style>
</head>
<body>
    <div class="page">
        <div class="content">
            {!! $content !!}
        </div>
    </div>
</body>
</html>

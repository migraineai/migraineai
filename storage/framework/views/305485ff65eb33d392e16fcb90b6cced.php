<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="min-h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <title inertia><?php echo e(config('app.name', 'Migraine Tracker')); ?></title>
        <style>[v-cloak]{display:none}</style>

        <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>">
        <link rel="shortcut icon" href="<?php echo e(asset('favicon.ico')); ?>">
        <link rel="apple-touch-icon" sizes="180x180" href="<?php echo e(asset('logo.png')); ?>">
        <link rel="mask-icon" href="<?php echo e(asset('logo.png')); ?>" color="#5f9e86">

        <?php if(config('services.google_analytics.measurement_id')): ?>
            <!-- Google Analytics -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e(config('services.google_analytics.measurement_id')); ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '<?php echo e(config('services.google_analytics.measurement_id')); ?>');
            </script>
        <?php endif; ?>

        <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.ts']); ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="min-h-screen bg-[--color-bg] text-[--color-text-primary] antialiased">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH /Users/ashwinjumani/Sites/GWS/migrainai/resources/views/app.blade.php ENDPATH**/ ?>
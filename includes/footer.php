</div> <!-- End of container -->

    <!-- jQuery -->
    <script src="<?php echo SITE_URL; ?>/assets/js/jquery.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="<?php echo SITE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($inlineJs)): ?>
        <script>
            <?php echo $inlineJs; ?>
        </script>
    <?php endif; ?>
</body>
</html>
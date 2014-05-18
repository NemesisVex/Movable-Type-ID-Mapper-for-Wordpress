<div class="wrap">
    <h2>Movable Type ID Mapper Settings</h2>
    <form method="post" action="options.php"> 
        <?php @settings_fields('mt_id_mapper-group'); ?>
        <?php @do_settings_fields('mt_id_mapper-group'); ?>

        <?php do_settings_sections('mt_id_mapper'); ?>

        <?php @submit_button(); ?>
    </form>
</div>